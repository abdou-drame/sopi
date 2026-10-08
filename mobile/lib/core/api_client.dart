import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// URL de l'API, injectée au build : --dart-define=API_BASE_URL=...
const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://sopi-api.duckdns.org',
);

/// Client HTTP unique de l'application. Toute requête vers l'API passe par lui.
final dioProvider = Provider<Dio>((ref) {
  final base = apiBaseUrl.replaceAll(RegExp(r'/+$'), '');
  return Dio(
    BaseOptions(
      baseUrl: '$base/api/v1',
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      headers: {'Accept': 'application/json'},
    ),
  );
});

/// Erreur d'API présentable à l'utilisateur (message en français).
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

/// Convertit une erreur dio en [ApiException] au message clair.
ApiException traduireErreur(DioException e) {
  final statut = e.response?.statusCode;
  if (statut != null) {
    return ApiException(
      'Le serveur a répondu avec une erreur ($statut).',
      statusCode: statut,
    );
  }
  return ApiException('Impossible de joindre le serveur.');
}
