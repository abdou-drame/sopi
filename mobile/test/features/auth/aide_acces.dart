import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sopi/core/api/stockage_jeton.dart';
import 'package:sopi/core/config/support.dart';
import 'package:sopi/core/routing/routeur.dart';
import 'package:sopi/core/theme/theme_sopi.dart';
import 'package:sopi/features/inscription_prestataire/stockage_jeton_suivi.dart';

import '../../aide_api.dart';

/// Faux serveur dont les réponses restent en attente tant que [liberer] n'est pas appelé (état « chargement »).
class AdaptateurLent extends AdaptateurSimule {
  AdaptateurLent(super.routes);

  final _porte = Completer<void>();

  void liberer() => _porte.complete();

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    await _porte.future;
    return super.fetch(options, requestStream, cancelFuture);
  }
}

/// Lance l'application complète (vrai routeur, faux serveur) sur [chemin].
/// [supportEmail] est vide par défaut ; [urlsOuvertes] collecte les adresses « ouvertes » (aucun appel système).
Future<ProviderContainer> lancerAcces(
  WidgetTester tester,
  AdaptateurSimule serveur, {
  String? chemin,
  String? jeton,
  String supportEmail = '',
  List<Uri>? urlsOuvertes,
  bool sansAnimation = true,
  StockageJetonSuiviMemoire? stockageSuivi,
  List<Override> surcharges = const [],
  GlobalKey<ScaffoldMessengerState>? messagerie,
}) async {
  final conteneur = ProviderContainer(
    overrides: <Override>[
      ...surchargesApi(serveur, StockageJetonMemoire(jeton)),
      stockageJetonSuiviProvider.overrideWithValue(
        stockageSuivi ?? StockageJetonSuiviMemoire(),
      ),
      supportEmailProvider.overrideWithValue(supportEmail),
      lanceurUrlProvider.overrideWithValue((uri) async {
        urlsOuvertes?.add(uri);
        return true;
      }),
      ...surcharges,
    ],
  );
  addTearDown(conteneur.dispose);
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: conteneur,
      child: Consumer(
        builder: (context, ref, _) => MaterialApp.router(
          scaffoldMessengerKey: messagerie,
          theme: ThemeSopi.construire(policeGoogle: false),
          locale: const Locale('fr', 'FR'),
          supportedLocales: const [Locale('fr', 'FR')],
          localizationsDelegates: const [
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          builder: (context, app) => MediaQuery(
            data: MediaQuery.of(
              context,
            ).copyWith(disableAnimations: sansAnimation),
            child: app!,
          ),
          routerConfig: ref.watch(routeurProvider),
        ),
      ),
    ),
  );
  await tester.pump();
  await tester.pump();
  if (chemin != null) {
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go(chemin);
  }
  await passerTransition(tester);
  return conteneur;
}

/// Laisse finir la transition de page (jusqu'à 800 ms) ; les curseurs clignotent sans fin : pas de pumpAndSettle.
Future<void> passerTransition(WidgetTester tester) => avancer(tester, 1000);

String cheminCourant(WidgetTester tester) =>
    GoRouter.of(tester.element(find.byType(Scaffold).first)).state.uri.path;

/// Avance le temps par pas de 50 ms (les curseurs clignotent sans fin : pas de pumpAndSettle).
Future<void> avancer(WidgetTester tester, int ms) async {
  for (var t = 0; t < ms; t += 50) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

/// Champ de saisie n° [i] (dans l'ordre d'affichage).
Finder champ(int i) => find.byType(TextField).at(i);

Future<void> saisir(WidgetTester tester, int i, String texte) async {
  await tester.ensureVisible(champ(i));
  await tester.enterText(champ(i), texte);
  await tester.pump();
}

Future<void> toucher(WidgetTester tester, Finder cible) async {
  await tester.ensureVisible(cible);
  await tester.pump();
  await tester.tap(cible);
  await avancer(tester, 100);
}
