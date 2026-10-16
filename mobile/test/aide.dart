import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:sopi/core/theme/theme_sopi.dart';

/// Enveloppe un widget dans une application de test (thème Sopi sans téléchargement de police).
/// [sansAnimation] reproduit le réglage d'accessibilité « supprimer les animations ».
Widget enrober(Widget child, {bool sansAnimation = false, Size? taille}) {
  return MaterialApp(
    theme: ThemeSopi.construire(policeGoogle: false),
    builder: (context, app) => MediaQuery(
      data: MediaQuery.of(context).copyWith(disableAnimations: sansAnimation),
      child: app!,
    ),
    home: Scaffold(body: child),
  );
}

/// Opacité effective d'un widget (produit des Opacity ancêtres).
double opacite(WidgetTester tester, Finder cible) {
  var total = 1.0;
  for (final o in tester.widgetList<Opacity>(
    find.ancestor(of: cible, matching: find.byType(Opacity)),
  )) {
    total *= o.opacity;
  }
  return total;
}
