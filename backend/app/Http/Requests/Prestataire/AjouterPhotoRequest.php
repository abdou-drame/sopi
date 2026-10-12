<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

class AjouterPhotoRequest extends FormRequest
{
    /** Taille maximale d'une photo, en Ko (5 Mo). */
    public const TAILLE_MAX_KO = 5120;

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
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'photo' => ['description' => 'Image JPG, PNG ou WebP (type vérifié sur le contenu), 5 Mo maximum. 6 photos au plus par prestataire.'],
        ];
    }
}
