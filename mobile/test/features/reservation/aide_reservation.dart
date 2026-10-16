import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../aide_api.dart';

/// Réponses simulées fidèles au format réel de l'API (`/api/v1`).

Map<String, Object?> utilisateurClient({String prenom = 'Aminata'}) => {
  'data': {
    'id': 'u-1',
    'nom': 'Diop',
    'prenom': prenom,
    'telephone': '+221771234567',
    'email': 'aminata@exemple.sn',
    'role': 'client',
    'statut': 'actif',
  },
};

const metiersApi = {
  'data': [
    {'id': 'm-1', 'code': 'mecanique', 'nom': 'Mécanique'},
    {'id': 'm-2', 'code': 'coiffure', 'nom': 'Coiffure'},
    {'id': 'm-3', 'code': 'pressing', 'nom': 'Pressing'},
  ],
};

const quartiersApi = {
  'data': ['Jaxaay', 'Keur Massar Nord', 'Malika'],
};

Map<String, Object?> prestataireListe(
  String id,
  String nom, {
  String metier = 'coiffure',
  String quartier = 'Malika',
  num? prixMin = 3000,
  num? note = 4.9,
  int avis = 12,
}) => {
  'id': id,
  'nomEntreprise': nom,
  'metier': metier,
  'quartier': quartier,
  'adresse': 'Rue 12',
  'prixMin': prixMin,
  'nombrePrestations': 3,
  'noteMoyenne': note,
  'nombreAvis': avis,
};

Map<String, Object?> pagePrestataires(
  List<Map<String, Object?>> elements, {
  int page = 1,
  int derniere = 1,
  int? total,
}) => {
  'data': elements,
  'links': {'first': null, 'last': null, 'prev': null, 'next': null},
  'meta': {
    'current_page': page,
    'last_page': derniere,
    'per_page': 20,
    'total': total ?? elements.length,
  },
};

Map<String, Object?> profilApi({
  String id = 'p-1',
  String nom = 'Salon Fatou',
  List<Map<String, Object?>>? prestations,
  List<Map<String, Object?>> photos = const [],
  num? note = 4.9,
  int avis = 128,
}) => {
  'data': {
    'id': id,
    'nomEntreprise': nom,
    'metier': 'coiffure',
    'quartier': 'Malika',
    'adresse': 'Rue 12',
    'noteMoyenne': note,
    'nombreAvis': avis,
    'acomptePourcentage': 30,
    'prestations':
        prestations ??
        [
          {
            'id': 'sv-1',
            'nom': 'Coupe femme',
            'domaine': 'Coiffure',
            'dureeMinutes': 60,
            'prix': 5000,
            'montantAcompte': 1500,
          },
          {
            'id': 'sv-2',
            'nom': 'Brushing',
            'domaine': 'Coiffure',
            'dureeMinutes': 45,
            'prix': 3000,
            'montantAcompte': 900,
          },
        ],
    'photos': photos,
  },
};

Map<String, Object?> avisApi(
  List<Map<String, Object?>> elements, {
  int page = 1,
  int derniere = 1,
}) => {
  'data': elements,
  'links': {'first': null, 'last': null, 'prev': null, 'next': null},
  'meta': {
    'current_page': page,
    'last_page': derniere,
    'per_page': 20,
    'total': elements.length,
  },
  'noteMoyenne': 4.9,
  'nombreAvis': 128,
};

Map<String, Object?> unAvis(
  String id,
  String auteur,
  int note,
  String? commentaire,
) => {
  'id': id,
  'note': note,
  'commentaire': commentaire,
  'auteur': auteur,
  'dateCreation': '2026-10-01T10:00:00+00:00',
};

Map<String, Object?> reservationApi(
  String id, {
  required DateTime debut,
  String prestataire = 'Salon Fatou',
  String statut = 'en_attente',
  String? motifRefus,
  String? code,
  bool peutAnnuler = true,
  bool peutPayer = false,
  bool peutNoter = false,
  Map<String, Object?>? paiement,
  Map<String, Object?>? avis,
  String? telephone = '+221******567',
  String prestation = 'Coupe femme',
}) => {
  'id': id,
  'statut': statut,
  'dateCreation': '2026-10-15T09:00:00+00:00',
  'motifRefus': motifRefus,
  'codeReservation': code,
  'peutAnnuler': peutAnnuler,
  'peutPayer': peutPayer,
  'paiement': paiement,
  'peutNoter': peutNoter,
  'avis': avis,
  'creneau': {
    'id': 'c-1',
    'dateDebut': debut.toUtc().toIso8601String(),
    'dateFin': debut.add(const Duration(hours: 1)).toUtc().toIso8601String(),
  },
  'prestation': {
    'id': 'sv-1',
    'nom': prestation,
    'dureeMinutes': 60,
    'prix': 5000,
  },
  'prestataire': {
    'id': 'p-1',
    'nomEntreprise': prestataire,
    'quartier': 'Malika',
    'adresse': 'Rue 12',
    'telephone': telephone,
  },
};

Map<String, Object?> creneauApi(String id, DateTime debut, {int heures = 1}) =>
    {
      'id': id,
      'dateDebut': debut.toUtc().toIso8601String().replaceFirst('Z', '+00:00'),
      'dateFin': debut
          .add(Duration(hours: heures))
          .toUtc()
          .toIso8601String()
          .replaceFirst('Z', '+00:00'),
    };

/// Le jeton de test donne une session client : `/me` répond avec un client.
Map<String, Simulee> routesClient([Map<String, Simulee> enPlus = const {}]) => {
  'GET /me': Simulee(200, utilisateurClient()),
  'GET /metiers': const Simulee(200, metiersApi),
  'GET /quartiers': const Simulee(200, quartiersApi),
  ...enPlus,
};

/// Faux serveur dont les réponses aux routes données restent en attente jusqu'à [liberer].
class AdaptateurLentPour extends AdaptateurSimule {
  AdaptateurLentPour(super.routes, this.lentes);

  /// Clés « METHODE /chemin » retardées.
  final Set<String> lentes;
  final _porte = Completer<void>();

  void liberer() => _porte.complete();

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (lentes.contains('${options.method} ${options.path}')) {
      await _porte.future;
    }
    return super.fetch(options, requestStream, cancelFuture);
  }
}
