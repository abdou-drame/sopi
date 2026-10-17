import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../reservation/depot_reservation.dart'
    show horlogeProvider, sansRelance;
import '../reservation/formats.dart' show formaterJourApi;
import 'modeles.dart';
import 'modeles_portefeuille.dart';

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

  /// Solde du portefeuille en FCFA et limites d'un retrait (`GET /prestataire/solde`).
  Future<SoldePortefeuille> solde() async =>
      SoldePortefeuille.depuisJson(await _lire('/prestataire/solde'));

  /// Une page de l'historique du portefeuille, du plus récent au plus ancien (20 par page).
  Future<PageMouvements> mouvements({int page = 1}) async =>
      PageMouvements.depuisJson(
        await _lire(
          '/prestataire/portefeuille/mouvements',
          requete: {'page': page},
        ),
      );

  /// Demande un retrait. Le serveur débite, verse et décide (422 par champ, 429, 503 : versement refusé, solde intact).
  Future<RetraitCree> demanderRetrait({
    required int montant,
    required String telephone,
    required OperateurRetrait operateur,
  }) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/retraits',
      data: {
        'montant': montant,
        'telephone_versement': telephone,
        'operateur': operateur.code,
      },
    );
    return RetraitCree.depuisJson(reponse.data);
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

  /// Démarre la prestation avec le code du client. 422 si le code est faux, 429 après 5 essais par minute.
  Future<DemandeReservation> demarrer(String id, String code) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/reservations/$id/demarrer',
      data: {'code': code},
    );
    return DemandeReservation.depuisJson(reponse.data);
  }

  /// Termine la prestation : le serveur crédite le portefeuille et renvoie les montants du crédit.
  Future<CreditPrestation> terminer(String id) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/reservations/$id/terminer',
    );
    return CreditPrestation.depuisJson(reponse.data);
  }

  /// Annule une réservation acceptée ou confirmée (le client est remboursé par le serveur).
  Future<DemandeReservation> annuler(String id, String motif) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/reservations/$id/annuler',
      data: {'motif': motif},
    );
    return DemandeReservation.depuisJson(reponse.data);
  }

  /// Toutes les prestations du prestataire, actives et désactivées (`GET /prestataire/prestations`, sans pagination).
  Future<List<PrestationService>> prestations() async {
    final corps = await _lire('/prestataire/prestations');
    final liste = corps['data'];
    return liste is List
        ? [for (final e in liste) PrestationService.depuisJson(e)]
        : const [];
  }

  Future<PrestationService> creerPrestation({
    required String nom,
    required int dureeMinutes,
    required num prix,
  }) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/prestations',
      data: {'nom': nom, 'duree_minutes': dureeMinutes, 'prix': prix},
    );
    return PrestationService.depuisJson(reponse.data);
  }

  /// Modification partielle : seuls les champs fournis sont envoyés. Il n'existe pas de suppression : on désactive (`actif`).
  Future<PrestationService> modifierPrestation(
    String id, {
    String? nom,
    int? dureeMinutes,
    num? prix,
    bool? actif,
  }) async {
    final reponse = await _dio.patch<Object?>(
      '/prestataire/prestations/$id',
      data: {
        'nom': ?nom,
        'duree_minutes': ?dureeMinutes,
        'prix': ?prix,
        'actif': ?actif,
      },
    );
    return PrestationService.depuisJson(reponse.data);
  }

  /// Créneaux de [du] à [au] inclus, au format AAAA-MM-JJ (`GET /prestataire/agenda`, 62 jours au plus).
  Future<List<CreneauAgenda>> agenda(String du, String au) async {
    final corps = await _lire(
      '/prestataire/agenda',
      requete: {'du': du, 'au': au},
    );
    final liste = corps['data'];
    return liste is List
        ? [for (final e in liste) CreneauAgenda.depuisJson(e)]
        : const [];
  }

  /// Crée les créneaux d'une plage ; renvoie le nombre de créneaux créés.
  Future<int> creerPlage(PlageHoraire plage) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/agenda/plages',
      data: {
        'plages': [plage.versJson()],
      },
    );
    final corps = reponse.data;
    final nombre = corps is Map ? corps['nombreCreneaux'] : null;
    return nombre is num ? nombre.toInt() : 0;
  }

  /// Retire un créneau libre (409 s'il n'est plus libre).
  Future<void> supprimerCreneau(String id) =>
      _dio.delete<Object?>('/prestataire/agenda/creneaux/$id');

  /// Déclare une indisponibilité (dates « AAAA-MM-JJ HH:MM »). Le serveur annule et rembourse les réservations de la période ;
  /// 409 si une prestation est en cours.
  Future<BilanIndisponibilite> declarerIndisponibilite({
    required String debut,
    required String fin,
  }) async {
    final reponse = await _dio.post<Object?>(
      '/prestataire/agenda/indisponibilites',
      data: {'debut': debut, 'fin': fin},
    );
    return BilanIndisponibilite.depuisJson(reponse.data);
  }
}

final depotPrestataireProvider = Provider<DepotPrestataire>(
  (ref) => DepotPrestataire(ref.watch(dioProvider)),
);

/// Solde et limites de retrait, relus à l'ouverture du tableau, du portefeuille et du retrait.
final soldeProvider = FutureProvider.autoDispose<SoldePortefeuille>(
  retry: sansRelance,
  (ref) => ref.watch(depotPrestataireProvider).solde(),
);

/// Compteur que l'onglet Portefeuille écoute pour relire son historique (après un retrait ou un crédit).
class VersionPortefeuille extends Notifier<int> {
  @override
  int build() => 0;

  void incrementer() => state++;
}

final versionPortefeuilleProvider = NotifierProvider<VersionPortefeuille, int>(
  VersionPortefeuille.new,
);

/// Le solde ou l'historique a changé côté serveur : relit les deux.
void rafraichirPortefeuille(WidgetRef ref) {
  ref.invalidate(soldeProvider);
  ref.read(versionPortefeuilleProvider.notifier).incrementer();
}

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

/// Services et tarifs du prestataire, relus à l'ouverture de l'écran.
final prestationsProvider = FutureProvider.autoDispose<List<PrestationService>>(
  retry: sansRelance,
  (ref) => ref.watch(depotPrestataireProvider).prestations(),
);

/// Jours d'agenda consultés (le serveur accepte 62 jours au plus).
const joursAgendaConsultes = 62;

/// Créneaux à venir du prestataire, relus à l'ouverture de l'écran des disponibilités.
final creneauxProvider = FutureProvider.autoDispose<List<CreneauAgenda>>(
  retry: sansRelance,
  (ref) {
    final aujourdhui = ref.watch(horlogeProvider)().toUtc();
    final du = DateTime.utc(aujourdhui.year, aujourdhui.month, aujourdhui.day);
    return ref
        .watch(depotPrestataireProvider)
        .agenda(
          formaterJourApi(du),
          formaterJourApi(du.add(const Duration(days: joursAgendaConsultes))),
        );
  },
);
