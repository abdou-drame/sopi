<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

class AjouterPhotoRequest extends FormRequest
{
    /** Taille maximale d'une photo, en Ko (512 Ko ; l'application compresse avant l'envoi). */
    public const TAILLE_MAX_KO = 512;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // « mimetypes » lit le contenu réel du fichier, pas son extension.
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::TAILLE_MAX_KO],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Veuillez joindre une photo.',
            'photo.file' => 'Veuillez joindre une photo valide.',
            'photo.uploaded' => 'L\'envoi de la photo a échoué (fichier trop volumineux ou interrompu).',
            'photo.mimetypes' => 'La photo doit être une image JPG, PNG ou WebP.',
            'photo.max' => 'La photo ne doit pas dépasser 512 Ko.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'photo' => ['description' => 'Image JPG, PNG ou WebP (type vérifié sur le contenu), 512 Ko maximum. 6 photos au plus par prestataire.', 'example' => 'resources/exemples-docs/photo.png'],
        ];
    }
}
