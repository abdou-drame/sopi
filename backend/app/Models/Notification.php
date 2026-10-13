<?php

namespace App\Models;

use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification adressée à un utilisateur (diagramme de classes) : message affiché dans l'application, et tampon d'envoi pour
 * l'e-mail et le push. Créée uniquement par NotificationService (aucune affectation de masse).
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'notifications';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => TypeNotification::class,
            'donnees' => 'array',
            'lue_le' => 'datetime',
            'canal_email_statut' => StatutEnvoi::class,
            'canal_push_statut' => StatutEnvoi::class,
            'prochain_essai_le' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
