import 'package:flutter/material.dart';

import '../animation/apparition.dart';
import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

/// Carte blanche à bordure fine. Apparaît par défaut en fondu + glissement (désactivable avec [animer]),
/// sauf quand un parent gère déjà l'apparition (cascade, liste).
class CarteSopi extends StatelessWidget {
  const CarteSopi({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.onTap,
    this.animer = true,
    this.delai = Duration.zero,
    this.rayon = DimensionsSopi.rayonCarte,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final bool animer;
  final Duration delai;
  final double rayon;

  @override
  Widget build(BuildContext context) {
    final forme = BorderRadius.circular(rayon);
    Widget carte = Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: forme,
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      clipBehavior: Clip.antiAlias,
      child: onTap == null
          ? Padding(padding: padding, child: child)
          : InkWell(
              onTap: onTap,
              child: Padding(padding: padding, child: child),
            ),
    );
    if (animer && !ApparitionHeritee.dans(context)) {
      carte = Apparition(delai: delai, child: carte);
    }
    return carte;
  }
}
