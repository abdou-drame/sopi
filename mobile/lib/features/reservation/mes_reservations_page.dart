import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/pastille_statut.dart';
import 'annulation_reservation.dart';
import 'depot_reservation.dart';
import 'modeles.dart';
import 'presentation_reservation.dart';
import 'reservations_controleur.dart';
import 'widgets/composants.dart';

/// Onglet « Réservations » du client (maquette Reservations) : trois groupes, pagination infinie.
class MesReservationsPage extends ConsumerStatefulWidget {
  const MesReservationsPage({super.key});

  @override
  ConsumerState<MesReservationsPage> createState() =>
      _MesReservationsPageState();
}

class _MesReservationsPageState extends ConsumerState<MesReservationsPage> {
  GroupeReservations _groupe = GroupeReservations.aVenir;

  ReservationsControleur get _controleur =>
      ref.read(reservationsProvider(_groupe).notifier);

  @override
  Widget build(BuildContext context) {
    final etat = ref.watch(reservationsProvider(_groupe));

    Widget corps;
    if (etat.chargement) {
      corps = const _CorpsSquelette();
    } else if (etat.erreur != null) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(
          message: etat.erreur!,
          onReessayer: () => _controleur.charger(),
        ),
      );
    } else if (etat.elements.isEmpty) {
      corps = const SizedBox(
        height: 320,
        child: EtatVide(
          message: 'Aucune réservation ici.',
          icone: Icons.event_busy_rounded,
        ),
      );
    } else {
      corps = _CorpsListe(
        etat: etat,
        onSuite: () => _controleur.chargerSuite(),
      );
    }

    return Scaffold(
      body: NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n.metrics.axis == Axis.vertical &&
              n.metrics.extentAfter < 400 &&
              etat.encore) {
            _controleur.chargerSuite();
          }
          return false;
        },
        child: RefreshIndicator(
          onRefresh: () => _controleur.charger(),
          edgeOffset: MediaQuery.paddingOf(context).top,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: EnTeteDegrade(
                  titreLigne1: 'Mes',
                  titreLigne2: 'réservations',
                  avecRetour: false,
                  enfant: _OngletsGroupes(
                    actif: _groupe,
                    onChange: (g) => setState(() => _groupe = g),
                  ),
                ),
              ),
              SliverToBoxAdapter(child: corps),
              SliverToBoxAdapter(
                child: SizedBox(height: margeBarreOnglets(context)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Sélecteur À venir / Terminées / Annulées : pilule marine de 48 px, onglet actif en bleu.
class _OngletsGroupes extends StatelessWidget {
  const _OngletsGroupes({required this.actif, required this.onChange});

  final GroupeReservations actif;
  final ValueChanged<GroupeReservations> onChange;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      height: 48,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: CouleursSopi.marine,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        children: [
          for (final g in GroupeReservations.values) ...[
            if (g != GroupeReservations.aVenir) const SizedBox(width: 4),
            Expanded(
              child: Semantics(
                button: true,
                selected: g == actif,
                label: g.libelle,
                excludeSemantics: true,
                child: Material(
                  color: g == actif ? CouleursSopi.bleu : Colors.transparent,
                  borderRadius: BorderRadius.circular(20),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(20),
                    onTap: () => onChange(g),
                    child: Center(
                      child: Text(
                        g.libelle,
                        style: theme.textTheme.labelLarge?.copyWith(
                          fontSize: 13,
                          fontWeight: g == actif
                              ? FontWeight.w700
                              : FontWeight.w600,
                          color: g == actif
                              ? CouleursSopi.blanc
                              : CouleursSopi.texteSurMarine,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CorpsSquelette extends StatelessWidget {
  const _CorpsSquelette();

  @override
  Widget build(BuildContext context) {
    Widget carte() => Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Squelette(largeur: 150, hauteur: 16),
          SizedBox(height: 8),
          Squelette(largeur: 200, hauteur: 12),
          SizedBox(height: 18),
          Squelette(largeur: 120, hauteur: 12),
        ],
      ),
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
      child: Column(children: [carte(), const SizedBox(height: 12), carte()]),
    );
  }
}

class _CorpsListe extends StatelessWidget {
  const _CorpsListe({required this.etat, required this.onSuite});

  final EtatReservations etat;
  final VoidCallback onSuite;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
      child: Column(
        children: [
          for (var i = 0; i < etat.elements.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Apparition.indexee(
                index: i,
                child: CarteReservation(reservation: etat.elements[i]),
              ),
            ),
          if (etat.chargementSuite)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(strokeWidth: 3),
              ),
            ),
          if (etat.erreurSuite != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                children: [
                  Text(
                    etat.erreurSuite!,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 8),
                  BoutonCompact(
                    libelle: 'Réessayer',
                    ton: TonBoutonCompact.clair,
                    onPressed: onSuite,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Carte d'une réservation : prestataire, statut, prestation et date, puis ce que le statut permet de faire.
class CarteReservation extends ConsumerWidget {
  const CarteReservation({super.key, required this.reservation});

  final ReservationClient reservation;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r = reservation;
    final theme = Theme.of(context);

    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(26),
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(26),
        onTap: () => context.push(Chemins.detailReservation(r.id)),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      r.prestataireNom,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  PastilleStatut(
                    libelle: r.statut.libelle,
                    ton: tonDuStatut(r.statut),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${r.prestationNom} · ${formaterDate(r.debut)}',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 13,
                  color: CouleursSopi.texteDiscret,
                ),
              ),
              _PiedCarte(reservation: r),
            ],
          ),
        ),
      ),
    );
  }
}

class _PiedCarte extends ConsumerWidget {
  const _PiedCarte({required this.reservation});

  final ReservationClient reservation;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r = reservation;
    final theme = Theme.of(context);
    final texte = theme.textTheme.bodyMedium?.copyWith(
      fontSize: 13,
      color: CouleursSopi.texteSecondaire,
    );
    final gras = texte?.copyWith(
      fontWeight: FontWeight.w700,
      color: CouleursSopi.marine,
    );

    Widget ligne(Widget gauche, Widget? action) => Padding(
      padding: const EdgeInsets.only(top: 14),
      child: Row(
        children: [
          Expanded(child: gauche),
          if (action != null) ...[const SizedBox(width: 12), action],
        ],
      ),
    );

    switch (r.statut) {
      case StatutReservation.confirmee:
        return ligne(
          Text.rich(
            TextSpan(
              style: texte,
              children: [
                const TextSpan(text: 'Acompte payé '),
                TextSpan(
                  text: formaterArgent(r.paiement?.montantAcompte ?? 0),
                  style: gras,
                ),
              ],
            ),
          ),
          r.peutAnnuler
              ? BoutonCompact(
                  libelle: 'Annuler',
                  ton: TonBoutonCompact.contour,
                  hauteur: 38,
                  margeHorizontale: 16,
                  onPressed: () => ouvrirAnnulation(context, ref, r),
                )
              : null,
        );
      case StatutReservation.acceptee:
        final connu = acompteConnu(r);
        final montant =
            connu ??
            ref
                .watch(
                  acomptePrestationProvider((
                    prestataireId: r.prestataireId,
                    prestationId: r.prestationId,
                  )),
                )
                .value
                ?.montant;
        return ligne(
          Text.rich(
            TextSpan(
              style: texte,
              children: [
                const TextSpan(text: 'Acompte '),
                if (montant != null)
                  TextSpan(text: formaterArgent(montant), style: gras),
                TextSpan(text: montant != null ? ' à payer' : 'à payer'),
              ],
            ),
          ),
          r.peutPayer
              ? BoutonCompact(
                  libelle: 'Payer',
                  hauteur: 38,
                  margeHorizontale: 16,
                  onPressed: () => context.push(Chemins.paiement(r.id)),
                )
              : null,
        );
      case StatutReservation.enAttente:
        return ligne(
          Text('Le prestataire doit répondre à votre demande.', style: texte),
          null,
        );
      case StatutReservation.enCours:
        return ligne(Text('Prestation en cours.', style: texte), null);
      case StatutReservation.terminee:
        if (r.peutNoter) {
          return ligne(
            Text('Prestation terminée.', style: texte),
            BoutonCompact(
              libelle: 'Laisser un avis',
              hauteur: 38,
              margeHorizontale: 16,
              onPressed: () => context.push(Chemins.avis(r.id)),
            ),
          );
        }
        final avis = r.avis;
        return ligne(
          avis == null
              ? Text('Prestation terminée.', style: texte)
              : Row(
                  children: [
                    const Icon(
                      Icons.star_rounded,
                      size: 18,
                      color: CouleursSopi.bleu,
                    ),
                    const SizedBox(width: 4),
                    Text('Votre avis : ${avis.note}/5', style: gras),
                  ],
                ),
          null,
        );
      case StatutReservation.annulee:
      case StatutReservation.refusee:
        final motif = r.motifRefus;
        if (motif == null) return const SizedBox.shrink();
        return ligne(Text(motif, style: texte), null);
    }
  }
}
