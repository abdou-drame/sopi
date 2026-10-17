import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/widgets/liste_infinie.dart';
import 'modeles_litige.dart';
import 'modeles_notification.dart';

/// Requêtes du module Compte client (litiges, notifications, profil).
class DepotCompte {
  DepotCompte(this._dio);

  final Dio _dio;

  /// POST /reservations/{id}/litiges en multipart (`motif`, `description`, `preuves[]`).
  Future<void> ouvrirLitige(
    String reservationId, {
    required String motif,
    required String description,
    List<PhotoChoisie> preuves = const [],
  }) async {
    final formulaire = FormData.fromMap({
      'motif': motif,
      'description': description,
      'preuves[]': [
        for (final p in preuves)
          MultipartFile.fromBytes(
            p.octets,
            filename: p.nom,
            contentType: DioMediaType.parse(p.typeMime),
          ),
      ],
    });
    await _dio.post<Object?>(
      '/reservations/$reservationId/litiges',
      data: formulaire,
    );
  }

  Future<PageListe<LitigeClient>> litiges(int page) async {
    final reponse = await _dio.get<Object?>(
      '/litiges',
      queryParameters: {'page': page},
    );
    return LitigeClient.pageDepuisJson(reponse.data);
  }

  Future<LitigeClient> litige(String id) async {
    final reponse = await _dio.get<Object?>('/litiges/$id');
    final corps = reponse.data as Map;
    final j = corps['data'] is Map ? corps['data'] as Map : corps;
    return LitigeClient.depuisJson(Map<String, dynamic>.from(j));
  }

  Future<PageListe<NotificationClient>> notifications(int page) async {
    final reponse = await _dio.get<Object?>(
      '/notifications',
      queryParameters: {'page': page},
    );
    return NotificationClient.pageDepuisJson(reponse.data);
  }

  Future<void> marquerLue(String id) async {
    await _dio.post<Object?>('/notifications/$id/lire');
  }

  Future<void> toutMarquerLu() async {
    await _dio.post<Object?>('/notifications/lire-tout');
  }

  /// Octets d'une preuve ; le jeton est ajouté par le client HTTP commun.
  Future<Uint8List> preuve(String litigeId, String preuveId) async {
    final reponse = await _dio.get<List<int>>(
      '/litiges/$litigeId/preuves/$preuveId',
      options: Options(responseType: ResponseType.bytes),
    );
    return Uint8List.fromList(reponse.data ?? const []);
  }
}

final depotCompteProvider = Provider<DepotCompte>(
  (ref) => DepotCompte(ref.watch(dioProvider)),
);

final litigeProvider = FutureProvider.autoDispose.family<LitigeClient, String>(
  (ref, id) => ref.watch(depotCompteProvider).litige(id),
);

final preuveProvider = FutureProvider.autoDispose
    .family<Uint8List, ({String litigeId, String preuveId})>(
      (ref, c) => ref.watch(depotCompteProvider).preuve(c.litigeId, c.preuveId),
    );
