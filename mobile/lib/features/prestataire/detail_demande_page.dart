import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/chargement.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/pastille_statut.dart';
import '../reservation/depot_reservation.dart';
import '../reservation/modeles.dart' show StatutReservation;
import '../reservation/presentation_reservation.dart';
import 'actions_demande.dart';
import 'depot_prestataire.dart';
import 'formats_prestataire.dart';
import 'modeles.dart';
import '../../core/api/erreurs.dart';

/// Détail d'une demande (maquette PrestaDetailDemande) : montants, délai de réponse, Accepter / Refuser.
/// Une réservation déjà traitée garde le même écran, sans compte à rebours ni boutons.
class DetailDemandePage extends ConsumerStatefulWidget {
  const DetailDemandePage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<DetailDemandePage> createState() => _DetailDemandePageState();
}

class _DetailDemandePageState extends ConsumerState<DetailDemandePage> {
  bool _enCours = false;

  Future<void> _accepter() async {
    if (_enCours) return;
    setState(() => _enCours = true);
    await accepterDemande(context, ref, widget.id);
    if (mounted) setState(() => _enCours = false);
  }

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.tableau);

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(detailDemandeProvider(widget.id));
    final demande = detail.value;

    Widget corps;
    if (demande != null) {
      corps = _Corps(demande: demande);
    } else if (detail.hasError) {
      corps = SizedBox(
        height: 360,
        child: EtatErreur(
          message: ErreurApi.depuis(detail.error!).message,
          onReessayer: () => ref.invalidate(detailDemandeProvider(widget.id)),
        ),
      );
    } else {
      corps = const _CorpsSquelette();
    }

    final peutRepondre = demande != null && demande.aTraiter;
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                _EnTete(demande: demande, onRetour: _retour),
                corps,
                const SizedBox(height: 24),
              ],
            ),
          ),
          if (peutRepondre)
            Padding(
              padding: EdgeInsets.fromLTRB(
                16,
                8,
                16,
                MediaQuery.paddingOf(context).bottom + 24,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  BoutonPrimaire(
                    libelle: 'Accepter la demande',
                    chargement: _enCours,
                    onPressed: _enCours ? null : _accepter,
                  ),
                  const SizedBox(height: 10),
                  BoutonCompact(
                    libelle: 'Refuser',
                    ton: TonBoutonCompact.contour,
                    hauteur: 50,
                    taille: 15,
                    pleineLargeur: true,
                    onPressed: _enCours
                        ? null
                        : () =>
                              context.push(Chemins.refusReservation(widget.id)),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _EnTete extends StatelessWidget {
  const _EnTete({required this.demande, required this.onRetour});

  final DemandeReservation? demande;
  final VoidCallback onRetour;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final d = demande;
    return ClipRRect(
      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(36)),
      child: FondDegrade(
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            MediaQuery.paddingOf(context).top + 12,
            16,
            28,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  BoutonRetour(onPressed: onRetour),
                  Expanded(
                    child: Text(
                      'Demande',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(width: 64),
                ],
              ),
              const SizedBox(height: 22),
              if (d != null) ...[
                PastilleStatut(
                  libelle: d.aTraiter
                      ? 'En attente de votre réponse'
                      : d.statut == StatutReservation.acceptee
                      ? 'Acceptée, en attente du paiement'
                      : d.statut.libelle,
                  ton: tonDuStatut(d.statut),
                ),
                const SizedBox(height: 10),
                Text(
                  d.clientAffiche,
                  style: theme.textTheme.headlineMedium?.copyWith(
                    fontSize: 28,
                    height: 34 / 28,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${d.prestationNom} · ${formaterDate(d.debut)}',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 14,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
              ] else
                const Padding(
                  padding: EdgeInsets.only(top: 6),
                  child: Squelette(largeur: 200, hauteur: 28),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CorpsSquelette extends StatelessWidget {
  const _CorpsSquelette();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 18, 16, 0),
    child: Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: const Column(
        children: [
          Squelette(largeur: double.infinity, hauteur: 16),
          SizedBox(height: 14),
          Squelette(largeur: double.infinity, hauteur: 16),
          SizedBox(height: 14),
          Squelette(largeur: double.infinity, hauteur: 16),
        ],
      ),
    ),
  );
}

class _Corps extends ConsumerWidget {
  const _Corps({required this.demande});

  final DemandeReservation demande;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final d = demande;
    final theme = Theme.of(context);
    final echeance = d.reponseAvant;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (d.montantsConnus) _CarteMontants(demande: d),
          if (d.motifRefus != null && d.motifRefus!.isNotEmpty) ...[
            const SizedBox(height: 14),
            Text(
              'Motif du refus',
              style: theme.textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              d.motifRefus!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: CouleursSopi.texteSecondaire,
              ),
            ),
          ],
          if (d.aTraiter) ...[
            if (echeance != null) ...[
              const SizedBox(height: 14),
              _BandeauDelai(echeance: echeance),
            ],
            const SizedBox(height: 14),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Text(
                "Le client dispose d'un délai pour payer l'acompte. La réservation est confirmée à réception du paiement.",
                style: theme.textTheme.bodySmall?.copyWith(
                  fontSize: 12,
                  height: 17 / 12,
                  color: CouleursSopi.texteDiscret,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CarteMontants extends StatelessWidget {
  const _CarteMontants({required this.demande});

  final DemandeReservation demande;

  @override
  Widget build(BuildContext context) {
    final d = demande;
    Widget ligne(
      String libelle,
      String valeur, {
      Color? couleur,
      FontWeight poids = FontWeight.w700,
    }) {
      final theme = Theme.of(context);
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              libelle,
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 14,
                color: CouleursSopi.texteDiscret,
                fontWeight: poids == FontWeight.w800
                    ? FontWeight.w700
                    : FontWeight.w500,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Text(
            valeur,
            textAlign: TextAlign.right,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              fontWeight: poids,
              color: couleur ?? CouleursSopi.marine,
            ),
          ),
        ],
      );
    }

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: Column(
        children: [
          ligne('Tarif du service', formaterArgent(d.prix)),
          const SizedBox(height: 10),
          ligne(
            'Acompte demandé au client',
            formaterArgent(d.acompte!),
            couleur: CouleursSopi.bleu,
          ),
          const SizedBox(height: 10),
          // Reste à payer au prestataire en main propre : calculé ici pour l'affichage seulement.
          ligne(
            'Reste à encaisser sur place',
            formaterArgent(d.prix - d.acompte!),
          ),
          const SizedBox(height: 10),
          Container(height: 1, color: CouleursSopi.bordure),
          const SizedBox(height: 10),
          ligne('Commission Sopi', '− ${formaterArgent(d.commission!)}'),
          const SizedBox(height: 10),
          ligne(
            'Crédité au portefeuille à la fin',
            formaterArgent(d.montantCredite!),
            poids: FontWeight.w800,
          ),
        ],
      ),
    );
  }
}

/// Bandeau ambre : temps restant pour répondre, relu toutes les 30 secondes.
class _BandeauDelai extends ConsumerStatefulWidget {
  const _BandeauDelai({required this.echeance});

  final DateTime echeance;

  @override
  ConsumerState<_BandeauDelai> createState() => _BandeauDelaiState();
}

class _BandeauDelaiState extends ConsumerState<_BandeauDelai> {
  late final Stream<int> _tic = Stream.periodic(
    const Duration(seconds: 30),
    (i) => i,
  );

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<int>(
      stream: _tic,
      builder: (context, _) {
        final maintenant = ref.read(horlogeProvider)();
        return Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            color: CouleursSopi.ambrePastel,
            borderRadius: BorderRadius.circular(22),
          ),
          child: Text(
            formaterResteVous(widget.echeance.difference(maintenant)),
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontSize: 13,
              height: 19 / 13,
              fontWeight: FontWeight.w600,
              color: CouleursSopi.ambreProfond,
            ),
          ),
        );
      },
    );
  }
}
