import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/code_saisie/code_saisie.dart';
import '../../core/widgets/etats.dart';
import 'actions_demande.dart';
import 'depot_prestataire.dart';
import 'widgets/composants_prestation.dart';

/// Prestation d'une réservation confirmée (maquettes Prestation, CodeVerification, CodeValide, CodeFaux) :
/// le prestataire saisit le code du client, puis la prestation passe « en cours ». Tout vient de l'API.
class PrestationPage extends ConsumerStatefulWidget {
  const PrestationPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<PrestationPage> createState() => _PrestationPageState();
}

class _PrestationPageState extends ConsumerState<PrestationPage> {
  final _saisie = GlobalKey<CodeSaisieState>();
  bool _annulation = false;

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.tableau);

  /// Envoie le code. Les erreurs (422, 429, réseau…) remontent à la saisie, qui les affiche.
  Future<void> _demarrer(String code) async {
    try {
      await ref.read(depotPrestataireProvider).demarrer(widget.id, code);
    } on Object catch (e) {
      // 409 : l'état de la réservation a changé côté serveur, on le relit. Un code faux ne change rien.
      if (ErreurApi.depuis(e) is ErreurServeur) {
        rafraichirDemandes(ref, widget.id);
      }
      rethrow;
    }
  }

  /// Après l'animation « Code validé » : on relit la réservation, qui est maintenant « en cours ».
  void _apresSucces() => rafraichirDemandes(ref, widget.id);

  Future<void> _annuler() async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Annuler la prestation ?'),
        content: const Text(
          'La réservation sera annulée et le client remboursé. '
          'Vous devrez indiquer un motif.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Non'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Oui, annuler'),
          ),
        ],
      ),
    );
    if (confirme != true || !mounted) return;
    setState(() => _annulation = true);
    await context.push(Chemins.annulationPrestataire(widget.id));
    if (mounted) setState(() => _annulation = false);
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
                  titre: 'Rendez-vous',
                  demande: demande,
                  pastille: const PastilleConfirmee(),
                  onRetour: _retour,
                ),
                if (demande != null && demande.montantsConnus)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 84, 16, 0),
                    child: CarteMontantsPrestation(
                      demande: demande,
                      libelleCredit: 'Crédité au portefeuille à la fin',
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
                  padding: const EdgeInsets.fromLTRB(20, 51, 20, 0),
                  child: Text(
                    'Code du client',
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                  child: CodeSaisie(
                    key: _saisie,
                    onSubmit: _demarrer,
                    onSucces: _apresSucces,
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
                  child: Text(
                    'Demandez au client le code à 6 caractères affiché dans son application. '
                    'Vous pourrez ensuite démarrer la prestation.',
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
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                BoutonPrimaire(
                  libelle: 'Démarrer la prestation',
                  onPressed: () => _saisie.currentState?.soumettreSiComplet(),
                ),
                const SizedBox(height: 10),
                BoutonCompact(
                  libelle: 'Annuler (le client est remboursé)',
                  ton: TonBoutonCompact.clair,
                  hauteur: 50,
                  taille: 14,
                  pleineLargeur: true,
                  onPressed: _annulation ? null : _annuler,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
