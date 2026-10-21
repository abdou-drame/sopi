import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

/// Base commune des boutons pilule : texte, état désactivé, état chargement.
class _BoutonPilule extends StatelessWidget {
  const _BoutonPilule({
    required this.libelle,
    required this.onPressed,
    required this.chargement,
    required this.fond,
    required this.texte,
    this.icone,
    this.pleineLargeur = true,
    this.hauteur = DimensionsSopi.hauteurBouton,
    this.taille = 16,
    this.margeHorizontale = 28,
  });

  final String libelle;
  final VoidCallback? onPressed;
  final bool chargement;
  final Color fond;
  final Color texte;
  final IconData? icone;
  final bool pleineLargeur;
  final double hauteur;
  final double taille;
  final double margeHorizontale;

  @override
  Widget build(BuildContext context) {
    final actif = onPressed != null && !chargement;
    final style = ButtonStyle(
      minimumSize: WidgetStatePropertyAll(
        Size(pleineLargeur ? double.infinity : 0, hauteur),
      ),
      padding: WidgetStatePropertyAll(
        EdgeInsets.symmetric(horizontal: margeHorizontale),
      ),
      elevation: const WidgetStatePropertyAll(0),
      backgroundColor: WidgetStateProperty.resolveWith(
        (etats) => etats.contains(WidgetState.disabled) && !chargement
            ? CouleursSopi.fondSecondaire
            : fond,
      ),
      foregroundColor: WidgetStateProperty.resolveWith(
        (etats) => etats.contains(WidgetState.disabled) && !chargement
            ? CouleursSopi.texteSecondaire
            : texte,
      ),
      side: const WidgetStatePropertyAll(BorderSide.none),
      shape: WidgetStatePropertyAll(
        RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(hauteur / 2),
        ),
      ),
      textStyle: WidgetStatePropertyAll(
        Theme.of(context).textTheme.labelLarge?.copyWith(
          fontWeight: FontWeight.w700,
          fontSize: taille,
        ),
      ),
    );

    final contenu = chargement
        ? SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              color: texte,
              semanticsLabel: 'Chargement',
            ),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icone != null) ...[
                Icon(icone, size: 20),
                const SizedBox(width: 8),
              ],
              Flexible(child: Text(libelle, textAlign: TextAlign.center)),
            ],
          );

    return FilledButton(
      onPressed: actif ? onPressed : null,
      style: style,
      // Pendant le chargement le bouton garde son fond (pas l'aspect « désactivé »).
      child: contenu,
    );
  }
}

/// Action principale : fond bleu, texte blanc.
class BoutonPrimaire extends StatelessWidget {
  const BoutonPrimaire({
    super.key,
    required this.libelle,
    required this.onPressed,
    this.chargement = false,
    this.icone,
    this.pleineLargeur = true,
    this.hauteur = DimensionsSopi.hauteurBouton,
  });

  final String libelle;
  final VoidCallback? onPressed;
  final bool chargement;
  final IconData? icone;
  final bool pleineLargeur;
  final double hauteur;

  @override
  Widget build(BuildContext context) => _BoutonPilule(
    libelle: libelle,
    onPressed: onPressed,
    chargement: chargement,
    hauteur: hauteur,
    fond: CouleursSopi.bleu,
    texte: CouleursSopi.blanc,
    icone: icone,
    pleineLargeur: pleineLargeur,
  );
}

/// Action secondaire : fond #e9ecf3, sans bordure, texte #12172b.
class BoutonSecondaire extends StatelessWidget {
  const BoutonSecondaire({
    super.key,
    required this.libelle,
    required this.onPressed,
    this.chargement = false,
    this.icone,
    this.pleineLargeur = true,
  });

  final String libelle;
  final VoidCallback? onPressed;
  final bool chargement;
  final IconData? icone;
  final bool pleineLargeur;

  @override
  Widget build(BuildContext context) => _BoutonPilule(
    libelle: libelle,
    onPressed: onPressed,
    chargement: chargement,
    fond: CouleursSopi.fondSecondaire,
    texte: CouleursSopi.texte,
    icone: icone,
    pleineLargeur: pleineLargeur,
  );
}

enum TonBoutonCompact { primaire, clair, contour }

/// Bouton pilule de petite taille (cartes de liste, barres d'action) : « Réserver », « Voir », « Continuer ».
/// [clair] : fond blanc, texte bleu, pour une pose sur fond bleu. [contour] : fond #e9ecf3, texte #12172b (action secondaire d'une carte).
class BoutonCompact extends StatelessWidget {
  const BoutonCompact({
    super.key,
    required this.libelle,
    required this.onPressed,
    this.ton = TonBoutonCompact.primaire,
    this.hauteur = 40,
    this.taille = 13,
    this.margeHorizontale = 18,
    this.chargement = false,
    this.pleineLargeur = false,
  });

  final String libelle;
  final VoidCallback? onPressed;
  final TonBoutonCompact ton;
  final double hauteur;
  final double taille;
  final double margeHorizontale;
  final bool chargement;
  final bool pleineLargeur;

  @override
  Widget build(BuildContext context) {
    final clair = ton != TonBoutonCompact.primaire;
    final contour = ton == TonBoutonCompact.contour;
    return _BoutonPilule(
      libelle: libelle,
      onPressed: onPressed,
      chargement: chargement,
      fond: contour
          ? CouleursSopi.fondSecondaire
          : (clair ? CouleursSopi.blanc : CouleursSopi.bleu),
      texte: contour
          ? CouleursSopi.texte
          : (clair ? CouleursSopi.bleu : CouleursSopi.blanc),
      pleineLargeur: pleineLargeur,
      hauteur: hauteur,
      taille: taille,
      margeHorizontale: margeHorizontale,
    );
  }
}
