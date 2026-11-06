import 'dart:math' as math;

import 'package:flutter/material.dart';

const _ambre = Color(0xFFF59E0B);
const _cos30 = 0.8660254;

/// Fond de l'écran d'orientation, repris de la vidéo de référence et adapté à la palette claire de Sopi :
/// un champ de blocs isométriques qui se lèvent en vague à l'ouverture, puis « respirent » doucement,
/// avec quelques dessus qui s'allument en ambre. Purement décoratif : à placer derrière le contenu.
class FondBlocsAnime extends StatelessWidget {
  const FondBlocsAnime({
    super.key,
    required this.progression,
    required this.boucle,
  });

  /// 0 → 1 : les blocs se lèvent (une seule fois).
  final Animation<double> progression;

  /// 0 → 1 en boucle : respiration et étincelles.
  final Animation<double> boucle;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _PeintreBlocs(progression, boucle),
      size: Size.infinite,
    );
  }
}

double _alea(int a, int b, int c) {
  final h = (a * 73856093) ^ (b * 19349663) ^ (c * 83492791);
  return (h & 0xFFFF) / 65535;
}

double _lisse(double t, double debut, double fin) {
  if (t <= debut) return 0;
  if (t >= fin) return 1;
  return Curves.easeOutCubic.transform((t - debut) / (fin - debut));
}

class _PeintreBlocs extends CustomPainter {
  _PeintreBlocs(this.progression, this.boucle)
    : super(repaint: Listenable.merge([progression, boucle]));

  final Animation<double> progression;
  final Animation<double> boucle;

  static const _cote = 34.0;
  final _chemin = Path();
  final _peinture = Paint();

  @override
  void paint(Canvas canvas, Size size) {
    final t = progression.value;
    final b = boucle.value;
    const dx = _cote * _cos30;
    final ox = size.width / 2;
    final nbRangs = (size.height / (_cote / 2)).ceil() + 8;
    final nbColonnes = (size.width / dx).ceil() + 4;

    // De l'arrière vers l'avant : les rangs les plus hauts à l'écran d'abord.
    for (var s = -4; s < nbRangs; s++) {
      final y = s * _cote / 2;
      final profondeur = (y / size.height).clamp(0.0, 1.0);
      for (var d = -nbColonnes; d <= nbColonnes; d++) {
        if ((s + d).isOdd) continue;
        final x = ox + d * dx;
        if (x < -dx * 2 || x > size.width + dx * 2) continue;
        final i = (s + d) ~/ 2;
        final j = (s - d) ~/ 2;

        // Vague d'apparition du haut vers le bas, puis respiration continue.
        final apparition = _lisse(
          t,
          0.0 + profondeur * 0.5,
          0.45 + profondeur * 0.5,
        );
        if (apparition <= 0) continue;
        final alea = _alea(i, j, 1);
        final base = _cote * (0.15 + 1.5 * alea * alea);
        final respiration = math.sin((b * 3 - s * 0.12) * 2 * math.pi) * 2.5;
        final hauteur = (base + respiration) * apparition;
        // Plus discret en haut (derrière le titre) qu'en bas.
        final opacite = (0.2 + 0.3 * profondeur) * apparition;

        _bloc(canvas, Offset(x, y), hauteur, opacite);

        // Quelques dessus s'allument en ambre, comme les fenêtres de la vidéo.
        final etincelle = _alea(i, j, 7);
        if (etincelle > 0.9) {
          final lueur = math.max(
            0.0,
            math.sin((b * 2 + etincelle * 10) * 2 * math.pi),
          );
          if (lueur > 0.05) {
            _peinture.color = _ambre.withValues(
              alpha: 0.55 * lueur * apparition,
            );
            canvas.drawCircle(Offset(x, y - hauteur), 3 + 2 * lueur, _peinture);
          }
        }
      }
    }
  }

  /// Bloc isométrique posé en [sol] (centre de sa base), de hauteur [h].
  void _bloc(Canvas canvas, Offset sol, double h, double opacite) {
    const dx = _cote * _cos30;
    const demi = _cote / 2;
    final haut = Offset(sol.dx, sol.dy - h);

    _face(canvas, const Color(0xFFCFDBF6), opacite, [
      Offset(haut.dx - dx, haut.dy),
      Offset(haut.dx, haut.dy + demi),
      Offset(sol.dx, sol.dy + demi),
      Offset(sol.dx - dx, sol.dy),
    ]);
    _face(canvas, const Color(0xFFB9CAF0), opacite, [
      Offset(haut.dx + dx, haut.dy),
      Offset(haut.dx, haut.dy + demi),
      Offset(sol.dx, sol.dy + demi),
      Offset(sol.dx + dx, sol.dy),
    ]);
    _face(canvas, const Color(0xFFE3EBFC), opacite, [
      Offset(haut.dx, haut.dy - demi),
      Offset(haut.dx + dx, haut.dy),
      Offset(haut.dx, haut.dy + demi),
      Offset(haut.dx - dx, haut.dy),
    ]);
  }

  void _face(Canvas canvas, Color couleur, double opacite, List<Offset> pts) {
    _chemin
      ..reset()
      ..moveTo(pts[0].dx, pts[0].dy);
    for (var k = 1; k < pts.length; k++) {
      _chemin.lineTo(pts[k].dx, pts[k].dy);
    }
    _chemin.close();
    canvas.drawPath(
      _chemin,
      _peinture..color = couleur.withValues(alpha: opacite),
    );
  }

  @override
  bool shouldRepaint(covariant _PeintreBlocs ancien) =>
      ancien.progression != progression || ancien.boucle != boucle;
}
