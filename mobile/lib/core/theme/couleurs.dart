import 'package:flutter/material.dart';

/// Palette Sopi (maquettes `mobile/maquettes`). Aucune couleur n'est écrite en dur ailleurs que dans ce fichier.
abstract final class CouleursSopi {
  static const bleu = Color(0xFF1D4ED8);
  static const blanc = Color(0xFFFFFFFF);

  /// Fond de tous les écrans : uni, sans dégradé.
  static const fond = Color(0xFFF5F6FA);

  /// Texte principal et icônes des boutons ronds du haut.
  static const texte = Color(0xFF12172B);
  static const texteSecondaire = Color(0xFF6B7287);

  /// Icônes inactives (barre du bas, chevrons).
  static const iconeInactive = Color(0xFF8A90A2);

  /// Fond des boutons secondaires, des onglets segmentés et des puces non choisies.
  static const fondSecondaire = Color(0xFFE9ECF3);

  /// Fond des champs de saisie (sans bordure).
  static const champ = Color(0xFFF1F3F8);

  static const bleuPastel = Color(0xFFDBEAFE);

  /// Texte sur fond bleu pastel (bandeaux d'information, encadré du motif).
  static const bleuProfond = Color(0xFF1E3A8A);

  static const erreurFond = Color(0xFFFEE2E2);
  static const erreurTexte = Color(0xFFB91C1C);
  static const succesFond = Color(0xFFDCFCE7);
  static const succesTexte = Color(0xFF15803D);

  // Pastilles d'icône des métiers (bleu, indigo, cyan clairs)
  static const indigoPastel = Color(0xFFE0E7FF);
  static const cyanPastel = Color(0xFFCFFAFE);
  static const indigoTexte = Color(0xFF3730A3);
  static const ardoisePastel = Color(0xFFF1F5F9);
  static const ardoiseTexte = Color(0xFF475569);

  // Délai de réponse qui court (« Il reste 11 h 20 »)
  static const ambre = Color(0xFFB45309);
  static const ambrePastel = Color(0xFFFEF3C7);
  static const ambreProfond = Color(0xFF92400E);

  // Squelettes de chargement
  static const squelette = Color(0xFFE9ECF3);
  static const squeletteClair = Color(0xFFF1F3F8);

  // --- Transitoire : anciens jetons, retirés écran par écran (étapes 3 à 6 de la refonte). ---
  static const bordure = Color(0xFFE9ECF3);
  static const bordureForte = Color(0xFFE9ECF3);
  static const enteteFond = Color(0xFFF5F6FA);
  static const enteteTacheBleue = Color(0xFFDBEAFE);
  static const enteteTacheIndigo = Color(0xFFE0E7FF);
  static const separateurLigne = Color(0xFFE9ECF3);
  static const texteSurMarine = Color(0xFF6B7287);
}

/// Ombres des maquettes.
abstract final class OmbresSopi {
  /// Cartes : 0 1px 3px rgba(18,23,43,.06).
  static const carte = [
    BoxShadow(color: Color(0x0F12172B), blurRadius: 3, offset: Offset(0, 1)),
  ];

  /// Boutons ronds du haut : 0 2px 8px rgba(18,23,43,.08).
  static const boutonRond = [
    BoxShadow(color: Color(0x1412172B), blurRadius: 8, offset: Offset(0, 2)),
  ];

  /// Barre du bas : 0 8px 24px rgba(18,23,43,.12).
  static const barreBas = [
    BoxShadow(color: Color(0x1F12172B), blurRadius: 24, offset: Offset(0, 8)),
  ];
}
