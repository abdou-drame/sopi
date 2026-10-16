import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

class _Bandeau extends StatelessWidget {
  const _Bandeau({
    required this.message,
    required this.fond,
    required this.texte,
    required this.icone,
    this.titre,
    this.rayon = DimensionsSopi.rayonBandeau,
    this.remplissage = const EdgeInsets.all(14),
    this.couleurMessage,
    this.hauteurLigne,
    this.poidsMessage = FontWeight.w600,
    this.tailleMessage,
  });

  final String message;
  final String? titre;
  final Color fond;
  final Color texte;
  final IconData? icone;
  final double rayon;
  final EdgeInsets remplissage;
  final Color? couleurMessage;
  final double? hauteurLigne;
  final FontWeight poidsMessage;
  final double? tailleMessage;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: remplissage,
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(rayon),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icone != null) ...[
            Icon(icone, color: texte, size: 22),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (titre != null)
                  Text(
                    titre!,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: texte,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                Text(
                  message,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: couleurMessage ?? texte,
                    fontWeight: poidsMessage,
                    fontSize: tailleMessage,
                    height: hauteurLigne,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class BandeauInfo extends StatelessWidget {
  const BandeauInfo({super.key, required this.message, this.titre})
    : confirmation = false;

  /// Variante « lien envoyé » de la maquette MotDePasse : coche, coins 24, texte bleu profond 13/19.
  const BandeauInfo.confirmation({super.key, required this.message})
    : titre = null,
      confirmation = true;

  final String message;
  final String? titre;
  final bool confirmation;

  @override
  Widget build(BuildContext context) => confirmation
      ? _Bandeau(
          message: message,
          fond: CouleursSopi.bleuPastel,
          texte: CouleursSopi.bleu,
          icone: Icons.check_rounded,
          rayon: DimensionsSopi.rayonBandeauConfirmation,
          remplissage: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          couleurMessage: CouleursSopi.bleuProfond,
          hauteurLigne: 19 / 13,
          poidsMessage: FontWeight.w400,
          tailleMessage: 13,
        )
      : _Bandeau(
          message: message,
          titre: titre,
          fond: CouleursSopi.bleuPastel,
          texte: CouleursSopi.bleu,
          icone: Icons.info_outline_rounded,
        );
}

class BandeauErreur extends StatelessWidget {
  const BandeauErreur({super.key, required this.message, this.titre})
    : alerte = false;

  /// Variante « alerte » de la maquette ConnexionBloquee : sans icône, coins 20, texte 13/18.
  const BandeauErreur.alerte({super.key, required this.message})
    : titre = null,
      alerte = true;

  final String message;
  final String? titre;
  final bool alerte;

  @override
  Widget build(BuildContext context) => alerte
      ? _Bandeau(
          message: message,
          fond: CouleursSopi.erreurFond,
          texte: CouleursSopi.erreurTexte,
          icone: null,
          rayon: DimensionsSopi.rayonBandeauAlerte,
          remplissage: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          hauteurLigne: 18 / 13,
          tailleMessage: 13,
        )
      : _Bandeau(
          message: message,
          titre: titre,
          fond: CouleursSopi.erreurFond,
          texte: CouleursSopi.erreurTexte,
          icone: Icons.error_outline_rounded,
        );
}

class BandeauSucces extends StatelessWidget {
  const BandeauSucces({super.key, required this.message, this.titre});

  final String message;
  final String? titre;

  @override
  Widget build(BuildContext context) => _Bandeau(
    message: message,
    titre: titre,
    fond: CouleursSopi.succesFond,
    texte: CouleursSopi.succesTexte,
    icone: Icons.check_circle_outline_rounded,
  );
}
