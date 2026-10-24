<?php

namespace App\Http\Requests\Prestataire;

class ApercuIndisponibiliteRequest extends DeclarerIndisponibiliteRequest
{
    public function rules(): array
    {
        return [
            'debut' => ['required', 'date_format:Y-m-d H:i'],
            'fin' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'debut' => ['description' => 'Début (AAAA-MM-JJ HH:MM, fuseau Africa/Dakar).', 'example' => '2030-01-15 08:00'],
            'fin' => ['description' => 'Fin (AAAA-MM-JJ HH:MM), après le début et dans le futur. Plage de '.\App\Services\IndisponibiliteService::DUREE_MAX_JOURS.' jours au maximum.', 'example' => '2030-01-20 18:00'],
        ];
    }
}
