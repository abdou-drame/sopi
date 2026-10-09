import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/api_client.dart';
import 'package:sopi/features/accueil/accueil_page.dart';

/// Faux adaptateur réseau : renvoie une réponse simulée, ou une erreur, ou ne répond jamais.
class _FauxReseau implements HttpClientAdapter {
  _FauxReseau({
    this.statut,
    this.corps,
    this.injoignable = false,
    this.attente,
  });

  final int? statut;
  final Map<String, dynamic>? corps;
  final bool injoignable;
  final Completer<void>? attente;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (attente != null) await attente!.future;
    if (injoignable) {
      throw DioException.connectionError(
        requestOptions: options,
        reason: 'réseau coupé',
      );
    }
    return ResponseBody.fromString(
      jsonEncode(corps ?? {}),
      statut ?? 200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Widget _app(HttpClientAdapter adaptateur) {
  final dio = Dio(BaseOptions(baseUrl: 'http://test/api/v1'))
    ..httpClientAdapter = adaptateur;
  return ProviderScope(
    overrides: [dioProvider.overrideWithValue(dio)],
    child: const MaterialApp(home: AccueilPage()),
  );
}

void main() {
  testWidgets("affiche le chargement tant que l'API n'a pas répondu", (
    tester,
  ) async {
    final attente = Completer<void>();
    await tester.pumpWidget(_app(_FauxReseau(attente: attente)));

    expect(find.text("Chargement de l'état de l'API…"), findsOneWidget);

    attente.complete();
    await tester.pumpAndSettle();
  });

  testWidgets("affiche l'API et la base à OK quand tout va bien", (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        _FauxReseau(
          corps: {
            'status': 'ok',
            'app': 'Sopi',
            'version': '0.1.0',
            'database': 'ok',
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Base de données'), findsOneWidget);
    expect(find.text('OK'), findsNWidgets(2));
    expect(find.text('0.1.0'), findsOneWidget);
  });

  testWidgets('signale la base en erreur quand elle est en panne', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        _FauxReseau(
          corps: {'status': 'ok', 'version': '0.1.0', 'database': 'erreur'},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('OK'), findsOneWidget);
    expect(find.text('Erreur'), findsOneWidget);
  });

  testWidgets("affiche une erreur claire quand l'API répond 503", (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(_FauxReseau(statut: 503, corps: {'database': 'erreur'})),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining("L'API est indisponible"), findsOneWidget);
    expect(find.textContaining('(503)'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);
  });

  testWidgets('affiche une erreur claire quand le serveur est injoignable', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_FauxReseau(injoignable: true)));
    await tester.pumpAndSettle();

    expect(
      find.textContaining('Impossible de joindre le serveur'),
      findsOneWidget,
    );
    expect(find.text('Réessayer'), findsOneWidget);
  });
}
