import 'dart:typed_data';

import '../../core/widgets/liste_infinie.dart';

/// Motifs proposés au client ; le libellé choisi est envoyé tel quel comme `motif`.
const motifsLitige = <String>[
  'Prestataire absent',
  'Service non conforme',
  'Problème de prix',
  'Autre',
];

const descriptionLitigeMin = 10;
const descriptionLitigeMax = 1000;
const preuvesLitigeMax = 3;
const tailleMaxPreuve = 512 * 1024;

/// Types d'image acceptés par l'API pour les preuves.
const typesPreuveAcceptes = <String>{'image/jpeg', 'image/png', 'image/webp'};

/// Photo choisie par le client (galerie ou appareil photo), lue en mémoire.
class PhotoChoisie {
  const PhotoChoisie({
    required this.nom,
    required this.octets,
    required this.typeMime,
  });

  final String nom;
  final Uint8List octets;
  final String typeMime;
}

/// `en cours` ou `clôturé` : état d'un signalement.
enum StatutLitige {
  ouvert('Ouvert'),
  cloture('Clôturé');

  const StatutLitige(this.libelle);

  final String libelle;

  static StatutLitige depuisCode(Object? code) =>
      code == 'cloture' ? cloture : ouvert;
}

/// Décision de l'administrateur, telle que l'API l'écrit.
String libelleDecision(String? code) => switch (code) {
  'rejet' => 'Signalement non retenu',
  'avertissement' => 'Avertissement envoyé au prestataire',
  'suspension_temporaire' => 'Prestataire suspendu temporairement',
  'bannissement_definitif' => 'Prestataire exclu de Sopi',
  _ => 'Décision rendue',
};

class PreuveLitige {
  const PreuveLitige({required this.id, required this.url});

  final String id;

  /// Chemin relatif à l'API (`/api/v1/litiges/{id}/preuves/{preuve}`).
  final String url;
}

/// Litige vu par son client (GET /litiges et GET /litiges/{id}).
class LitigeClient {
  const LitigeClient({
    required this.id,
    required this.statut,
    required this.motif,
    required this.description,
    required this.dateCreation,
    required this.prestationNom,
    required this.dateRendezVous,
    required this.prestataireNom,
    this.nombrePreuves = 0,
    this.preuves = const [],
    this.reponsePrestataire,
    this.decision,
    this.motifDecision,
  });

  final String id;
  final StatutLitige statut;
  final String motif;
  final String description;
  final DateTime dateCreation;
  final String prestationNom;
  final DateTime dateRendezVous;
  final String prestataireNom;
  final int nombrePreuves;
  final List<PreuveLitige> preuves;
  final String? reponsePrestataire;
  final String? decision;
  final String? motifDecision;

  factory LitigeClient.depuisJson(Map<String, dynamic> j) {
    final reservation = j['reservation'] is Map
        ? Map<String, dynamic>.from(j['reservation'] as Map)
        : <String, dynamic>{};
    final prestataire = j['prestataire'] is Map
        ? Map<String, dynamic>.from(j['prestataire'] as Map)
        : <String, dynamic>{};
    String? texte(Object? v) {
      final s = (v ?? '').toString().trim();
      return s.isEmpty ? null : s;
    }

    final preuves = <PreuveLitige>[
      for (final p in (j['preuves'] as List? ?? const []))
        if (p is Map)
          PreuveLitige(id: p['id'].toString(), url: p['url'].toString()),
    ];
    final n = j['nombrePreuves'];
    return LitigeClient(
      id: j['id'].toString(),
      statut: StatutLitige.depuisCode(j['statut']),
      motif: (j['motif'] ?? '').toString(),
      description: (j['description'] ?? '').toString(),
      dateCreation: DateTime.parse(j['dateCreation'].toString()).toUtc(),
      prestationNom: (reservation['prestation'] ?? '').toString(),
      dateRendezVous: DateTime.parse(
        (reservation['dateDebut'] ?? j['dateCreation']).toString(),
      ).toUtc(),
      prestataireNom: (prestataire['nomEntreprise'] ?? '').toString(),
      nombrePreuves: n is num ? n.toInt() : preuves.length,
      preuves: preuves,
      reponsePrestataire: texte(j['reponsePrestataire']),
      decision: texte(j['decision']),
      motifDecision: texte(j['motifDecision']),
    );
  }

  static PageListe<LitigeClient> pageDepuisJson(Object? corps) {
    final brut = corps is Map ? Map<String, dynamic>.from(corps) : const {};
    final meta = brut['meta'] is Map ? brut['meta'] as Map : const {};
    return PageListe(
      elements: [
        for (final e in (brut['data'] as List? ?? const []))
          if (e is Map) LitigeClient.depuisJson(Map<String, dynamic>.from(e)),
      ],
      page: (meta['current_page'] as num?)?.toInt() ?? 1,
      derniere: (meta['last_page'] as num?)?.toInt() ?? 1,
    );
  }
}
