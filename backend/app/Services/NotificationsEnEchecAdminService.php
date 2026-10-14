<?php

namespace App\Services;

use App\Enums\StatutEnvoi;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Notifications en échec pour l'administrateur (M12.3) : lecture seule. « En échec » a la même définition que l'alerte
 * `notifications_en_echec` : au moins un canal à l'état `echoue`. Le titre, le corps, les données, l'adresse e-mail et le jeton
 * push ne sont jamais lus : seul le destinataire est identifié (id et rôle).
 */
class NotificationsEnEchecAdminService
{
    public const PAR_PAGE = 20;

    private const COLONNES = ['id', 'user_id', 'type', 'canal_email_statut', 'canal_push_statut', 'tentatives', 'dernier_echec_classe', 'created_at'];

    /**
     * @param  array{canal?: ?string, type?: ?string, ancienneteMinutes?: ?int}  $filtres
     */
    public function lister(array $filtres): LengthAwarePaginator
    {
        $requete = $this->enEchec();

        $canal = $filtres['canal'] ?? null;
        if ($canal === 'email') {
            $requete->where('canal_email_statut', StatutEnvoi::Echoue->value);
        } elseif ($canal === 'push') {
            $requete->where('canal_push_statut', StatutEnvoi::Echoue->value);
        }
        if (filled($filtres['type'] ?? null)) {
            $requete->where('type', $filtres['type']);
        }
        if (filled($filtres['ancienneteMinutes'] ?? null)) {
            $requete->where('created_at', '<=', now()->subMinutes((int) $filtres['ancienneteMinutes']));
        }

        return $requete->orderByDesc('created_at')->orderByDesc('id')->paginate(self::PAR_PAGE);
    }

    public function trouver(string $id): ?Notification
    {
        return Str::isUuid($id) ? $this->enEchec()->find($id) : null;
    }

    private function enEchec(): Builder
    {
        return Notification::query()
            ->select(self::COLONNES)
            ->with('user:id,role')
            ->where(function (Builder $q) {
                $q->where('canal_email_statut', StatutEnvoi::Echoue->value)->orWhere('canal_push_statut', StatutEnvoi::Echoue->value);
            });
    }
}
