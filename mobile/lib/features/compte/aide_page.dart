import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/config/support.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';

const messageMessagerieIndisponible = "Impossible d'ouvrir votre messagerie.";

const _questions = [
  (
    'Comment fonctionne la réservation ?',
    "Vous choisissez un service et un créneau, puis vous envoyez la demande. Le prestataire dispose d'un délai limité pour répondre. S'il accepte, vous avez un délai limité pour payer l'acompte et confirmer.",
  ),
  (
    "Combien coûte l'acompte ?",
    "L'acompte représente une part du tarif du service, indiquée avant le paiement. Le reste se règle sur place, auprès du prestataire.",
  ),
  (
    'Puis-je annuler ?',
    "Oui, jusqu'à un court délai avant le rendez-vous (indiqué au moment d'annuler). L'acompte n'est pas remboursé si vous annulez. Il l'est si c'est le prestataire qui annule.",
  ),
  (
    'À quoi sert le code de démarrage ?',
    'Une fois la réservation confirmée, un code apparaît dans votre réservation. Donnez-le au prestataire à son arrivée pour démarrer la prestation.',
  ),
  (
    'Un problème avec une prestation ?',
    'Depuis le détail de votre réservation, touchez « Signaler un problème ». Un administrateur examinera votre dossier.',
  ),
];

/// « Aide et support » (maquette Aide) : questions fréquentes statiques. Le bouton « Contacter le support »
/// n'apparaît que si une adresse de support est configurée.
class AidePage extends ConsumerWidget {
  const AidePage({super.key});

  Future<void> _contacter(BuildContext context, WidgetRef ref) async {
    final ouvert = await contacterSupport(ref);
    if (!ouvert && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text(messageMessagerieIndisponible)),
      );
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final aSupport = ref.watch(supportEmailProvider).isNotEmpty;
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: CustomScrollView(
              slivers: [
                const SliverToBoxAdapter(
                  child: EnTeteEcran(
                    titreLigne1: 'Aide et',
                    titreLigne2: 'support',
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(
                    DimensionsSopi.margeCarte,
                    16,
                    DimensionsSopi.margeCarte,
                    16,
                  ),
                  sliver: SliverToBoxAdapter(
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: CouleursSopi.blanc,
                        borderRadius: BorderRadius.circular(30),
                        boxShadow: OmbresSopi.carte,
                      ),
                      child: Column(
                        children: [
                          for (var i = 0; i < _questions.length; i++)
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              decoration: BoxDecoration(
                                border: i == 0
                                    ? null
                                    : const Border(
                                        top: BorderSide(
                                          color: Color(0xFFE3E7EF),
                                        ),
                                      ),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _questions[i].$1,
                                    style: theme.textTheme.titleSmall?.copyWith(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    _questions[i].$2,
                                    style: theme.textTheme.bodyMedium?.copyWith(
                                      fontSize: 13,
                                      height: 19 / 13,
                                      color: CouleursSopi.texteSecondaire,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (aSupport)
            Padding(
              padding: const EdgeInsets.fromLTRB(
                DimensionsSopi.margeCarte,
                8,
                DimensionsSopi.margeCarte,
                24,
              ),
              child: BoutonPrimaire(
                libelle: 'Contacter le support',
                onPressed: () => _contacter(context, ref),
              ),
            ),
        ],
      ),
    );
  }
}
