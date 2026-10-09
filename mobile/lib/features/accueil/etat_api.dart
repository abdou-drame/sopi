import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';

/// État de l'API tel que renvoyé par GET /api/v1/health.
class EtatApi {
  const EtatApi({
    required this.status,
    required this.database,
    required this.version,
  });

  factory EtatApi.fromJson(Map<String, dynamic> json) => EtatApi(
    status: json['status'] as String? ?? 'erreur',
    database: json['database'] as String? ?? 'erreur',
    version: json['version'] as String? ?? '',
  );

  final String status;
  final String database;
  final String version;

  bool get apiOk => status == 'ok';
  bool get baseOk => database == 'ok';
}

/// Appelle GET /api/v1/health.
final etatApiProvider = FutureProvider.autoDispose<EtatApi>(
  retry: (nombreEssais, erreur) =>
      null, // pas de relance automatique : bouton « Réessayer »
  (ref) async {
    final dio = ref.watch(dioProvider);
    try {
      final reponse = await dio.get<Map<String, dynamic>>('/health');
      return EtatApi.fromJson(reponse.data ?? const {});
    } on DioException catch (e) {
      throw traduireErreur(e);
    }
  },
);
