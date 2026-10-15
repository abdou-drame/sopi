<?php

namespace App\Http\Controllers;

use App\Enums\StatutCompte;
use App\Enums\StatutJustificatif;
use App\Models\Metier;
use App\Services\SuiviDossierService;
use Illuminate\Http\Response;

/**
 * Page web publique de suivi du dossier d'inscription : le lien reçu par e-mail s'ouvre dans un navigateur.
 * Même logique que l'API JSON (SuiviDossierService) ; aucune donnée personnelle ; le jeton n'est jamais journalisé ni transmis à un tiers.
 */
class SuiviDossierPageController extends Controller
{
    private const DOCUMENTS = ['cni' => 'Carte d\'identité', 'diplome' => 'Diplôme', 'attestation' => 'Attestation'];

    private const STATUTS_DOCUMENT = ['en_attente' => 'En attente', 'valide' => 'Validé', 'rejete' => 'Refusé'];

    public function __invoke(string $jeton, SuiviDossierService $service): Response
    {
        $prestataire = $service->trouverParJeton($jeton);

        if ($prestataire === null) {
            return $this->reponse(response()->view('suivi.introuvable', [], 404));
        }

        $statut = $prestataire->user->statut;
        $refuse = $statut === StatutCompte::Refuse;
        $etat = match (true) {
            $statut === StatutCompte::EnAttenteValidation => ['classe' => 'attente', 'titre' => 'En cours d\'examen', 'texte' => 'Votre dossier a bien été reçu. L\'équipe Sopi l\'examine : revenez sur cette page pour suivre la décision.'],
            $refuse => ['classe' => 'refuse', 'titre' => 'Dossier refusé', 'texte' => 'Votre dossier n\'a pas été retenu.'],
            default => ['classe' => 'accepte', 'titre' => 'Dossier accepté, vous pouvez vous connecter', 'texte' => 'Votre compte prestataire est actif. Connectez-vous dans l\'application Sopi.'],
        };

        $justificatifs = $prestataire->justificatifs->sortBy('date_soumission')->map(fn ($j) => [
            'document' => self::DOCUMENTS[$j->type_document->value] ?? $j->type_document->value,
            'statut' => self::STATUTS_DOCUMENT[$j->statut->value] ?? $j->statut->value,
            'classe' => $j->statut === StatutJustificatif::Valide ? 'accepte' : ($j->statut === StatutJustificatif::Rejete ? 'refuse' : 'attente'),
            'motif' => $j->statut === StatutJustificatif::Rejete ? $j->motif_rejet : null,
        ])->values();

        return $this->reponse(response()->view('suivi.dossier', [
            'entreprise' => $prestataire->nom_entreprise,
            'metier' => Metier::where('code', $prestataire->metier)->value('libelle') ?? ucfirst($prestataire->metier),
            'date' => $prestataire->created_at?->timezone('Africa/Dakar')->locale('fr')->translatedFormat('j F Y'),
            'etat' => $etat,
            'motif' => $refuse ? $prestataire->motif_refus : null,
            'justificatifs' => $justificatifs,
        ]));
    }

    /** Page de suivi : jamais mise en cache, aucune adresse de provenance transmise (le jeton est dans l'URL). */
    private function reponse(Response $reponse): Response
    {
        return $reponse->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
