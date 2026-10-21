import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/onglets_segmentes.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/pastille_statut.dart';
import '../reservation/modeles.dart' show StatutReservation;
import '../reservation/presentation_reservation.dart';
import 'demandes_controleur.dart';
import 'modeles.dart';
import 'widgets/composants_prestataire.dart';

/// « Mes réservations » du prestataire (maquette PrestaReservations) : quatre onglets, 20 demandes par page.
class ReservationsPrestatairePage extends ConsumerStatefulWidget {
  const ReservationsPrestatairePage({super.key});

  @override
  ConsumerState<ReservationsPrestatairePage> createState() =>
      _ReservationsPrestatairePageState();
}

class _ReservationsPrestatairePageState
    extends ConsumerState<ReservationsPrestatairePage> {
  OngletDemandes _onglet = OngletDemandes.aTraiter;

  DemandesControleur get _controleur =>
      ref.read(listeDemandesProvider(_onglet).notifier);

  @override
  Widget build(BuildContext context) {
    final etat = ref.watch(listeDemandesProvider(_onglet));

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
      corps = SizedBox(
        height: 320,
        child: EtatVide(
          message: _onglet == OngletDemandes.aTraiter
              ? 'Aucune demande à traiter pour le moment.'
              : 'Aucune réservation ici.',
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
                child: EnTeteEcran(
                  titreLigne1: 'Mes',
                  titreLigne2: 'réservations',
                  onRetour: () => context.canPop()
                      ? context.pop()
                      : context.go(Chemins.tableau),
                  enfant: OngletsSegmentes<OngletDemandes>(
                    valeurs: OngletDemandes.values,
                    libelle: (o) => o.libelle,
                    actif: _onglet,
                    onChange: (o) => setState(() => _onglet = o),
                    tailleTexte: 12,
                  ),
                ),
              ),
              SliverToBoxAdapter(child: corps),
              SliverToBoxAdapter(
                child: SizedBox(
                  height: MediaQuery.paddingOf(context).bottom + 24,
                ),
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

  final EtatDemandes etat;
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
                child: CarteDemande(demande: etat.elements[i]),
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

/// Carte d'une demande : client, statut, prestation et date, puis ce que le statut permet de faire.
class CarteDemande extends StatelessWidget {
  const CarteDemande({super.key, required this.demande});

  final DemandeReservation demande;

  @override
  Widget build(BuildContext context) {
    final d = demande;
    final theme = Theme.of(context);
    final echeance = d.reponseAvant;

    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(26),
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(26),
        onTap: () => context.push(Chemins.reservationPrestataire(d.id)),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      d.clientAffiche,
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
                    libelle: d.statut.libelle,
                    ton: tonDuStatut(d.statut),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${d.prestationNom} · ${formaterDate(d.debut)}',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 13,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
              if (d.aTraiter)
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      if (echeance != null)
                        Flexible(child: ResteAvantReponse(echeance: echeance))
                      else
                        const SizedBox.shrink(),
                      const SizedBox(width: 8),
                      BoutonsReponse(demande: d, compact: true),
                    ],
                  ),
                )
              else if (d.statut == StatutReservation.acceptee)
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Text(
                    "En attente du paiement de l'acompte par le client.",
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 13,
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
