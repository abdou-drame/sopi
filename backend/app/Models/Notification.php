<?php

namespace App\Models;

use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use Database\Factories\NotificationFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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

    /** Jamais sérialisé : le secret chiffré ne quitte pas le serveur. */
    protected $hidden = ['secret_chiffre'];

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

    /** Enregistre l'information secrète chiffrée (Crypt) ; null l'efface. Ne sauvegarde pas. */
    public function definirSecret(?string $secret): void
    {
        $this->secret_chiffre = $secret === null ? null : Crypt::encryptString($secret);
    }

    /** Secret déchiffré, ou null s'il est absent, effacé ou illisible. */
    public function secret(): ?string
    {
        if (blank($this->secret_chiffre)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->secret_chiffre);
        } catch (DecryptException) {
            return null;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
