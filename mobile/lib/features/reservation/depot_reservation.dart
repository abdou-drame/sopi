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
    this.tri = TriPrestataires.nom,
  });

  final String recherche;
  final String? quartier;
  final double? noteMin;
  final int? prixMin;
  final int? prixMax;
  final TriPrestataires tri;

  bool get actifs =>
      recherche.trim().isNotEmpty ||
      quartier != null ||
      noteMin != null ||
      prixMin != null ||
      prixMax != null;

  FiltresPrestataires copie({
    String? recherche,
    Object? quartier = _inchange,
    Object? noteMin = _inchange,
    Object? prixMin = _inchange,
    Object? prixMax = _inchange,
    TriPrestataires? tri,
  }) => FiltresPrestataires(
    recherche: recherche ?? this.recherche,
    quartier: identical(quartier, _inchange)
        ? this.quartier
        : quartier as String?,
    noteMin: identical(noteMin, _inchange) ? this.noteMin : noteMin as double?,
    prixMin: identical(prixMin, _inchange) ? this.prixMin : prixMin as int?,
    prixMax: identical(prixMax, _inchange) ? this.prixMax : prixMax as int?,
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

  Future<List<String>> quartiers() async {
    final corps = await _lire('/quartiers');
    return [for (final e in (corps['data'] as List? ?? const [])) e.toString()];
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
}

final depotReservationProvider = Provider<DepotReservation>(
  (ref) => DepotReservation(ref.watch(dioProvider)),
);

/// Liste des métiers (publique), gardée en mémoire : sert à l'accueil et aux titres.
final metiersProvider = FutureProvider<List<Metier>>(
  (ref) => ref.watch(depotReservationProvider).metiers(),
);

final quartiersProvider = FutureProvider<List<String>>(
  (ref) => ref.watch(depotReservationProvider).quartiers(),
);
