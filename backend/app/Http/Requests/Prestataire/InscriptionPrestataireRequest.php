<?php

namespace App\Http\Requests\Prestataire;

use App\Enums\Metier;
use App\Enums\TypeDocument;
use App\Support\TelephoneSenegal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InscriptionPrestataireRequest extends FormRequest
{
    /** Taille maximale d'un justificatif, en Ko (5 Mo). */
    public const TAILLE_MAX_KO = 5120;

    public const NB_MAX_JUSTIFICATIFS = 5;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            // Un numéro invalide reste tel quel pour que la règle « regex » le rejette.
            'telephone' => TelephoneSenegal::normaliser($this->input('telephone')) ?? $this->input('telephone'),
            'email' => is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'regex:/^\+2217[05678]\d{7}$/', 'unique:users,telephone'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'nom_entreprise' => ['required', 'string', 'max:150'],
            'metier' => ['required', 'string', Rule::enum(Metier::class)],
            'quartier' => ['required', 'string', 'max:100'],
            'adresse' => ['required', 'string', 'max:255'],
            // Une CNI et un justificatif professionnel au minimum (contrôlé plus bas), 5 fichiers au maximum.
            'justificatifs' => ['required', 'array', 'min:2', 'max:'.self::NB_MAX_JUSTIFICATIFS],
            'justificatifs.*.type_document' => ['required', 'string', Rule::enum(TypeDocument::class)],
            // « mimetypes » lit le contenu réel du fichier, pas son extension.
            'justificatifs.*.fichier' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.self::TAILLE_MAX_KO],
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le numéro de téléphone doit être un mobile sénégalais valide (ex. : 77 123 45 67).',
            'metier.*' => 'Le métier choisi est invalide (mecanique, coiffure ou pressing).',
            'justificatifs.required' => 'Veuillez joindre vos justificatifs.',
            'justificatifs.min' => 'Le dossier doit contenir au moins une CNI et un justificatif professionnel (diplôme ou attestation).',
            'justificatifs.max' => 'Vous pouvez joindre 5 fichiers au maximum.',
            'justificatifs.*.type_document.*' => 'Le type de document est invalide (cni, diplome ou attestation).',
            'justificatifs.*.fichier.required' => 'Veuillez joindre un fichier PDF ou image valide',
            'justificatifs.*.fichier.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }

    /** Dossier complet : au moins une CNI ET au moins un justificatif professionnel (diplôme ou attestation). */
    public function after(): array
    {
        return [function (Validator $validateur) {
            $justificatifs = $this->input('justificatifs');
            if ($validateur->errors()->has('justificatifs') || ! is_array($justificatifs)) {
                return;
            }

            $types = collect($justificatifs)->map(fn ($j) => is_array($j) ? ($j['type_document'] ?? null) : null);

            if (! $types->contains(TypeDocument::Cni->value)) {
                $validateur->errors()->add('justificatifs', 'Le dossier doit contenir une copie de votre CNI.');
            }
            if (! $types->contains(TypeDocument::Diplome->value) && ! $types->contains(TypeDocument::Attestation->value)) {
                $validateur->errors()->add('justificatifs', 'Le dossier doit contenir au moins un justificatif professionnel (diplôme ou attestation).');
            }
        }];
    }

    /** Description des champs pour la documentation (Scribe). */
    public function bodyParameters(): array
    {
        return [
            'nom' => ['description' => 'Nom.', 'example' => 'Diop'],
            'prenom' => ['description' => 'Prénom.', 'example' => 'Moussa'],
            'telephone' => ['description' => 'Mobile sénégalais (avec ou sans +221), unique.', 'example' => '77 123 45 67'],
            'email' => ['description' => 'Adresse e-mail, unique.', 'example' => 'moussa@example.com'],
            'mot_de_passe' => ['description' => '8 caractères minimum.', 'example' => 'mot-de-passe-exemple'],
            'mot_de_passe_confirmation' => ['description' => 'Identique à mot_de_passe.', 'example' => 'mot-de-passe-exemple'],
            'nom_entreprise' => ['description' => "Nom de l'entreprise ou de l'atelier.", 'example' => 'Garage Moussa'],
            'metier' => ['description' => 'mecanique, coiffure ou pressing.', 'example' => 'mecanique'],
            'quartier' => ['description' => 'Quartier de Keur Massar.', 'example' => 'Malika'],
            'adresse' => ['description' => 'Adresse.', 'example' => 'Rue 10, près du marché'],
            'justificatifs' => ['description' => 'De 2 à 5 pièces : au moins une CNI et un diplôme ou une attestation.'],
            'justificatifs.*.type_document' => ['description' => 'cni, diplome ou attestation.', 'example' => 'cni'],
            'justificatifs.*.fichier' => ['description' => 'PDF, JPG ou PNG, 5 Mo maximum.'],
        ];
    }
}
