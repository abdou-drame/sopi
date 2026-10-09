<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Http\Requests\Auth\InscriptionRequest;
use App\Models\User;
use App\Support\TelephoneSenegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crée le premier administrateur à partir des variables d'environnement ADMIN_*.
 * Jamais lancée automatiquement (ni par le seeder, ni au déploiement). Aucune valeur n'est affichée.
 */
class CreerAdmin extends Command
{
    protected $signature = 'sopi:creer-admin';

    protected $description = "Crée le compte administrateur à partir des variables ADMIN_NOM, ADMIN_PRENOM, ADMIN_TELEPHONE, ADMIN_EMAIL (optionnel) et ADMIN_PASSWORD";

    /** Champ du compte => nom de la variable d'environnement. */
    private const VARIABLES = [
        'nom' => 'ADMIN_NOM',
        'prenom' => 'ADMIN_PRENOM',
        'telephone' => 'ADMIN_TELEPHONE',
        'email' => 'ADMIN_EMAIL',
        'password' => 'ADMIN_PASSWORD',
    ];

    private const OBLIGATOIRES = ['nom', 'prenom', 'telephone', 'password'];

    public function handle(): int
    {
        $valeurs = [];
        foreach (self::VARIABLES as $champ => $variable) {
            $valeur = config("sopi.admin.$champ");
            $valeurs[$champ] = is_string($valeur) && trim($valeur) !== '' ? trim($valeur) : null;
        }

        $manquantes = [];
        foreach (self::OBLIGATOIRES as $champ) {
            if ($valeurs[$champ] === null) {
                $manquantes[] = self::VARIABLES[$champ];
            }
        }
        if ($manquantes) {
            $this->error('Variable(s) obligatoire(s) manquante(s) : '.implode(', ', $manquantes).'. Aucun compte créé.');

            return self::FAILURE;
        }

        $valeurs['telephone'] = TelephoneSenegal::normaliser($valeurs['telephone']) ?? $valeurs['telephone'];
        if ($valeurs['email'] !== null) {
            $valeurs['email'] = mb_strtolower($valeurs['email']);
        }

        // Idempotence : un compte existe déjà avec ce téléphone.
        $existant = User::where('telephone', $valeurs['telephone'])->first();
        if ($existant) {
            if ($existant->role === Role::Administrateur) {
                $this->info("Un administrateur existe déjà avec ce téléphone. Rien n'a été créé ni modifié.");

                return self::SUCCESS;
            }

            $this->error("Ce téléphone appartient déjà à un compte qui n'est pas administrateur. Aucun compte créé ni modifié.");

            return self::FAILURE;
        }

        // Mêmes règles que l'inscription (téléphone sénégalais, mot de passe de 8 caractères minimum, e-mail unique).
        $demande = new InscriptionRequest;
        $validateur = Validator::make($valeurs, $demande->rules(), $demande->messages());
        if ($validateur->fails()) {
            $this->error('Configuration invalide. Aucun compte créé :');
            foreach ($validateur->errors()->messages() as $champ => $messages) {
                $this->line('  - '.self::VARIABLES[$champ].' : '.implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $admin = new User($validateur->validated());
        $admin->role = Role::Administrateur;
        $admin->statut = StatutCompte::Actif;
        $admin->save();

        $this->info('Administrateur créé.');

        return self::SUCCESS;
    }
}
