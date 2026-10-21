import 'dart:math' as math;

import 'package:flutter/widgets.dart';

/// Hauteur de la barre d'état supposée par les maquettes (390 × 844) : leurs positions verticales en partent.
const hautMaquette = 24.0;

/// Espace réservé en haut de l'écran : la barre d'état de l'appareil, au moins [hautMaquette].
double hautEcran(BuildContext context) =>
    math.max(MediaQuery.paddingOf(context).top, hautMaquette);
