<?php

namespace App\Services;

use App\Enums\DecisionLitige;
use App\Enums\StatutLitige;
use App\Enums\StatutReservation;
use App\Events\LitigeCloture;
use App\Events\LitigeOuvert;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Litige;
use App\Models\LitigePreuve;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Litiges (RF07, RF17, RF19) : ouverture par le client sur une réservation CONFIRMEE ou au-delà, réponse unique du
 * prestataire, clôture par l'administrateur (OUVERT → CLOTURE). Aucun mouvement d'argent n'est déclenché ici.
 * Les preuves (photos) sont sur le disque privé, sous « litiges/{litige} », avec un nom aléatoire.
 */
class LitigeService
{
    public function __construct(private readonly SanctionService $sanctions) {}

    /** Statuts de réservation sur lesquels un litige est possible : la prestation a été confirmée (payée) au moins. */
    public const STATUTS_ELIGIBLES = [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee];

    /** Une réservation engendre au plus un litige (diagramme de classes), même clôturé. */
    public const MESSAGE_LITIGE_EXISTANT = 'Un litige a déjà été ouvert pour cette réservation.';

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param  list<UploadedFile>  $preuves
     *
     * @throws ReservationImpossibleException 404 (introuvable ou à quelqu'un d'autre), 409 (statut non éligible, un litige existe déjà pour la réservation, même clôturé)
     */
    public function ouvrir(User $client, string $reservationId, string $motif, string $description, array $preuves = []): Litige
    {
        $reservation = Str::isUuid($reservationId)
            ? Reservation::query()->with('creneau')->where('client_id', $client->id)->find($reservationId)
            : null;

        if ($reservation === null) {
            throw new ReservationImpossibleException('Réservation introuvable.', Response::HTTP_NOT_FOUND);
        }

        if (! in_array($reservation->statut, self::STATUTS_ELIGIBLES, true)) {
            throw new ReservationImpossibleException('Un litige ne peut être ouvert que sur une réservation confirmée, en cours ou terminée.', Response::HTTP_CONFLICT);
        }

        $ecrits = [];

        try {
            return DB::transaction(function () use ($client, $reservation, $motif, $description, $preuves, &$ecrits) {
                if (Litige::query()->where('reservation_id', $reservation->id)->exists()) {
                    throw new ReservationImpossibleException(self::MESSAGE_LITIGE_EXISTANT, Response::HTTP_CONFLICT);
                }

                $litige = new Litige;
                $litige->reservation_id = $reservation->id;
                $litige->client_id = $client->id;
                // Le prestataire vient TOUJOURS de la réservation, jamais de la requête.
                $litige->prestataire_id = $reservation->creneau->prestataire_id;
                $litige->statut = StatutLitige::Ouvert;
                $litige->motif = $motif;
                $litige->description = $description;
                $litige->save();

                foreach ($preuves as $fichier) {
                    $nom = Str::uuid().'.'.(self::EXTENSIONS[$fichier->getMimeType()] ?? 'bin');
                    Storage::disk(InscriptionPrestataireService::DISQUE)->putFileAs($this->dossier($litige), $fichier, $nom);
                    $ecrits[] = $this->dossier($litige).'/'.$nom;

                    $preuve = new LitigePreuve;
                    $preuve->litige_id = $litige->id;
                    $preuve->fichier = $nom;
                    $preuve->save();
                }

                LitigeOuvert::dispatch($litige);

                return $litige;
            });
        } catch (UniqueConstraintViolationException) {
            // Deux ouvertures simultanées : l'index unique sur reservation_id ne laisse passer que la première.
            Storage::disk(InscriptionPrestataireService::DISQUE)->delete($ecrits);

            throw new ReservationImpossibleException(self::MESSAGE_LITIGE_EXISTANT, Response::HTTP_CONFLICT);
        } catch (Throwable $erreur) {
            Storage::disk(InscriptionPrestataireService::DISQUE)->delete($ecrits);

            throw $erreur;
        }
    }

    /**
     * Réponse unique du prestataire (sa version des faits), tant que le litige est ouvert.
     *
     * @throws ReservationImpossibleException 404 (litige d'un autre prestataire ou inconnu), 409 (déjà répondu, clôturé)
     */
    public function repondre(Prestataire $prestataire, string $id, string $reponse): Litige
    {
        $litige = $this->trouverPourPrestataire($prestataire, $id);

        if ($litige === null) {
            throw new ReservationImpossibleException('Litige introuvable.', Response::HTTP_NOT_FOUND);
        }

        $maintenant = now();
        $mis = Litige::query()
            ->whereKey($litige->id)
            ->where('statut', StatutLitige::Ouvert->value)
            ->whereNull('reponse_prestataire')
            ->update(['reponse_prestataire' => $reponse, 'reponse_le' => $maintenant, 'updated_at' => $maintenant]);

        if ($mis === 0) {
            $message = $litige->fresh()->statut === StatutLitige::Cloture
                ? 'Ce litige est déjà clôturé.'
                : 'Vous avez déjà répondu à ce litige.';

            throw new ReservationImpossibleException($message, Response::HTTP_CONFLICT);
        }

        return $this->charger($litige->fresh());
    }

    /**
     * Clôture d'un litige ouvert par une décision motivée. Mise à jour conditionnelle : deux décisions simultanées,
     * une seule passe. Le rejet ne touche à aucun compte. Une décision de sanction pose la sanction (avec le motif de la
     * décision) sur le prestataire ou le client, dans la MÊME transaction que la clôture : tout passe ou rien.
     *
     * @param  string  $cible  `prestataire` ou `client` : le compte sanctionné par une décision de sanction
     *
     * @throws ReservationImpossibleException 404 (inconnu), 409 (déjà clôturé, sanction impossible), 403 (administrateur visé)
     */
    public function trancher(User $admin, string $id, DecisionLitige $decision, string $motif, string $cible = 'prestataire', ?Carbon $dateFin = null): Litige
    {
        $litige = Str::isUuid($id) ? Litige::query()->find($id) : null;

        if ($litige === null) {
            throw new ReservationImpossibleException('Litige introuvable.', Response::HTTP_NOT_FOUND);
        }

        $cloturer = function () use ($admin, $litige, $decision, $motif) {
            $maintenant = now();
            $mis = Litige::query()
                ->whereKey($litige->id)
                ->where('statut', StatutLitige::Ouvert->value)
                ->update([
                    'statut' => StatutLitige::Cloture->value,
                    'decision' => $decision->value,
                    'motif_decision' => $motif,
                    'decide_par_id' => $admin->id,
                    'cloture_le' => $maintenant,
                    'updated_at' => $maintenant,
                ]);

            if ($mis === 0) {
                throw new ReservationImpossibleException('Ce litige est déjà clôturé.', Response::HTTP_CONFLICT);
            }

            LitigeCloture::dispatch($this->charger($litige->fresh()));
        };

        $type = $decision->typeSanction();
        if ($type === null) {
            DB::transaction($cloturer);
        } else {
            $litige->loadMissing('prestataire.user', 'client');
            $compte = $cible === 'client' ? $litige->client : $litige->prestataire->user;

            $this->sanctions->poser($admin, $compte, $type, $motif, $dateFin, $litige, null, $cloturer);
        }

        return $this->charger($litige->fresh());
    }

    public function listerPourClient(User $client): LengthAwarePaginator
    {
        return Litige::query()->with($this->relationsListe())->withCount('preuves')
            ->where('client_id', $client->id)->latest()->latest('id')->paginate(20);
    }

    public function listerPourPrestataire(Prestataire $prestataire): LengthAwarePaginator
    {
        return Litige::query()->with($this->relationsListe())->withCount('preuves')
            ->where('prestataire_id', $prestataire->id)->latest()->latest('id')->paginate(20);
    }

    public function listerPourAdmin(string $statut): LengthAwarePaginator
    {
        $requete = Litige::query()->with($this->relationsListe())->withCount('preuves');

        if ($statut !== 'tous') {
            $requete->where('statut', $statut);
        }

        // Les plus anciens d'abord : ce sont ceux qui attendent depuis le plus longtemps.
        return $requete->oldest()->oldest('id')->paginate(20);
    }

    public function trouverPourClient(User $client, string $id): ?Litige
    {
        return Str::isUuid($id)
            ? $this->chargerOuNull(Litige::query()->where('client_id', $client->id)->find($id))
            : null;
    }

    public function trouverPourPrestataire(Prestataire $prestataire, string $id): ?Litige
    {
        return Str::isUuid($id)
            ? $this->chargerOuNull(Litige::query()->where('prestataire_id', $prestataire->id)->find($id))
            : null;
    }

    public function trouverPourAdmin(string $id): ?Litige
    {
        $litige = Str::isUuid($id) ? Litige::query()->find($id) : null;

        // L'administrateur voit aussi les paiements de la réservation.
        return $litige === null ? null : $this->charger($litige)->load('reservation.paiements');
    }

    /** Flux d'une preuve du litige (accès déjà contrôlé par l'appelant), ou null si la preuve est inconnue ou son fichier absent. */
    public function fluxPreuve(Litige $litige, string $preuveId): ?StreamedResponse
    {
        $preuve = Str::isUuid($preuveId) ? $litige->preuves()->find($preuveId) : null;
        if ($preuve === null) {
            return null;
        }

        $disque = Storage::disk(InscriptionPrestataireService::DISQUE);
        $chemin = $this->dossier($litige).'/'.$preuve->fichier;
        if (! $disque->exists($chemin)) {
            return null;
        }

        $type = $disque->mimeType($chemin);
        if (! array_key_exists($type, self::EXTENSIONS)) {
            return null;
        }

        return $disque->response($chemin, $preuve->fichier, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }

    private function chargerOuNull(?Litige $litige): ?Litige
    {
        return $litige === null ? null : $this->charger($litige);
    }

    private function charger(Litige $litige): Litige
    {
        return $litige->load([...$this->relationsListe(), 'preuves', 'decidePar', 'sanction']);
    }

    /** @return list<string> */
    private function relationsListe(): array
    {
        return ['reservation.creneau', 'reservation.prestation', 'client', 'prestataire.user'];
    }

    private function dossier(Litige $litige): string
    {
        return 'litiges/'.$litige->id;
    }
}
