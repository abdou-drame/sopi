import 'package:flutter/material.dart';

import '../format/formateurs.dart';
import 'apparition.dart';

/// Montant qui monte de 0 jusqu'à sa valeur (500 ms) à la première apparition, puis suit les changements.
class CompteurAnime extends StatelessWidget {
  const CompteurAnime({
    super.key,
    required this.valeur,
    this.format = formaterArgent,
    this.style,
    this.duree = const Duration(milliseconds: 500),
  });

  final num valeur;
  final String Function(num) format;
  final TextStyle? style;
  final Duration duree;

  @override
  Widget build(BuildContext context) {
    if (!animationsActives(context)) return Text(format(valeur), style: style);
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: valeur.toDouble()),
      duration: duree,
      curve: Curves.easeOutCubic,
      builder: (context, v, _) => Text(format(v.round()), style: style),
    );
  }
}

/// Barre verticale d'un graphique : grandit depuis zéro (600 ms) après un [delai].
class BarreAnimee extends StatefulWidget {
  const BarreAnimee({
    super.key,
    required this.ratio,
    required this.hauteurMax,
    required this.couleur,
    this.largeur = 18,
    this.delai = Duration.zero,
    this.duree = const Duration(milliseconds: 600),
  });

  /// Entre 0 et 1.
  final double ratio;
  final double hauteurMax;
  final Color couleur;
  final double largeur;
  final Duration delai;
  final Duration duree;

  @override
  State<BarreAnimee> createState() => _BarreAnimeeState();
}

class _BarreAnimeeState extends State<BarreAnimee>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controleur;
  late final Animation<double> _courbe;
  bool _initialise = false;

  @override
  void initState() {
    super.initState();
    final total = widget.delai + widget.duree;
    _controleur = AnimationController(vsync: this, duration: total);
    _courbe = CurvedAnimation(
      parent: _controleur,
      curve: Interval(
        widget.delai.inMicroseconds / total.inMicroseconds,
        1,
        curve: Curves.easeOutCubic,
      ),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialise) return;
    _initialise = true;
    if (animationsActives(context)) {
      _controleur.forward();
    } else {
      _controleur.value = 1;
    }
  }

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cible = widget.hauteurMax * widget.ratio.clamp(0.0, 1.0);
    return AnimatedBuilder(
      animation: _courbe,
      builder: (context, _) => Container(
        width: widget.largeur,
        height: cible * _courbe.value,
        decoration: BoxDecoration(
          color: widget.couleur,
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(widget.largeur / 2.5),
          ),
        ),
      ),
    );
  }
}
