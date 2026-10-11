<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
     * @return array{prestataire: Prestataire, jeton_suivi: string}  Le jeton en clair n'est renvoyé qu'ici, une seule fois.
     */
    public function inscrire(array $donnees): array
    {
        $disque = Storage::disk(self::DISQUE);
        $dossier = null;

        try {
            return DB::transaction(function () use ($donnees, $disque, &$dossier) {
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
                    ]);
                    $justificatif->prestataire_id = $prestataire->id;
                    $justificatif->save();
                }

                return [
                    'prestataire' => $prestataire->load(['user', 'justificatifs']),
                    'jeton_suivi' => $jeton,
                ];
            });
        } catch (Throwable $erreur) {
            // La base est annulée : on supprime aussi les fichiers déjà écrits.
            if ($dossier !== null) {
                $disque->deleteDirectory($dossier);
            }

            throw $erreur;
        }
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
