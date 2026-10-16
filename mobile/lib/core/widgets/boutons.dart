import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

/// Base commune des trois boutons pilule : texte, état désactivé, état chargement.
class _BoutonPilule extends StatelessWidget {
  const _BoutonPilule({
    required this.libelle,
    required this.onPressed,
    required this.chargement,
    required this.fond,
    required this.texte,
    this.bordure,
    this.icone,
    this.pleineLargeur = true,
  });

  final String libelle;
  final VoidCallback? onPressed;
  final bool chargement;
  final Color fond;
  final Color texte;
  final Color? bordure;
  final IconData? icone;
  final bool pleineLargeur;

  @override
  Widget build(BuildContext context) {
    final actif = onPressed != null && !chargement;
    final style = ButtonStyle(
      minimumSize: WidgetStatePropertyAll(
        Size(pleineLargeur ? double.infinity : 0, DimensionsSopi.hauteurBouton),
      ),
      padding: const WidgetStatePropertyAll(
        EdgeInsets.symmetric(horizontal: 28),
      ),
      elevation: const WidgetStatePropertyAll(0),
      backgroundColor: WidgetStateProperty.resolveWith(
        (etats) => etats.contains(WidgetState.disabled) && !chargement
            ? CouleursSopi.bordure
            : fond,
      ),
      foregroundColor: WidgetStateProperty.resolveWith(
        (etats) => etats.contains(WidgetState.disabled) && !chargement
            ? CouleursSopi.texteDiscret
            : texte,
      ),
      side: WidgetStatePropertyAll(
        bordure == null
            ? BorderSide.none
            : BorderSide(color: bordure!, width: 1.5),
      ),
      shape: WidgetStatePropertyAll(
        RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(DimensionsSopi.rayonBouton),
        ),
      ),
      textStyle: WidgetStatePropertyAll(
        Theme.of(context).textTheme.labelLarge?.copyWith(
          fontWeight: FontWeight.w800,
          fontSize: 16,
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
    fond: CouleursSopi.bleu,
    texte: CouleursSopi.blanc,
    icone: icone,
    pleineLargeur: pleineLargeur,
  );
}

/// Action secondaire : fond blanc, bordure bleue, texte bleu.
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
    fond: CouleursSopi.blanc,
    texte: CouleursSopi.bleu,
    bordure: CouleursSopi.bleu,
    icone: icone,
    pleineLargeur: pleineLargeur,
  );
}

/// Action sobre ou engageante : fond marine, texte blanc.
class BoutonMarine extends StatelessWidget {
  const BoutonMarine({
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
    fond: CouleursSopi.marine,
    texte: CouleursSopi.blanc,
    icone: icone,
    pleineLargeur: pleineLargeur,
  );
}
