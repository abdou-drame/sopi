import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'couleurs.dart';
import 'dimensions.dart';

/// Nom de famille utilisé quand la police Google n'est pas chargée (tests).
const policeRepli = 'Roboto';

abstract final class ThemeSopi {
  /// [policeGoogle] à false dans les tests : évite tout téléchargement de police.
  static ThemeData construire({bool policeGoogle = true}) {
    final schema = ColorScheme.fromSeed(
      seedColor: CouleursSopi.bleu,
      primary: CouleursSopi.bleu,
      onPrimary: CouleursSopi.blanc,
      secondary: CouleursSopi.marine,
      error: CouleursSopi.erreurTexte,
      surface: CouleursSopi.blanc,
      brightness: Brightness.light,
    );

    final base = ThemeData(
      useMaterial3: true,
      colorScheme: schema,
      scaffoldBackgroundColor: CouleursSopi.fond,
      fontFamily: policeGoogle ? null : policeRepli,
    );
    final texte = _texte(
      policeGoogle
          ? GoogleFonts.plusJakartaSansTextTheme(base.textTheme)
          : base.textTheme,
    );

    OutlineInputBorder bordure(Color couleur, [double epaisseur = 1.2]) =>
        OutlineInputBorder(
          borderRadius: BorderRadius.circular(DimensionsSopi.rayonChamp),
          borderSide: BorderSide(color: couleur, width: epaisseur),
        );

    return base.copyWith(
      textTheme: texte,
      primaryTextTheme: texte,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: CouleursSopi.marine,
        elevation: 0,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: CouleursSopi.champ,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 16,
        ),
        border: bordure(CouleursSopi.bordureForte),
        enabledBorder: bordure(CouleursSopi.bordureForte),
        focusedBorder: bordure(CouleursSopi.bleu, 2),
        errorBorder: bordure(CouleursSopi.erreurTexte),
        focusedErrorBorder: bordure(CouleursSopi.erreurTexte, 2),
        hintStyle: texte.bodyMedium?.copyWith(color: CouleursSopi.texteDiscret),
      ),
      cardTheme: CardThemeData(
        color: CouleursSopi.blanc,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(DimensionsSopi.rayonCarte),
          side: const BorderSide(color: CouleursSopi.bordure),
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: CouleursSopi.bordure,
        space: 1,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: CouleursSopi.marine,
        contentTextStyle: texte.bodyMedium?.copyWith(color: CouleursSopi.blanc),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
    );
  }

  static TextTheme _texte(TextTheme t) {
    TextStyle? s(TextStyle? style, FontWeight poids, Color couleur) =>
        style?.copyWith(fontWeight: poids, color: couleur);
    return t.copyWith(
      displaySmall: s(t.displaySmall, FontWeight.w800, CouleursSopi.marine),
      headlineMedium: s(t.headlineMedium, FontWeight.w800, CouleursSopi.marine),
      headlineSmall: s(t.headlineSmall, FontWeight.w800, CouleursSopi.marine),
      titleLarge: s(t.titleLarge, FontWeight.w800, CouleursSopi.marine),
      titleMedium: s(t.titleMedium, FontWeight.w700, CouleursSopi.marine),
      titleSmall: s(t.titleSmall, FontWeight.w700, CouleursSopi.marine),
      bodyLarge: s(t.bodyLarge, FontWeight.w500, CouleursSopi.marine),
      bodyMedium: s(
        t.bodyMedium,
        FontWeight.w400,
        CouleursSopi.texteSecondaire,
      ),
      bodySmall: s(t.bodySmall, FontWeight.w400, CouleursSopi.texteDiscret),
      labelLarge: s(t.labelLarge, FontWeight.w700, CouleursSopi.marine),
      labelMedium: s(
        t.labelMedium,
        FontWeight.w600,
        CouleursSopi.texteSecondaire,
      ),
    );
  }
}
