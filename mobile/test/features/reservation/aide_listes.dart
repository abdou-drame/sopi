import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../aide_api.dart';

/// Page de liste « Mes réservations » au format de l'API.
Map<String, Object?> pageReservationsApi(
  List<Map<String, Object?>> elements, {
  int page = 1,
  int derniere = 1,
}) => {
  'data': elements,
  'meta': {
    'current_page': page,
    'last_page': derniere,
    'per_page': 20,
    'total': elements.length,
  },
};

/// Faux serveur qui répond à GET /reservations selon le groupe et la page demandés (clé « groupe:page »).
class AdaptateurListes extends AdaptateurSimule {
  AdaptateurListes(super.routes, this.pages, {this.retenir = false});

  final Map<String, Simulee> pages;

  /// Vrai : les listes restent en attente jusqu'à [liberer] (pour voir l'état de chargement).
  final bool retenir;
  final _porte = Completer<void>();

  void liberer() => _porte.complete();

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (options.method == 'GET' && options.path == '/reservations') {
      requetes.add(options);
      if (retenir) await _porte.future;
      final cle =
          '${options.queryParameters['groupe']}:${options.queryParameters['page'] ?? 1}';
      final reponse = pages[cle];
      if (reponse != null) {
        return ResponseBody.fromString(
          jsonEncode(reponse.corps ?? {}),
          reponse.statut,
          headers: {
            Headers.contentTypeHeader: [Headers.jsonContentType],
          },
        );
      }
    }
    return super.fetch(options, requestStream, cancelFuture);
  }
}
