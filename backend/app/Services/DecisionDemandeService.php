<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Enums\StatutJustificatif;
use App\Events\DemandePrestataireAcceptee;
use App\Events\DemandePrestataireRefusee;
use App\Exceptions\DemandeDejaTraitee;
use App\Exceptions\JustificatifHorsDossier;
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
     * Refuse le dossier. `$motif` est le motif global (facultatif si des documents sont refusés) ; `$motifsParDocument` associe
     * l'identifiant d'un justificatif à son motif de refus (mémoire, tableau 2.8) : les autres documents sont alors validés.
     * Sans liste de documents, tous les documents sont rejetés comme avant.
     *
     * @param  array<string, string>  $motifsParDocument
     *
     * @throws DemandeDejaTraitee
     * @throws JustificatifHorsDossier
     * @throws ModelNotFoundException
     */
    public function refuser(string $prestataireId, ?string $motif, User $administrateur, array $motifsParDocument = []): Prestataire
    {
        $decide = $this->decider($prestataireId, function (Prestataire $prestataire) use ($motif, $administrateur, $motifsParDocument) {
            $justificatifs = $prestataire->justificatifs()->get();
            if (array_diff(array_keys($motifsParDocument), $justificatifs->pluck('id')->all()) !== []) {
                throw new JustificatifHorsDossier;
            }

            $prestataire->user->forceFill(['statut' => StatutCompte::Refuse])->save();
            $prestataire->forceFill(['motif_refus' => $motif])->save();

            if ($motifsParDocument === []) {
                $this->marquerJustificatifs($prestataire, StatutJustificatif::Rejete, $administrateur);

                return;
            }
            foreach ($justificatifs as $justificatif) {
                $refuse = array_key_exists($justificatif->id, $motifsParDocument);
                $justificatif->forceFill([
                    'statut' => $refuse ? StatutJustificatif::Rejete : StatutJustificatif::Valide,
                    'motif_rejet' => $refuse ? $motifsParDocument[$justificatif->id] : null,
                    'verifie_par' => $administrateur->id,
                ])->save();
            }
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
