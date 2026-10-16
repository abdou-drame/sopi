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
  });

  final String message;
  final String? titre;
  final Color fond;
  final Color texte;
  final IconData icone;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(DimensionsSopi.rayonBandeau),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icone, color: texte, size: 22),
          const SizedBox(width: 12),
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
                    color: texte,
                    fontWeight: FontWeight.w600,
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

/// Information neutre sur fond bleu pastel.
class BandeauInfo extends StatelessWidget {
  const BandeauInfo({super.key, required this.message, this.titre});

  final String message;
  final String? titre;

  @override
  Widget build(BuildContext context) => _Bandeau(
    message: message,
    titre: titre,
    fond: CouleursSopi.bleuPastel,
    texte: CouleursSopi.bleu,
    icone: Icons.info_outline_rounded,
  );
}

/// Erreur sur fond rouge pâle.
class BandeauErreur extends StatelessWidget {
  const BandeauErreur({super.key, required this.message, this.titre});

  final String message;
  final String? titre;

  @override
  Widget build(BuildContext context) => _Bandeau(
    message: message,
    titre: titre,
    fond: CouleursSopi.erreurFond,
    texte: CouleursSopi.erreurTexte,
    icone: Icons.error_outline_rounded,
  );
}

/// Confirmation sur fond vert pâle.
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
