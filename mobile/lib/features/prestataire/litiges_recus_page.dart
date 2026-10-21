import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/onglets_segmentes.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import 'depot_prestataire.dart';
import 'modeles_litiges.dart';

/// « Signalements reçus » (maquette PrestaLitiges) : les litiges ouverts par des clients contre ce prestataire,
/// répartis en À traiter (pas encore de réponse), En examen (réponse envoyée) et Clos.
class LitigesRecusPage extends ConsumerStatefulWidget {
  const LitigesRecusPage({super.key});

  @override
  ConsumerState<LitigesRecusPage> createState() => _LitigesRecusPageState();
}

class _LitigesRecusPageState extends ConsumerState<LitigesRecusPage> {
  OngletLitiges _onglet = OngletLitiges.aTraiter;

  @override
  Widget build(BuildContext context) {
    final etat = ref.watch(litigesRecusProvider);

    Widget corps;
    if (etat.isLoading && !etat.hasValue) {
      corps = const Padding(
        padding: EdgeInsets.fromLTRB(16, 20, 16, 0),
        child: Column(
          children: [
            Squelette(hauteur: 100, rayon: 26),
            SizedBox(height: 12),
            Squelette(hauteur: 100, rayon: 26),
          ],
        ),
      );
    } else if (etat.hasError && !etat.hasValue) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(
          message: ErreurApi.depuis(etat.error!).message,
          onReessayer: () => ref.invalidate(litigesRecusProvider),
        ),
      );
    } else {
      final elements = [
        for (final l in etat.requireValue)
          if (l.onglet == _onglet) l,
      ];
      corps = Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
        child: elements.isEmpty
            ? SizedBox(
                height: 280,
                child: EtatVide(
                  message: switch (_onglet) {
                    OngletLitiges.aTraiter =>
                      'Aucun signalement à traiter pour le moment.',
                    OngletLitiges.enExamen => 'Aucun signalement en examen.',
                    OngletLitiges.clos => 'Aucun signalement clos.',
                  },
                  icone: Icons.flag_outlined,
                ),
              )
            : Column(
                children: [
                  for (final l in elements)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _CarteLitige(litige: l),
                    ),
                  if (_onglet == OngletLitiges.aTraiter)
                    const _NoteReponseRapide(),
                ],
              ),
      );
    }

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(litigesRecusProvider.future),
        edgeOffset: MediaQuery.paddingOf(context).top,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: EnTeteEcran(
                titreLigne1: 'Signalements',
                titreLigne2: 'reçus',
                onRetour: () => context.canPop()
                    ? context.pop()
                    : context.go(Chemins.profilPrestataire),
                enfant: OngletsSegmentes<OngletLitiges>(
                  valeurs: OngletLitiges.values,
                  libelle: (o) => o.libelle,
                  actif: _onglet,
                  onChange: (o) => setState(() => _onglet = o),
                  tailleTexte: 12,
                  cle: (o) => ValueKey('onglet-${o.name}'),
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
    );
  }
}

/// Pastille de statut d'un signalement (jaune « À traiter » comme la maquette ; indigo pour « En examen »).
class _PastilleLitige extends StatelessWidget {
  const _PastilleLitige({required this.onglet});

  final OngletLitiges onglet;

  @override
  Widget build(BuildContext context) {
    final (fond, texte) = switch (onglet) {
      OngletLitiges.aTraiter => (
        CouleursSopi.ambrePastel,
        CouleursSopi.ambreProfond,
      ),
      OngletLitiges.enExamen => (
        CouleursSopi.indigoPastel,
        CouleursSopi.indigoTexte,
      ),
      OngletLitiges.clos => (
        CouleursSopi.bordure,
        CouleursSopi.texteSecondaire,
      ),
    };
    return Container(
      height: 26,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Text(
        onglet.libelle,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: texte,
        ),
      ),
    );
  }
}

/// Carte d'un signalement : client, statut, motif · date du rendez-vous, bouton « Répondre » s'il reste à traiter.
class _CarteLitige extends StatelessWidget {
  const _CarteLitige({required this.litige});

  final LitigeRecu litige;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final jour = formaterDate(litige.dateRendezVous).split(',').first;
    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(26),
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      child: InkWell(
        key: ValueKey('litige-${litige.id}'),
        customBorder: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(26),
        ),
        onTap: () => context.push(Chemins.litigeRecu(litige.id)),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      litige.clientAffiche,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  _PastilleLitige(onglet: litige.onglet),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${litige.motif} · $jour',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 13,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
              if (litige.onglet == OngletLitiges.aTraiter) ...[
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerRight,
                  child: BoutonCompact(
                    libelle: 'Répondre',
                    hauteur: 38,
                    onPressed: () =>
                        context.push(Chemins.litigeRecu(litige.id)),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Encadré en pointillés de la maquette, sous les signalements à traiter.
class _NoteReponseRapide extends StatelessWidget {
  const _NoteReponseRapide();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: CouleursSopi.bordureForte),
      ),
      child: Text(
        "Répondez rapidement : votre version est transmise à l'administrateur avec celle du client.",
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          fontSize: 12,
          height: 17 / 12,
          color: CouleursSopi.texteSecondaire,
        ),
      ),
    );
  }
}
