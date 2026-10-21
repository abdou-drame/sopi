import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/liste_infinie.dart';
import '../../core/widgets/pastille_statut.dart';
import 'depot_compte.dart';
import 'modeles_litige.dart';

TonStatut _ton(StatutLitige s) =>
    s == StatutLitige.ouvert ? TonStatut.info : TonStatut.neutre;

/// « Mes signalements » : liste paginée des litiges ouverts par le client (écran hors maquettes).
class MesSignalementsPage extends ConsumerWidget {
  const MesSignalementsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final depot = ref.watch(depotCompteProvider);
    return Scaffold(
      body: ListeInfinie<LitigeClient>(
        titreLigne1: 'Mes',
        titreLigne2: 'signalements',
        charger: depot.litiges,
        messageVide: "Vous n'avez fait aucun signalement.",
        iconeVide: Icons.flag_outlined,
        construire: (context, l) => _CarteSignalement(litige: l),
      ),
    );
  }
}

class _CarteSignalement extends StatelessWidget {
  const _CarteSignalement({required this.litige});

  final LitigeClient litige;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(26),
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(26),
        onTap: () => context.push(Chemins.signalement(litige.id)),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      litige.motif,
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
                    libelle: litige.statut.libelle,
                    ton: _ton(litige.statut),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${litige.prestataireNom} · ${litige.prestationNom} · ${formaterDate(litige.dateRendezVous)}',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 13,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Détail d'un signalement : statut, motif, description, preuves, réponse du prestataire, décision.
class DetailSignalementPage extends ConsumerWidget {
  const DetailSignalementPage({super.key, required this.litigeId});

  final String litigeId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(litigeProvider(litigeId));
    final theme = Theme.of(context);
    final bas = MediaQuery.paddingOf(context).bottom;

    final Widget corps;
    if (async.hasError && !async.hasValue) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(
          message: ErreurApi.depuis(async.error!).message,
          onReessayer: () => ref.invalidate(litigeProvider(litigeId)),
        ),
      );
    } else if (!async.hasValue) {
      corps = const SizedBox(height: 320, child: EtatChargement());
    } else {
      final l = async.requireValue;
      corps = Padding(
        padding: EdgeInsets.fromLTRB(
          DimensionsSopi.margeCarte,
          16,
          DimensionsSopi.margeCarte,
          bas + 24,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Bloc(
              titre: l.motif,
              pastille: PastilleStatut(
                libelle: l.statut.libelle,
                ton: _ton(l.statut),
              ),
              children: [
                Text(
                  '${l.prestataireNom} · ${l.prestationNom} · ${formaterDate(l.dateRendezVous)}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 13,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  l.description,
                  style: theme.textTheme.bodyMedium?.copyWith(fontSize: 14),
                ),
                if (l.preuves.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      for (final p in l.preuves)
                        _Preuve(litigeId: l.id, preuveId: p.id),
                    ],
                  ),
                ],
              ],
            ),
            if (l.reponsePrestataire != null) ...[
              const SizedBox(height: 12),
              _Bloc(
                titre: 'Réponse du prestataire',
                children: [
                  Text(
                    l.reponsePrestataire!,
                    style: theme.textTheme.bodyMedium?.copyWith(fontSize: 14),
                  ),
                ],
              ),
            ],
            if (l.statut == StatutLitige.cloture) ...[
              const SizedBox(height: 12),
              _Bloc(
                titre: libelleDecision(l.decision),
                children: [
                  if (l.motifDecision != null)
                    Text(
                      l.motifDecision!,
                      style: theme.textTheme.bodyMedium?.copyWith(fontSize: 14),
                    ),
                ],
              ),
            ],
          ],
        ),
      );
    }

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(litigeProvider(litigeId).future),
        child: ListView(
          padding: EdgeInsets.zero,
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const EnTeteEcran(
              titreLigne1: 'Détail du',
              titreLigne2: 'signalement',
            ),
            corps,
          ],
        ),
      ),
    );
  }
}

class _Bloc extends StatelessWidget {
  const _Bloc({required this.titre, required this.children, this.pastille});

  final String titre;
  final Widget? pastille;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: CouleursSopi.blanc,
      borderRadius: BorderRadius.circular(26),
      border: Border.all(color: CouleursSopi.bordure),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                titre,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            ?pastille,
          ],
        ),
        if (children.isNotEmpty) const SizedBox(height: 8),
        ...children,
      ],
    ),
  );
}

/// Vignette d'une preuve : les octets sont lus avec le jeton, puis affichés.
class _Preuve extends ConsumerWidget {
  const _Preuve({required this.litigeId, required this.preuveId});

  final String litigeId;
  final String preuveId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(
      preuveProvider((litigeId: litigeId, preuveId: preuveId)),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        width: 72,
        height: 72,
        child: async.when(
          data: (octets) => Image.memory(
            octets,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => const _PreuveIndisponible(),
          ),
          error: (_, _) => const _PreuveIndisponible(),
          loading: () => const ColoredBox(color: CouleursSopi.champ),
        ),
      ),
    );
  }
}

class _PreuveIndisponible extends StatelessWidget {
  const _PreuveIndisponible();

  @override
  Widget build(BuildContext context) => const ColoredBox(
    color: CouleursSopi.champ,
    child: Icon(
      Icons.broken_image_outlined,
      color: CouleursSopi.texteSecondaire,
    ),
  );
}
