import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/auth/session.dart';
import 'api/client_api.dart';
import 'api/stockage_jeton.dart';

export 'config/environnement.dart' show urlApi;

/// Stockage du jeton (chiffré sur l'appareil ; remplacé en mémoire dans les tests).
final stockageJetonProvider = Provider<StockageJeton>(
  (ref) => StockageJetonSecurise(),
);

/// Client HTTP unique de l'application. Toute requête vers l'API passe par lui :
/// jeton Bearer, langue française, erreurs converties en [ErreurApi] (voir `api/erreurs.dart`).
final dioProvider = Provider<Dio>((ref) {
  return creerClientApi(
    stockage: ref.watch(stockageJetonProvider),
    // Lecture à la demande : évite la dépendance circulaire client ↔ session.
    surSessionExpiree: () => ref.read(sessionProvider.notifier).expirer(),
  );
});

/// Erreur d'API présentable à l'utilisateur (message en français).
/// Conservée pour l'écran de diagnostic ; le reste de l'application utilise `ErreurApi`.
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
