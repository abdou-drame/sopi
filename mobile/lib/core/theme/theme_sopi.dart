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
      secondary: CouleursSopi.bleu,
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

    // Champs sans bordure (maquettes) : fond #f1f3f8 uniquement.
    final bordure = OutlineInputBorder(
      borderRadius: BorderRadius.circular(DimensionsSopi.rayonChamp),
      borderSide: BorderSide.none,
    );
    OutlineInputBorder bordureErreur([double epaisseur = 1.5]) =>
        OutlineInputBorder(
          borderRadius: BorderRadius.circular(DimensionsSopi.rayonChamp),
          borderSide: BorderSide(
            color: CouleursSopi.erreurTexte,
            width: epaisseur,
          ),
        );

    return base.copyWith(
      textTheme: texte,
      primaryTextTheme: texte,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: CouleursSopi.texte,
        elevation: 0,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: CouleursSopi.champ,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 16,
        ),
        border: bordure,
        enabledBorder: bordure,
        focusedBorder: bordure,
        errorBorder: bordureErreur(),
        focusedErrorBorder: bordureErreur(),
        hintStyle: texte.bodyMedium?.copyWith(
          color: CouleursSopi.texteSecondaire,
        ),
      ),
      cardTheme: CardThemeData(
        color: CouleursSopi.blanc,
        elevation: 1,
        margin: EdgeInsets.zero,
        shadowColor: const Color(0x0F12172B),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(DimensionsSopi.rayonCarte),
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: CouleursSopi.fondSecondaire,
        space: 1,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: CouleursSopi.blanc,
        elevation: 4,
        contentTextStyle: texte.bodyMedium?.copyWith(color: CouleursSopi.texte),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
    );
  }

  static TextTheme _texte(TextTheme t) {
    TextStyle? s(TextStyle? style, FontWeight poids, Color couleur) =>
        style?.copyWith(fontWeight: poids, color: couleur);
    return t.copyWith(
      displaySmall: s(t.displaySmall, FontWeight.w800, CouleursSopi.texte),
      headlineMedium: s(t.headlineMedium, FontWeight.w800, CouleursSopi.texte),
      headlineSmall: s(t.headlineSmall, FontWeight.w800, CouleursSopi.texte),
      titleLarge: s(t.titleLarge, FontWeight.w800, CouleursSopi.texte),
      titleMedium: s(t.titleMedium, FontWeight.w700, CouleursSopi.texte),
      titleSmall: s(t.titleSmall, FontWeight.w700, CouleursSopi.texte),
      bodyLarge: s(t.bodyLarge, FontWeight.w500, CouleursSopi.texte),
      bodyMedium: s(
        t.bodyMedium,
        FontWeight.w400,
        CouleursSopi.texteSecondaire,
      ),
      bodySmall: s(t.bodySmall, FontWeight.w400, CouleursSopi.texteSecondaire),
      labelLarge: s(t.labelLarge, FontWeight.w700, CouleursSopi.texte),
      labelMedium: s(
        t.labelMedium,
        FontWeight.w600,
        CouleursSopi.texteSecondaire,
      ),
    );
  }
}
