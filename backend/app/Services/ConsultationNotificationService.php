<?php

namespace App\Services;

use App\Exceptions\ReservationImpossibleException;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notifications d'un utilisateur dans l'application (M10.3). Toutes les requêtes sont filtrées sur l'utilisateur connecté :
 * la notification d'un autre répond comme une notification inconnue (404). Le marquage « lue » est une mise à jour conditionnelle.
 */
class ConsultationNotificationService
{
    public const PAR_PAGE = 20;

    public function lister(User $utilisateur, bool $nonLuesSeulement): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', $utilisateur->id)
            ->when($nonLuesSeulement, fn ($q) => $q->whereNull('lue_le'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE);
    }

    public function compterNonLues(User $utilisateur): int
    {
        return Notification::query()->where('user_id', $utilisateur->id)->whereNull('lue_le')->count();
    }

    /**
     * Marque une notification comme lue. Sans effet si elle l'est déjà : la date de première lecture est conservée.
     *
     * @throws ReservationImpossibleException 404 (inconnue ou à un autre utilisateur)
     */
    public function marquerLue(User $utilisateur, string $id): Notification
    {
        $notification = Str::isUuid($id)
            ? Notification::query()->where('user_id', $utilisateur->id)->find($id)
            : null;

        if ($notification === null) {
            throw new ReservationImpossibleException('Notification introuvable.', Response::HTTP_NOT_FOUND);
        }

        Notification::query()
            ->whereKey($notification->id)
            ->where('user_id', $utilisateur->id)
            ->whereNull('lue_le')
            ->update(['lue_le' => now()]);

        return $notification->refresh();
    }

    /** Marque toutes les notifications non lues de l'utilisateur comme lues. Retourne leur nombre. */
    public function marquerToutesLues(User $utilisateur): int
    {
        return Notification::query()->where('user_id', $utilisateur->id)->whereNull('lue_le')->update(['lue_le' => now()]);
    }
}
