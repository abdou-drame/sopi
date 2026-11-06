import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import 'apparition.dart';

/// Rectangle gris arrondi qui tient la place d'un contenu en cours de chargement.
class Squelette extends StatefulWidget {
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
  State<Squelette> createState() => _EtatSquelette();
}

/// Un reflet clair balaie le rectangle. Le balayage est fini (6 passages) pour ne jamais bloquer les tests.
class _EtatSquelette extends State<Squelette>
    with SingleTickerProviderStateMixin {
  static const _passages = 6;
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1300 * _passages),
  );
  bool _lance = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_lance && animationsActives(context)) {
      _lance = true;
      _c.forward();
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) {
        final p = (_c.value * _passages) % 1.0;
        return Container(
          width: widget.largeur,
          height: widget.hauteur,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(widget.rayon),
            gradient: _c.isAnimating
                ? LinearGradient(
                    colors: const [
                      CouleursSopi.squelette,
                      CouleursSopi.squeletteClair,
                      CouleursSopi.squelette,
                    ],
                    stops: const [0.0, 0.5, 1.0],
                    transform: _Glisse(p),
                  )
                : null,
            color: _c.isAnimating ? null : CouleursSopi.squelette,
          ),
        );
      },
    );
  }
}

class _Glisse extends GradientTransform {
  const _Glisse(this.p);
  final double p;

  @override
  Matrix4? transform(Rect bounds, {TextDirection? textDirection}) =>
      Matrix4.translationValues(bounds.width * (2 * p - 1), 0, 0);
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
        boxShadow: OmbresSopi.carte,
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
