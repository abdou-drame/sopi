import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/disposition.dart';
import '../../core/widgets/boutons.dart';
import 'modeles.dart';

/// Écran « Demande envoyée » (maquette DemandeEnvoyee) : récapitulatif de la demande qui vient d'être créée.
/// Aucun paiement à ce stade : le client paie seulement après l'acceptation du prestataire.
class DemandeEnvoyeePage extends StatelessWidget {
  const DemandeEnvoyeePage({super.key, required this.reservation});

  /// Réponse de POST /reservations (le routeur renvoie à l'accueil si elle manque).
  final ReservationCreee reservation;

  void _voirReservation(BuildContext context) {
    context.go(Chemins.accueil);
    context.push(Chemins.detailReservation(reservation.id));
  }

  @override
  Widget build(BuildContext context) {
    final r = reservation;
    final theme = Theme.of(context);
    final bas = MediaQuery.paddingOf(context).bottom;
    final haut = hautEcran(context) - hautMaquette;

    return Scaffold(
      body: Stack(
        children: [
          LayoutBuilder(
            builder: (context, contraintes) => SingleChildScrollView(
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: contraintes.maxHeight),
                child: IntrinsicHeight(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(16, haut + 80, 16, bas + 24),
                    child: Column(
                      children: [
                        Apparition(
                          child: Column(
                            children: [
                              Container(
                                width: 104,
                                height: 104,
                                decoration: const BoxDecoration(
                                  color: CouleursSopi.bleuPale,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(
                                  Icons.send_rounded,
                                  size: 46,
                                  color: CouleursSopi.bleu,
                                ),
                              ),
                              const SizedBox(height: 20),
                              Text(
                                'Demande',
                                textAlign: TextAlign.center,
                                style: theme.textTheme.headlineMedium?.copyWith(
                                  fontWeight: FontWeight.w400,
                                  fontSize: 28,
                                  height: 34 / 28,
                                ),
                              ),
                              Text(
                                'envoyée',
                                textAlign: TextAlign.center,
                                style: theme.textTheme.headlineMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 28,
                                  height: 34 / 28,
                                ),
                              ),
                              const SizedBox(height: 20),
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 20,
                                ),
                                child: Text(
                                  '${r.prestataireNom} va répondre à votre demande. Aucun paiement maintenant.',
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    fontSize: 14,
                                    height: 20 / 14,
                                    color: CouleursSopi.texteSecondaire,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 76),
                        Apparition.indexee(
                          index: 1,
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(18),
                            decoration: BoxDecoration(
                              color: CouleursSopi.blanc,
                              borderRadius: BorderRadius.circular(26),
                              boxShadow: OmbresSopi.carte,
                            ),
                            child: Column(
                              children: [
                                _Ligne('Service', r.prestationNom),
                                const SizedBox(height: 10),
                                _Ligne('Date', formaterDate(r.debut)),
                                const SizedBox(height: 10),
                                _Ligne(
                                  'Tarif du service',
                                  formaterArgent(r.prix),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 32),
                        Apparition.indexee(
                          index: 2,
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 18,
                              vertical: 14,
                            ),
                            decoration: BoxDecoration(
                              color: CouleursSopi.bleuPastel,
                              borderRadius: BorderRadius.circular(22),
                            ),
                            child: Text(
                              "Dès que le prestataire accepte, vous êtes prévenu et vous disposez d'un délai limité pour payer l'acompte. La réservation est confirmée une fois l'acompte payé.",
                              style: theme.textTheme.bodyMedium?.copyWith(
                                fontSize: 13,
                                height: 19 / 13,
                                color: CouleursSopi.bleuProfond,
                              ),
                            ),
                          ),
                        ),
                        const Spacer(),
                        const SizedBox(height: 24),
                        BoutonPrimaire(
                          libelle: 'Voir ma réservation',
                          onPressed: () => _voirReservation(context),
                        ),
                        const SizedBox(height: 10),
                        BoutonSecondaire(
                          libelle: "Retour à l'accueil",
                          onPressed: () => context.go(Chemins.accueil),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  const _Ligne(this.etiquette, this.valeur);

  final String etiquette;
  final String valeur;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          etiquette,
          style: theme.textTheme.bodyMedium?.copyWith(
            fontSize: 14,
            color: CouleursSopi.texteSecondaire,
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            valeur,
            textAlign: TextAlign.right,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}
