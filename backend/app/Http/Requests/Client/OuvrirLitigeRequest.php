<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class OuvrirLitigeRequest extends FormRequest
{
    public const MOTIF_MIN = 3;

    public const MOTIF_MAX = 150;

    public const DESCRIPTION_MIN = 10;

    public const DESCRIPTION_MAX = 1000;

    public const PREUVES_MAX = 3;

    /** Taille maximale d'une preuve, en Ko (5 Mo). */
    public const TAILLE_MAX_KO = 5120;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:'.self::MOTIF_MIN, 'max:'.self::MOTIF_MAX],
            'description' => ['required', 'string', 'min:'.self::DESCRIPTION_MIN, 'max:'.self::DESCRIPTION_MAX],
            'preuves' => ['nullable', 'array', 'max:'.self::PREUVES_MAX],
            // « mimetypes » lit le contenu réel du fichier, pas son extension.
            'preuves.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::TAILLE_MAX_KO],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du litige est obligatoire.',
            'motif.string' => 'Le motif doit être un texte.',
            'motif.min' => 'Le motif doit contenir au moins '.self::MOTIF_MIN.' caractères.',
            'motif.max' => 'Le motif ne peut pas dépasser '.self::MOTIF_MAX.' caractères.',
            'description.required' => 'La description du litige est obligatoire.',
            'description.string' => 'La description doit être un texte.',
            'description.min' => 'La description doit contenir au moins '.self::DESCRIPTION_MIN.' caractères.',
            'description.max' => 'La description ne peut pas dépasser '.self::DESCRIPTION_MAX.' caractères.',
            'preuves.array' => 'Les preuves doivent être envoyées sous forme de liste de photos.',
            'preuves.max' => 'Vous pouvez joindre '.self::PREUVES_MAX.' photos au plus.',
            'preuves.*.file' => 'Chaque preuve doit être un fichier valide.',
            'preuves.*.uploaded' => 'L\'envoi d\'une preuve a échoué (fichier trop volumineux ou interrompu).',
            'preuves.*.mimetypes' => 'Chaque preuve doit être une image JPG, PNG ou WebP.',
            'preuves.*.max' => 'Chaque preuve ne doit pas dépasser 5 Mo.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'motif' => ['description' => 'Raison du litige en quelques mots, de '.self::MOTIF_MIN.' à '.self::MOTIF_MAX.' caractères.', 'example' => 'Prestataire absent'],
            'description' => ['description' => 'Description du problème, de '.self::DESCRIPTION_MIN.' à '.self::DESCRIPTION_MAX.' caractères.', 'example' => 'Le prestataire ne s\'est pas présenté au rendez-vous.'],
            'preuves' => ['description' => 'Facultatif : '.self::PREUVES_MAX.' photos JPG, PNG ou WebP au plus (5 Mo chacune, type vérifié sur le contenu), en multipart.', 'required' => false],
        ];
    }

    public function motif(): string
    {
        return trim($this->validated('motif'));
    }

    public function description(): string
    {
        return trim($this->validated('description'));
    }

    /** @return list<UploadedFile> */
    public function preuves(): array
    {
        return array_values($this->file('preuves') ?? []);
    }
}
