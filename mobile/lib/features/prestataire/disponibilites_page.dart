import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import '../reservation/formats.dart';
import 'depot_prestataire.dart';
import 'modeles.dart';

/// Mes disponibilités (maquette Disponibilites) : créneaux des prochaines semaines, groupés par jour.
/// Seul un créneau libre porte une croix ; les réservés, verrouillés et indisponibles ne se retirent pas.
/// Créneaux dont le retrait est en cours : un second retrait du même créneau est ignoré (pas de double requête).
final _retraitsEnCours = <String>{};

class DisponibilitesPage extends ConsumerWidget {
  const DisponibilitesPage({super.key});

  void _retour(BuildContext context) =>
      context.canPop() ? context.pop() : context.go(Chemins.tableau);

  Future<void> _retirer(
    BuildContext context,
    WidgetRef ref,
    CreneauAgenda creneau,
  ) async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Retirer ce créneau ?'),
        content: Text(
          '${formaterJourCourt(creneau.debut)} à ${formaterHeure(creneau.debut)}. '
          'Les clients ne pourront plus le réserver.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Non'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Oui, retirer'),
          ),
        ],
      ),
    );
    if (confirme != true || !context.mounted) return;
    if (!_retraitsEnCours.add(creneau.id)) return;
    final messager = ScaffoldMessenger.of(context);
    try {
      await ref.read(depotPrestataireProvider).supprimerCreneau(creneau.id);
      ref.invalidate(creneauxProvider);
      messager.showSnackBar(const SnackBar(content: Text('Créneau retiré.')));
    } catch (e) {
      // Le créneau a pu être pris entretemps (409) : on affiche le message du serveur et on relit.
      ref.invalidate(creneauxProvider);
      messager.showSnackBar(
        SnackBar(content: Text(ErreurApi.depuis(e).message)),
      );
    } finally {
      _retraitsEnCours.remove(creneau.id);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final creneaux = ref.watch(creneauxProvider);
    final theme = Theme.of(context);
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteEcran(
                  titreLigne1: 'Mes',
                  titreLigne2: 'disponibilités',
                  onRetour: () => _retour(context),
                  enfant: Text(
                    "Vos créneaux des prochaines semaines. Touchez la croix d'un créneau libre pour le retirer.",
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 13,
                      height: 19 / 13,
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                ...creneaux.when(
                  loading: () => [
                    const SizedBox(height: 240, child: EtatChargement()),
                  ],
                  error: (e, _) => [
                    SizedBox(
                      height: 280,
                      child: EtatErreur(
                        message: ErreurApi.depuis(e).message,
                        onReessayer: () => ref.invalidate(creneauxProvider),
                      ),
                    ),
                  ],
                  data: (liste) {
                    if (liste.isEmpty) {
                      return [
                        const SizedBox(
                          height: 280,
                          child: EtatVide(
                            titre: 'Aucun créneau',
                            message:
                                'Ajoutez des créneaux pour que les clients puissent réserver.',
                            icone: Icons.event_available_outlined,
                          ),
                        ),
                      ];
                    }
                    final jours = <DateTime, List<CreneauAgenda>>{};
                    for (final c in [
                      ...liste,
                    ]..sort((a, b) => a.debut.compareTo(b.debut))) {
                      jours.putIfAbsent(debutDuJour(c.debut), () => []).add(c);
                    }
                    return [
                      for (final entree in jours.entries)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                          child: _CarteJour(
                            jour: entree.key,
                            creneaux: entree.value,
                            onRetirer: (c) => _retirer(context, ref, c),
                          ),
                        ),
                    ];
                  },
                ),
                const SizedBox(height: 14),
              ],
            ),
          ),
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
                  libelle: 'Ajouter des créneaux',
                  onPressed: () => context.push(Chemins.plageHoraire),
                ),
                const SizedBox(height: 10),
                BoutonCompact(
                  libelle: 'Déclarer une indisponibilité',
                  ton: TonBoutonCompact.contour,
                  hauteur: 50,
                  taille: 15,
                  pleineLargeur: true,
                  onPressed: () => context.push(Chemins.indisponibilite),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CarteJour extends StatelessWidget {
  const _CarteJour({
    required this.jour,
    required this.creneaux,
    required this.onRetirer,
  });

  final DateTime jour;
  final List<CreneauAgenda> creneaux;
  final ValueChanged<CreneauAgenda> onRetirer;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            formaterJourCourt(jour),
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final c in creneaux)
                _PuceCreneau(creneau: c, onRetirer: () => onRetirer(c)),
            ],
          ),
        ],
      ),
    );
  }
}

class _PuceCreneau extends StatelessWidget {
  const _PuceCreneau({required this.creneau, required this.onRetirer});

  final CreneauAgenda creneau;
  final VoidCallback onRetirer;

  @override
  Widget build(BuildContext context) {
    final heure = formaterHeure(creneau.debut);
    final (fond, texte, poids, libelle) = switch (creneau.statut) {
      StatutCreneau.libre => (
        CouleursSopi.bleuPastel,
        CouleursSopi.bleu,
        FontWeight.w700,
        heure,
      ),
      StatutCreneau.reserve => (
        CouleursSopi.texte,
        CouleursSopi.blanc,
        FontWeight.w700,
        '$heure · réservé',
      ),
      StatutCreneau.verrouille => (
        CouleursSopi.ambrePastel,
        CouleursSopi.ambreProfond,
        FontWeight.w700,
        '$heure · verrouillé',
      ),
      StatutCreneau.indisponible => (
        CouleursSopi.ardoisePastel,
        CouleursSopi.texteSecondaire,
        FontWeight.w600,
        heure,
      ),
    };
    final libre = creneau.statut == StatutCreneau.libre;
    return Container(
      height: 34,
      padding: EdgeInsets.only(left: 12, right: libre ? 0 : 12),
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(17),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            libelle,
            style: TextStyle(fontSize: 12, fontWeight: poids, color: texte),
          ),
          if (libre)
            Semantics(
              button: true,
              label: 'Retirer le créneau de $heure',
              excludeSemantics: true,
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: onRetirer,
                // Zone tactile de toute la hauteur de la pastille (34), le visuel de la croix ne change pas.
                child: const SizedBox(
                  width: 34,
                  height: 34,
                  child: Icon(
                    Icons.close_rounded,
                    size: 13,
                    color: CouleursSopi.bleu,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
