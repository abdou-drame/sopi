<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Enums\StatutJustificatif;
use App\Events\DemandePrestataireAcceptee;
use App\Events\DemandePrestataireRefusee;
use App\Exceptions\DemandeDejaTraitee;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Décision de l'administrateur sur une demande d'inscription (RF19).
 * Le statut du compte vit sur users.statut : c'est lui qui fait foi pour la connexion.
 */
class DecisionDemandeService
{
    /**
     * @throws DemandeDejaTraitee
     * @throws ModelNotFoundException
     */
    public function accepter(string $prestataireId, User $administrateur): Prestataire
    {
        $decide = $this->decider($prestataireId, function (Prestataire $prestataire) use ($administrateur) {
            $prestataire->user->forceFill(['statut' => StatutCompte::Actif])->save();
            $prestataire->forceFill(['motif_refus' => null])->save();
            $this->marquerJustificatifs($prestataire, StatutJustificatif::Valide, $administrateur);
        });
        DemandePrestataireAcceptee::dispatch($decide);

        return $decide;
    }

    /**
     * @throws DemandeDejaTraitee
     * @throws ModelNotFoundException
     */
    public function refuser(string $prestataireId, string $motif, User $administrateur): Prestataire
    {
        $decide = $this->decider($prestataireId, function (Prestataire $prestataire) use ($motif, $administrateur) {
            $prestataire->user->forceFill(['statut' => StatutCompte::Refuse])->save();
            $prestataire->forceFill(['motif_refus' => $motif])->save();
            $this->marquerJustificatifs($prestataire, StatutJustificatif::Rejete, $administrateur);
        });
        DemandePrestataireRefusee::dispatch($decide);

        return $decide;
    }

    /** Transaction avec verrou sur la demande : deux décisions simultanées ne peuvent pas passer. */
    private function decider(string $prestataireId, callable $appliquer): Prestataire
    {
        return DB::transaction(function () use ($prestataireId, $appliquer) {
            $prestataire = Prestataire::whereKey($prestataireId)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($prestataire->user_id)->lockForUpdate()->firstOrFail();
            $prestataire->setRelation('user', $user);

            if ($user->statut !== StatutCompte::EnAttenteValidation) {
                throw new DemandeDejaTraitee;
            }

            $appliquer($prestataire);

            return $prestataire->load(['user', 'justificatifs' => fn ($q) => $q->orderBy('date_soumission')]);
        });
    }

    private function marquerJustificatifs(Prestataire $prestataire, StatutJustificatif $statut, User $administrateur): void
    {
        foreach ($prestataire->justificatifs()->get() as $justificatif) {
            $justificatif->forceFill(['statut' => $statut, 'verifie_par' => $administrateur->id])->save();
        }
    }
}
