import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import 'modeles.dart';

/// Horloge de l'application (UTC), remplaçable dans les tests.
final horlogeProvider = Provider<DateTime Function()>((ref) => DateTime.now);

/// Filtres de la liste des prestataires, tels que l'API les attend.
class FiltresPrestataires {
  const FiltresPrestataires({
    this.recherche = '',
    this.quartier,
    this.noteMin,
    this.prixMin,
    this.prixMax,
    this.disponible = false,
    this.tri = TriPrestataires.nom,
  });

  final String recherche;
  final String? quartier;
  final double? noteMin;
  final int? prixMin;
  final int? prixMax;
  final bool disponible;
  final TriPrestataires tri;

  bool get actifs =>
      recherche.trim().isNotEmpty ||
      quartier != null ||
      noteMin != null ||
      prixMin != null ||
      prixMax != null ||
      disponible;

  FiltresPrestataires copie({
    String? recherche,
    Object? quartier = _inchange,
    Object? noteMin = _inchange,
    Object? prixMin = _inchange,
    Object? prixMax = _inchange,
    bool? disponible,
    TriPrestataires? tri,
  }) => FiltresPrestataires(
    recherche: recherche ?? this.recherche,
    quartier: identical(quartier, _inchange)
        ? this.quartier
        : quartier as String?,
    noteMin: identical(noteMin, _inchange) ? this.noteMin : noteMin as double?,
    prixMin: identical(prixMin, _inchange) ? this.prixMin : prixMin as int?,
    prixMax: identical(prixMax, _inchange) ? this.prixMax : prixMax as int?,
    disponible: disponible ?? this.disponible,
    tri: tri ?? this.tri,
  );

  /// Efface les filtres (la recherche, le quartier, la note et le prix) mais garde le tri.
  FiltresPrestataires sansFiltres() => FiltresPrestataires(tri: tri);
}

const Object _inchange = Object();

enum TriPrestataires {
  nom('nom', 'Nom (A → Z)'),
  prixAsc('prix_asc', 'Prix croissant'),
  prixDesc('prix_desc', 'Prix décroissant'),
  note('note', 'Mieux notés');

  const TriPrestataires(this.valeurApi, this.libelle);

  final String valeurApi;
  final String libelle;
}

/// Toutes les requêtes du module Réservation passent par ici (jamais de règle métier côté mobile).
class DepotReservation {
  DepotReservation(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> _lire(
    String chemin, {
    Map<String, Object?>? requete,
  }) async {
    final reponse = await _dio.get<Object?>(chemin, queryParameters: requete);
    final corps = reponse.data;
    return corps is Map ? Map<String, dynamic>.from(corps) : {};
  }

  /// Prénom du client connecté (GET /me).
  Future<String> prenomClient() async {
    final corps = await _lire('/me');
    final j = corps['data'] is Map ? corps['data'] as Map : corps;
    return (j['prenom'] ?? '').toString();
  }

  Future<List<Metier>> metiers() async {
    final corps = await _lire('/metiers');
    return [
      for (final e in (corps['data'] as List? ?? const []))
        if (e is Map) Metier.depuisJson(Map<String, dynamic>.from(e)),
    ];
  }

  /// Quartiers où un prestataire actif est installé (recherche côté client). Avec [tous] : liste complète du
  /// référentiel, proposée à l'inscription d'un prestataire (GET /quartiers?tous=1).
  Future<List<String>> quartiers({bool tous = false}) async {
    final corps = await _lire(
      '/quartiers',
      requete: tous ? {'tous': '1'} : null,
    );
    return [for (final e in (corps['data'] as List? ?? const [])) e.toString()];
  }

  /// Nombre de notifications non lues (GET /notifications/non-lues/compteur).
  Future<int> notificationsNonLues() async {
    final corps = await _lire('/notifications/non-lues/compteur');
    final n = corps['nonLues'];
    return n is num ? n.toInt() : 0;
  }

  Future<ReservationAVenir?> prochaineReservation() async {
    final corps = await _lire('/reservations', requete: {'groupe': 'a_venir'});
    return ReservationAVenir.premiereDepuisJson(corps);
  }

  Future<PagePrestataires> prestataires({
    String? metier,
    FiltresPrestataires filtres = const FiltresPrestataires(),
    int page = 1,
  }) async {
    final recherche = filtres.recherche.trim();
    final corps = await _lire(
      '/prestataires',
      requete: {
        'metier': ?metier,
        if (recherche.isNotEmpty) 'q': recherche,
        'quartier': ?filtres.quartier,
        'note_min': ?filtres.noteMin,
        'prix_min': ?filtres.prixMin,
        'prix_max': ?filtres.prixMax,
        if (filtres.disponible) 'disponible': 1,
        'tri': filtres.tri.valeurApi,
        'page': page,
      },
    );
    return PagePrestataires.depuisJson(corps);
  }

  Future<ProfilPrestataire> profil(String id) async {
    final reponse = await _dio.get<Object?>('/prestataires/$id');
    return ProfilPrestataire.depuisJson(reponse.data);
  }

  Future<PageAvis> avis(String id, {int page = 1}) async {
    final corps = await _lire(
      '/prestataires/$id/avis',
      requete: {'page': page},
    );
    return PageAvis.depuisJson(corps);
  }

  /// Créneaux libres entre deux jours (dates `AAAA-MM-JJ`, bornes comprises).
  Future<List<CreneauLibre>> agenda(
    String id, {
    required String du,
    required String au,
  }) async {
    final corps = await _lire(
      '/prestataires/$id/agenda',
      requete: {'du': du, 'au': au},
    );
    return [
      for (final e in (corps['data'] as List? ?? const []))
        if (e is Map) CreneauLibre.depuisJson(Map<String, dynamic>.from(e)),
    ];
  }

  Future<VerrouCreneau> verrouiller(String creneauId) async {
    final reponse = await _dio.post<Object?>('/creneaux/$creneauId/verrou');
    return VerrouCreneau.depuisJson(reponse.data);
  }

  Future<void> liberer(String creneauId) async {
    await _dio.delete<Object?>('/creneaux/$creneauId/verrou');
  }

  Future<ReservationCreee> demander({
    required String creneauId,
    required String prestationId,
  }) async {
    final reponse = await _dio.post<Object?>(
      '/reservations',
      data: {'creneau_id': creneauId, 'prestation_id': prestationId},
    );
    return ReservationCreee.depuisJson(reponse.data);
  }

  /// Une page de « Mes réservations » ([groupe] : `a_venir`, `terminees` ou `annulees`).
  Future<PageReservations> reservations(String groupe, {int page = 1}) async {
    final corps = await _lire(
      '/reservations',
      requete: {'groupe': groupe, 'page': page},
    );
    return PageReservations.depuisJson(corps);
  }

  Future<ReservationClient> reservation(String id) async {
    final reponse = await _dio.get<Object?>('/reservations/$id');
    final corps = reponse.data is Map
        ? Map<String, dynamic>.from(reponse.data as Map)
        : <String, dynamic>{};
    final j = corps['data'] is Map
        ? Map<String, dynamic>.from(corps['data'] as Map)
        : corps;
    return ReservationClient.depuisJson(j);
  }

  /// Publie l'avis du client sur une réservation terminée (POST /reservations/{id}/avis).
  Future<void> publierAvis(
    String id, {
    required int note,
    String? commentaire,
  }) async {
    await _dio.post<Object?>(
      '/reservations/$id/avis',
      data: {'note': note, 'commentaire': ?commentaire},
    );
  }

  /// POST /reservations/{id}/annuler (sans corps).
  Future<void> annuler(String id) async {
    await _dio.post<Object?>('/reservations/$id/annuler');
  }

  /// POST /reservations/{id}/payer (sans corps) : crée la session de paiement chez le prestataire de paiement.
  Future<SessionPaiement> payer(String id) async {
    final reponse = await _dio.post<Object?>('/reservations/$id/payer');
    return SessionPaiement.depuisJson(reponse.data);
  }

  /// Acompte d'une prestation, tel que l'API le donne dans le profil public (jamais recalculé ici).
  Future<AcomptePrestation?> acomptePrestation(
    String prestataireId,
    String prestationId,
  ) async {
    final profil = await this.profil(prestataireId);
    for (final p in profil.prestations) {
      if (p.id == prestationId) {
        return AcomptePrestation(
          montant: p.montantAcompte,
          pourcentage: profil.acomptePourcentage,
        );
      }
    }
    return null;
  }
}

final depotReservationProvider = Provider<DepotReservation>(
  (ref) => DepotReservation(ref.watch(dioProvider)),
);

/// Liste des métiers (publique), gardée en mémoire : sert à l'accueil et aux titres.
/// Pas de relance automatique des chargements : l'écran propose « Réessayer ».
Duration? sansRelance(int essais, Object erreur) => null;

final metiersProvider = FutureProvider<List<Metier>>(
  retry: sansRelance,
  (ref) => ref.watch(depotReservationProvider).metiers(),
);

final quartiersProvider = FutureProvider<List<String>>(
  retry: sansRelance,
  (ref) => ref.watch(depotReservationProvider).quartiers(),
);

/// Liste complète des quartiers pour l'inscription d'un prestataire (la recherche garde [quartiersProvider]).
final quartiersInscriptionProvider = FutureProvider<List<String>>(
  retry: sansRelance,
  (ref) => ref.watch(depotReservationProvider).quartiers(tous: true),
);

/// Nombre de notifications non lues, pour la pastille de la cloche.
final notificationsNonLuesProvider = FutureProvider<int>(
  retry: sansRelance,
  (ref) => ref.watch(depotReservationProvider).notificationsNonLues(),
);

/// Prestataire + prestation dont on veut l'acompte (clé de [acomptePrestationProvider]).
typedef ClePrestation = ({String prestataireId, String prestationId});

/// Acompte d'une prestation (donné par l'API), pour une réservation acceptée sans paiement encore créé.
final acomptePrestationProvider =
    FutureProvider.family<AcomptePrestation?, ClePrestation>(
      retry: sansRelance,
      (ref, cle) => ref
          .watch(depotReservationProvider)
          .acomptePrestation(cle.prestataireId, cle.prestationId),
    );

/// Une réservation du client, relue à la demande (tirer pour actualiser, retour d'un écran…).
final detailReservationProvider = FutureProvider.autoDispose
    .family<ReservationClient, String>(
      retry: sansRelance,
      (ref, id) => ref.watch(depotReservationProvider).reservation(id),
    );
