import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../reservation/depot_reservation.dart' show sansRelance;
import 'modeles.dart';

/// Requêtes de l'espace prestataire (`/api/v1/prestataire/...`). Aucune règle métier ici : le serveur décide.
class DepotPrestataire {
  DepotPrestataire(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> _lire(
    String chemin, {
    Map<String, Object?>? requete,
  }) async {
    final reponse = await _dio.get<Object?>(chemin, queryParameters: requete);
    final corps = reponse.data;
    return corps is Map ? Map<String, dynamic>.from(corps) : {};
  }

  /// Solde du portefeuille en FCFA (`GET /prestataire/solde`).
  Future<num> solde() async {
    final corps = await _lire('/prestataire/solde');
    final solde = corps['solde'];
    return solde is num ? solde : num.tryParse('$solde') ?? 0;
  }

  /// Une page de demandes pour un onglet de la liste (statut ou groupe selon l'onglet).
  Future<PageDemandes> demandes(OngletDemandes onglet, {int page = 1}) async {
    final corps = await _lire(
      '/prestataire/reservations',
      requete: {
        'statut': ?onglet.statut,
        'groupe': ?onglet.groupe,
        'page': page,
      },
    );
    return PageDemandes.depuisJson(corps);
  }

  Future<DemandeReservation> demande(String id) async {
    final reponse = await _dio.get<Object?>('/prestataire/reservations/$id');
    return DemandeReservation.depuisJson(reponse.data);
  }

  Future<DemandeReservation> accepter(String id) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/reservations/$id/accepter',
    );
    return DemandeReservation.depuisJson(reponse.data);
  }

  Future<DemandeReservation> refuser(String id, String motif) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/reservations/$id/refuser',
      data: {'motif': motif},
    );
    return DemandeReservation.depuisJson(reponse.data);
  }
}

final depotPrestataireProvider = Provider<DepotPrestataire>(
  (ref) => DepotPrestataire(ref.watch(dioProvider)),
);

/// Solde du portefeuille, relu à l'ouverture du tableau.
final soldeProvider = FutureProvider.autoDispose<num>(
  retry: sansRelance,
  (ref) => ref.watch(depotPrestataireProvider).solde(),
);

/// Nombre de demandes à traiter : `meta.total` de `?statut=en_attente`.
final demandesATraiterProvider = FutureProvider.autoDispose<PageDemandes>(
  retry: sansRelance,
  (ref) =>
      ref.watch(depotPrestataireProvider).demandes(OngletDemandes.aTraiter),
);

/// Une demande relue à la demande (détail).
final detailDemandeProvider = FutureProvider.autoDispose
    .family<DemandeReservation, String>(
      retry: sansRelance,
      (ref, id) => ref.watch(depotPrestataireProvider).demande(id),
    );
