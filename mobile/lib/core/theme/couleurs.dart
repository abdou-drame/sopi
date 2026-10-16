import 'package:flutter/material.dart';

/// Palette Sopi. Aucune couleur n'est écrite en dur ailleurs que dans ce fichier.
abstract final class CouleursSopi {
  static const bleu = Color(0xFF1D4ED8);
  static const marine = Color(0xFF0F1B3D);
  static const texteSecondaire = Color(0xFF4A5678);
  static const texteDiscret = Color(0xFF64708F);
  static const fond = Color(0xFFF4F7FD);
  static const bordure = Color(0xFFE3E9F6);
  static const bordureForte = Color(0xFFD5DDF0);
  static const champ = Color(0xFFF7F9FF);
  static const bleuPastel = Color(0xFFDBEAFE);

  /// Texte sur fond bleu pastel (bandeaux d'information, encadré du motif).
  static const bleuProfond = Color(0xFF1E3A8A);
  static const blanc = Color(0xFFFFFFFF);

  static const erreurFond = Color(0xFFFEE2E2);
  static const erreurTexte = Color(0xFFB91C1C);
  static const succesFond = Color(0xFFDCFCE7);
  static const succesTexte = Color(0xFF15803D);

  // En-tête dégradé
  static const enteteFond = Color(0xFFEEF4FF);
  static const enteteTacheBleue = Color(0xFFBFDBFE);
  static const enteteTacheIndigo = Color(0xFFC7D2FE);

  // Pastilles d'icône des métiers (bleu, indigo, cyan clairs)
  static const indigoPastel = Color(0xFFE0E7FF);
  static const cyanPastel = Color(0xFFCFFAFE);
  static const indigoTexte = Color(0xFF3730A3);
  static const ardoisePastel = Color(0xFFF1F5F9);
  static const ardoiseTexte = Color(0xFF475569);

  // Texte discret sur fond marine (onglets inactifs)
  static const texteSurMarine = Color(0xFFB8C4E6);

  // Squelettes de chargement
  static const squelette = Color(0xFFE3E9F6);
  static const squeletteClair = Color(0xFFEEF2FB);
}
