import 'package:flutter/material.dart';

/// Dessine une coche qui se trace progressivement ([progression] de 0 à 1).
class TraceCoche extends CustomPainter {
  const TraceCoche({required this.progression, required this.couleur});

  final double progression;
  final Color couleur;

  @override
  void paint(Canvas canvas, Size taille) {
    final chemin = Path()
      ..moveTo(taille.width * 0.27, taille.height * 0.52)
      ..lineTo(taille.width * 0.43, taille.height * 0.68)
      ..lineTo(taille.width * 0.74, taille.height * 0.34);
    final pinceau = Paint()
      ..color = couleur
      ..style = PaintingStyle.stroke
      ..strokeWidth = 8
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final metrique = chemin.computeMetrics().first;
    canvas.drawPath(
      metrique.extractPath(0, metrique.length * progression.clamp(0.0, 1.0)),
      pinceau,
    );
  }

  @override
  bool shouldRepaint(TraceCoche ancien) =>
      ancien.progression != progression || ancien.couleur != couleur;
}
