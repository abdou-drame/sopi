import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/pastille_statut.dart';
import 'actions_demande.dart';
import 'depot_prestataire.dart';
import 'widgets/composants_prestation.dart';

/// Terminer une prestation « en cours » (maquette PrestaTerminer). Les montants viennent de l'API ;
/// le crédit réel est lu dans la réponse de `terminer`.
class TerminerPrestationPage extends ConsumerStatefulWidget {
  const TerminerPrestationPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<TerminerPrestationPage> createState() =>
      _TerminerPrestationPageState();
}

class _TerminerPrestationPageState
    extends ConsumerState<TerminerPrestationPage> {
  bool _enCours = false;

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.tableau);

  Future<void> _terminer() async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Terminer la prestation ?'),
        content: const Text(
          'Confirmez que le travail est fini et que le reste a été payé sur place. '
          'Votre portefeuille sera alors crédité.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Non'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Oui, terminer'),
          ),
        ],
      ),
    );
    if (confirme != true || !mounted) return;
    if (_enCours) return;
    setState(() => _enCours = true);
    final ok = await terminerPrestation(context, ref, widget.id);
    if (!mounted) return;
    setState(() => _enCours = false);
    // Terminée : retour au tableau, dont le solde vient d'être relu.
    if (ok) context.go(Chemins.tableau);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final detail = ref.watch(detailDemandeProvider(widget.id));
    final demande = detail.value;

    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTetePrestation(
                  titre: 'Prestation',
                  demande: demande,
                  pastille: const PastilleStatut(
                    libelle: 'En cours',
                    ton: TonStatut.succes,
                  ),
                  onRetour: _retour,
                ),
                if (demande != null && demande.montantsConnus)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 84, 16, 0),
                    child: CarteMontantsPrestation(
                      demande: demande,
                      libelleCredit: 'Crédité à la fin',
                    ),
                  )
                else if (detail.hasError && demande == null)
                  SizedBox(
                    height: 240,
                    child: EtatErreur(
                      message: ErreurApi.depuis(detail.error!).message,
                      onReessayer: () =>
                          ref.invalidate(detailDemandeProvider(widget.id)),
                    ),
                  ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 51, 20, 24),
                  child: Text(
                    'Quand le travail est fini et le reste payé sur place, terminez la prestation : '
                    'votre portefeuille est alors crédité.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12,
                      height: 17 / 12,
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                ),
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
            child: BoutonPrimaire(
              libelle: 'Terminer la prestation',
              chargement: _enCours,
              onPressed: _enCours ? null : _terminer,
            ),
          ),
        ],
      ),
    );
  }
}
