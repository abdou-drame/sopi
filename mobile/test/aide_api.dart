import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:sopi/core/api/client_api.dart';
import 'package:sopi/core/api/stockage_jeton.dart';
import 'package:sopi/core/api_client.dart';
import 'package:sopi/features/auth/session.dart';

/// Réponse simulée d'une route.
class Simulee {
  const Simulee(this.statut, [this.corps, this.entetes = const {}]);

  final int statut;
  final Object? corps;
  final Map<String, String> entetes;
}

/// Faux serveur : aucune requête réseau réelle dans les tests. Clés « METHODE /chemin ».
class AdaptateurSimule implements HttpClientAdapter {
  AdaptateurSimule(this.routes);

  final Map<String, Simulee> routes;
  final List<RequestOptions> requetes = [];

  /// Faux : simule un réseau coupé.
  bool reseauCoupe = false;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requetes.add(options);
    if (reseauCoupe) {
      throw DioException(
        requestOptions: options,
        type: DioExceptionType.connectionError,
      );
    }
    final reponse = routes['${options.method} ${options.path}'];
    if (reponse == null) {
      return ResponseBody.fromString(
        '{"message":"Introuvable."}',
        404,
        headers: _json(),
      );
    }
    return ResponseBody.fromString(
      jsonEncode(reponse.corps ?? {}),
      reponse.statut,
      headers: {
        ..._json(),
        for (final e in reponse.entetes.entries) e.key: [e.value],
      },
    );
  }

  Map<String, List<String>> _json() => {
    Headers.contentTypeHeader: [Headers.jsonContentType],
  };

  @override
  void close({bool force = false}) {}
}

/// Surcharges Riverpod : stockage en mémoire + faux serveur.
List<Override> surchargesApi(
  AdaptateurSimule adaptateur,
  StockageJeton stockage,
) => [
  stockageJetonProvider.overrideWithValue(stockage),
  dioProvider.overrideWith(
    (ref) => creerClientApi(
      stockage: stockage,
      urlBase: 'http://test/api/v1',
      adaptateur: adaptateur,
      surSessionExpiree: () => ref.read(sessionProvider.notifier).expirer(),
    ),
  ),
];
