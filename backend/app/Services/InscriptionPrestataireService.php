<?php

namespace App\Services;

use App\Enums\Canal;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\TypeNotification;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Inscription d'un prestataire avec son dossier de justificatifs (RF06).
 * Tout est enregistré en une transaction ; si une étape échoue, la base est annulée
 * et les fichiers déjà écrits sont supprimés.
 */
class InscriptionPrestataireService
{
    /** Disque PRIVÉ de Laravel : les justificatifs ne sont jamais accessibles publiquement. */
    public const DISQUE = 'local';

    /** Extension déduite du contenu réel (jamais du nom d'origine). */
    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * @param  array<string, mixed>  $donnees  Données validées (voir InscriptionPrestataireRequest).
     * @return array{prestataire: Prestataire, jeton_suivi: string} Le jeton en clair n'est renvoyé qu'ici, une seule fois.
     */
    public function inscrire(array $donnees): array
    {
        $disque = Storage::disk(self::DISQUE);
        $dossier = null;

        try {
            $resultat = DB::transaction(function () use ($donnees, &$dossier) {
                $user = new User([
                    'nom' => $donnees['nom'],
                    'prenom' => $donnees['prenom'],
                    'telephone' => $donnees['telephone'],
                    'email' => $donnees['email'],
                    'password' => $donnees['mot_de_passe'],
                ]);
                $user->role = Role::Prestataire;
                $user->statut = StatutCompte::EnAttenteValidation;
                $user->save();

                // Jeton de suivi : seul son hachage est enregistré (SHA-256, adapté à un jeton aléatoire
                // à forte entropie et qui permet de le retrouver par son hachage).
                $jeton = Str::random(48);

                $prestataire = new Prestataire([
                    'metier' => $donnees['metier'],
                    'quartier' => $donnees['quartier'],
                    'nom_entreprise' => $donnees['nom_entreprise'],
                    'adresse' => $donnees['adresse'],
                ]);
                $prestataire->user_id = $user->id;
                $prestataire->jeton_suivi_hash = hash('sha256', $jeton);
                $prestataire->save();

                $dossier = 'justificatifs/'.$prestataire->id;
                foreach ($donnees['justificatifs'] as $piece) {
                    $justificatif = new Justificatif([
                        'type_document' => $piece['type_document'],
                        'chemin_fichier' => $this->enregistrerFichier($piece['fichier'], $dossier),
                        'date_soumission' => now(),
                        'nom_original' => $this->nettoyerNom($piece['fichier']->getClientOriginalName()),
                        'type_mime' => $piece['fichier']->getMimeType(),
                        'taille' => $piece['fichier']->getSize(),
                    ]);
                    $justificatif->prestataire_id = $prestataire->id;
                    $justificatif->save();
                }

                return [
                    'prestataire' => $prestataire->load(['user', 'justificatifs']),
                    'jeton_suivi' => $jeton,
                ];
            });

            $this->notifierDepot($resultat['prestataire'], $resultat['jeton_suivi']);

            return $resultat;
        } catch (Throwable $erreur) {
            // La base est annulée : on supprime aussi les fichiers déjà écrits.
            if ($dossier !== null) {
                $disque->deleteDirectory($dossier);
            }

            throw $erreur;
        }
    }

    /**
     * E-mail de confirmation du dépôt avec le lien de suivi (RG06). Lancé une fois le dossier enregistré ; une panne de la
     * notification ne doit jamais faire échouer l'inscription (elle est reprise par la commande planifiée). Le lien (donc le
     * jeton en clair) n'est transmis qu'en donnée chiffrée, effacée après l'envoi.
     */
    private function notifierDepot(Prestataire $prestataire, string $jeton): void
    {
        try {
            app(NotificationService::class)->creer(
                $prestataire->user,
                TypeNotification::DossierDepose,
                'Dossier bien reçu',
                'Nous avons bien reçu le dossier de « '.$prestataire->nom_entreprise.' ». Il est en cours d\'examen par l\'équipe Sopi. Le lien personnel de cet e-mail vous permet de suivre la validation de votre compte.',
                ['prestataireId' => $prestataire->id],
                [Canal::Email],
                SuiviDossierService::lien($jeton),
            );
        } catch (Throwable $erreur) {
            Log::warning('Notification de dépôt de dossier non créée.', ['erreur' => $erreur::class]);
        }
    }

    /** Nom d'origine conservé pour affichage seulement : sans chemin ni caractère de contrôle. */
    private function nettoyerNom(string $nom): string
    {
        $nom = basename(str_replace('\\', '/', $nom));
        $nom = preg_replace('#[\x00-\x1F\x7F"/\\\\]+#u', '', $nom);

        return mb_substr(trim((string) $nom), 0, 150) ?: 'document';
    }

    /** Écrit le fichier sous un nom aléatoire (UUID + extension tirée du contenu) et renvoie son chemin. */
    private function enregistrerFichier(UploadedFile $fichier, string $dossier): string
    {
        $extension = self::EXTENSIONS[$fichier->getMimeType()] ?? 'bin';
        $nom = Str::uuid().'.'.$extension;

        Storage::disk(self::DISQUE)->putFileAs($dossier, $fichier, $nom);

        return $dossier.'/'.$nom;
    }
}
