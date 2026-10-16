import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import 'apparition.dart';

/// Rectangle gris arrondi qui tient la place d'un contenu en cours de chargement.
class Squelette extends StatelessWidget {
  const Squelette({
    super.key,
    this.largeur,
    this.hauteur = 16,
    this.rayon = 12,
  });

  final double? largeur;
  final double hauteur;
  final double rayon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: largeur,
      height: hauteur,
      decoration: BoxDecoration(
        color: CouleursSopi.squelette,
        borderRadius: BorderRadius.circular(rayon),
      ),
    );
  }
}

/// Squelette d'une carte de données (titre + deux lignes).
class SqueletteCarte extends StatelessWidget {
  const SqueletteCarte({super.key, this.hauteur = 110});

  final double hauteur;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: hauteur,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Squelette(largeur: 120, hauteur: 14),
          SizedBox(height: 14),
          Squelette(hauteur: 12),
          SizedBox(height: 8),
          Squelette(largeur: 180, hauteur: 12),
        ],
      ),
    );
  }
}

/// Affiche le [squelette] tant que les données ne sont pas là, puis fond vers le [contenu] (300 ms).
class SqueletteVersContenu extends StatelessWidget {
  const SqueletteVersContenu({
    super.key,
    required this.pret,
    required this.squelette,
    required this.contenu,
  });

  final bool pret;
  final Widget squelette;
  final Widget contenu;

  @override
  Widget build(BuildContext context) {
    final duree = animationsActives(context)
        ? const Duration(milliseconds: 300)
        : Duration.zero;
    return AnimatedSwitcher(
      duration: duree,
      child: KeyedSubtree(
        key: ValueKey(pret),
        child: pret ? contenu : squelette,
      ),
    );
  }
}
