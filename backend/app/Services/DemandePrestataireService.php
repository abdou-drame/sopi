<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Models\Justificatif;
use App\Models\Prestataire;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consultation par l'administrateur des demandes d'inscription des prestataires (RF19).
 */
class DemandePrestataireService
{
    public const PAR_PAGE = 20;

    /** Types affichés dans le navigateur ; tout le reste est téléchargé. */
    private const TYPES_INLINE = ['application/pdf', 'image/jpeg', 'image/png'];

    private const EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /**
     * @param  string  $statut  en_attente, actif, refuse ou tous
     */
    public function lister(string $statut): LengthAwarePaginator
    {
        $requete = Prestataire::query()
            ->with('user')
            ->withCount('justificatifs');

        if ($statut !== 'tous') {
            $requete->whereHas('user', fn ($u) => $u->where('statut', StatutCompte::from($statut)->value));
        }

        // Du plus récent au plus ancien, comme toutes les listes du back-office.
        $requete->orderByDesc('created_at')->orderByDesc('id');

        return $requete->paginate(self::PAR_PAGE);
    }

    public function detail(string $id): ?Prestataire
    {
        return Prestataire::with(['user', 'justificatifs' => fn ($q) => $q->orderBy('date_soumission')])->find($id);
    }

    public function trouverJustificatif(string $id): ?Justificatif
    {
        return Justificatif::find($id);
    }

    public function fichierExiste(Justificatif $justificatif): bool
    {
        return Storage::disk(InscriptionPrestataireService::DISQUE)->exists($justificatif->chemin_fichier);
    }

    /** Flux du fichier depuis le disque privé, avec Content-Type réel et nom de fichier nettoyé. */
    public function telecharger(Justificatif $justificatif): StreamedResponse
    {
        $disque = Storage::disk(InscriptionPrestataireService::DISQUE);
        $type = $disque->mimeType($justificatif->chemin_fichier) ?: 'application/octet-stream';
        $inline = in_array($type, self::TYPES_INLINE, true);

        return $disque->response(
            $justificatif->chemin_fichier,
            $this->nomDeFichier($justificatif, $type),
            ['Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            $inline ? 'inline' : 'attachment',
        );
    }

    /** Nom d'origine nettoyé (sans chemin ni caractère dangereux), ou nom neutre déduit du type. */
    private function nomDeFichier(Justificatif $justificatif, string $type): string
    {
        $extension = self::EXTENSIONS[$type] ?? 'bin';
        $nom = basename(str_replace('\\', '/', (string) $justificatif->nom_original));
        $nom = trim((string) preg_replace('#[\x00-\x1F\x7F"/\\\\;]+#u', '', $nom));

        return $nom !== '' ? $nom : $justificatif->type_document->value.'.'.$extension;
    }
}
