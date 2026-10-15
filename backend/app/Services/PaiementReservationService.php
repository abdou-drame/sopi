<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Paiement\CalculPaiement;
use App\Services\Paiement\DexPay\JournalDexPay;
use App\Services\Paiement\PaiementService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Initiation du paiement de l'acompte d'une réservation ACCEPTEE (M6.3). Ne change jamais le statut de la réservation et ne
 * génère aucun code : la confirmation vient du webhook (M6.4). Montants calculés par le serveur à partir du prix figé de la réservation (prix_applique).
 */
class PaiementReservationService
{
    /** Un paiement EN_ATTENTE sans référence plus ancien que ceci est considéré comme abandonné (échec ou arrêt en cours d'appel). */
    public const DELAI_SESSION_SECONDES = 120;

    public function __construct(private readonly CalculPaiement $calcul) {}

    /**
     * Crée (ou renvoie) le paiement EN_ATTENTE de la réservation du client.
     *
     * @return array{0: Paiement, 1: bool} le paiement (avec la réservation et la prestation) et vrai s'il vient d'être créé
     *
     * @throws ReservationImpossibleException 404 (introuvable), 409 (statut, délai, déjà payée, en cours d'initialisation) ou 503 (prestataire de paiement)
     */
    public function initier(User $client, string $id): array
    {
        $reservation = Str::isUuid($id) ? Reservation::query()->where('client_id', $client->id)->find($id) : null;
        if ($reservation === null) {
            throw new ReservationImpossibleException('Réservation introuvable.', Response::HTTP_NOT_FOUND);
        }

        // Transaction 1 : verrou de ligne sur la réservation, puis paiement EN_ATTENTE. Aucun appel externe ici.
        [$paiement, $existant] = DB::transaction(function () use ($reservation, $client) {
            if (! $reservation->prendreVerrouPaiement($client->id, self::limitePaiement())) {
                throw $this->refus($reservation, $client);
            }

            $actif = $this->paiementActif($reservation);
            if ($actif === null) {
                return $this->ouvrir($reservation);
            }

            return [$this->reutiliser($actif), true];
        });

        if ($existant) {
            return [$this->charger($paiement), false];
        }

        // Hors transaction : appel au prestataire de paiement, puis enregistrement conditionnel de la session.
        try {
            $session = app(PaiementService::class)->creerSession($reservation->id, $paiement->montant_acompte, 'Acompte de réservation Sopi', $paiement->id);
            if ($session->reference === '' || $session->urlPaiement === '') {
                throw new \UnexpectedValueException('Session de paiement incomplète.');
            }
        } catch (Throwable $e) {
            $this->echouer($paiement, $e);
        }

        if (! $paiement->enregistrerSessionSiEnAttente($session)) {
            $contexte = JournalDexPay::erreur('dexpay.creer_session_echec', 'Session de paiement non enregistrée.', ['paiement_id' => $paiement->id]);

            throw $this->indisponible(JournalDexPay::diagnostic($contexte));
        }

        return [$this->charger($paiement), true];
    }

    /** Vrai si la réservation est ACCEPTEE, dans le délai de paiement et sans paiement réussi. */
    public static function peutPayer(Reservation $reservation): bool
    {
        if ($reservation->statut !== StatutReservation::Acceptee || $reservation->acceptee_le === null
            || $reservation->acceptee_le->lessThanOrEqualTo(self::limitePaiement())) {
            return false;
        }

        $reussi = array_key_exists('a_paiement_reussi', $reservation->getAttributes())
            ? (bool) $reservation->getAttribute('a_paiement_reussi')
            : $reservation->paiements()->where('statut', StatutPaiement::Reussi->value)->exists();

        return ! $reussi;
    }

    /** Une réservation acceptée avant cet instant (UTC) n'est plus payable. */
    private static function limitePaiement(): CarbonInterface
    {
        return now()->utc()->subHours((int) config('sopi.reservations.delai_paiement_heures'));
    }

    /** @return array{0: Paiement, 1: bool} */
    private function ouvrir(Reservation $reservation): array
    {
        $montants = $this->calcul->calculer($reservation->prix_applique);

        try {
            // Transaction imbriquée = point de sauvegarde : un refus de l'index ne casse pas la transaction (PostgreSQL).
            return [DB::transaction(fn () => Paiement::ouvrir($reservation->id, $montants)), false];
        } catch (UniqueConstraintViolationException) {
            // Dernière protection (index unique partiel) : un paiement actif est apparu entre-temps, on le renvoie.
            $actif = $this->paiementActif($reservation) ?? throw $this->indisponible();

            return [$this->reutiliser($actif), true];
        }
    }

    /** Paiement déjà actif : renvoyé tel quel s'il a sa page de paiement, 409 s'il est réussi, repris s'il est abandonné. */
    private function reutiliser(Paiement $actif): Paiement
    {
        if ($actif->statut === StatutPaiement::Reussi) {
            throw new ReservationImpossibleException('Cette réservation est déjà payée.', Response::HTTP_CONFLICT);
        }
        if ($actif->url_paiement !== null) {
            return $actif;
        }

        throw new ReservationImpossibleException('Un paiement est déjà en cours d\'initialisation. Veuillez réessayer dans un instant.', Response::HTTP_CONFLICT);
    }

    private function paiementActif(Reservation $reservation): ?Paiement
    {
        $actif = $reservation->paiements()->whereIn('statut', array_column(StatutPaiement::ACTIFS, 'value'))->first();

        // Abandonné (jamais de référence, ancien) : on le passe à ECHOUE pour pouvoir en ouvrir un nouveau.
        if ($actif?->statut === StatutPaiement::EnAttente && $actif->reference_dexpay === null
            && $actif->echouerSiSansReference(now()->subSeconds(self::DELAI_SESSION_SECONDES))) {
            return null;
        }

        return $actif;
    }

    private function echouer(Paiement $paiement, Throwable $e): never
    {
        $paiement->echouerSiSansReference();
        // Ni secret, ni corps de requête : l'identifiant du paiement, la classe de l'exception, le code HTTP et un extrait masqué.
        $contexte = JournalDexPay::erreur('dexpay.creer_session_echec', 'Échec de la création de la session de paiement.', ['paiement_id' => $paiement->id], $e);

        throw $this->indisponible(JournalDexPay::diagnostic($contexte));
    }

    /** @param array<string, mixed>|null $diagnostic détail masqué, seulement si DEXPAY_DIAGNOSTIC_REPONSE est actif */
    private function indisponible(?array $diagnostic = null): ReservationImpossibleException
    {
        return new ReservationImpossibleException('Le service de paiement est momentanément indisponible. Veuillez réessayer.', Response::HTTP_SERVICE_UNAVAILABLE, $diagnostic);
    }

    private function charger(Paiement $paiement): Paiement
    {
        return $paiement->load('reservation.prestation');
    }

    /** Explique l'échec du verrou (relecture APRÈS l'écriture refusée, pour choisir le message). */
    private function refus(Reservation $reservation, User $client): ReservationImpossibleException
    {
        $actuelle = Reservation::query()->whereKey($reservation->id)->where('client_id', $client->id)->first();

        $message = match (true) {
            in_array($actuelle?->statut, [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee], true) => 'Cette réservation est déjà payée.',
            $actuelle?->statut === StatutReservation::Acceptee => 'Le délai de paiement est dépassé.',
            default => 'Cette réservation n\'est pas en attente de paiement.',
        };

        return new ReservationImpossibleException($message, Response::HTTP_CONFLICT);
    }
}
