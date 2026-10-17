import '../../core/widgets/liste_infinie.dart';

Map<String, dynamic> _objet(Object? brut) =>
    brut is Map ? Map<String, dynamic>.from(brut) : <String, dynamic>{};

String? _texte(Object? brut) {
  final s = (brut ?? '').toString().trim();
  return s.isEmpty ? null : s;
}

DateTime? _date(Object? brut) =>
    brut == null ? null : DateTime.tryParse(brut.toString())?.toUtc();

/// Onglets de l'écran « Signalements reçus ». L'API ne filtre pas par statut : les onglets reposent sur les états réels
/// d'un litige (ouvert sans réponse, ouvert avec réponse, clôturé).
enum OngletLitiges {
  aTraiter('À traiter'),
  enExamen('En examen'),
  clos('Clos');

  const OngletLitiges(this.libelle);

  final String libelle;
}

/// Signalement (litige) vu par le prestataire visé : `GET /prestataire/litiges` et `/{id}`.
/// Le client n'est connu que par son prénom et l'initiale de son nom.
class LitigeRecu {
  const LitigeRecu({
    required this.id,
    required this.cloture,
    required this.motif,
    required this.description,
    required this.dateCreation,
    required this.prestationNom,
    required this.dateRendezVous,
    required this.clientPrenom,
    required this.clientInitiale,
    this.nombrePreuves = 0,
    this.preuves = const [],
    this.reponsePrestataire,
    this.reponseLe,
    this.decision,
    this.motifDecision,
  });

  final String id;
  final bool cloture;
  final String motif;
  final String description;
  final DateTime dateCreation;
  final String prestationNom;
  final DateTime dateRendezVous;
  final String clientPrenom;
  final String clientInitiale;
  final int nombrePreuves;

  /// Identifiants des preuves du client (présents dans le détail seulement).
  final List<String> preuves;
  final String? reponsePrestataire;
  final DateTime? reponseLe;
  final String? decision;
  final String? motifDecision;

  bool get aRepondu => reponsePrestataire != null;

  OngletLitiges get onglet => cloture
      ? OngletLitiges.clos
      : aRepondu
      ? OngletLitiges.enExamen
      : OngletLitiges.aTraiter;

  /// « Aminata D. »
  String get clientAffiche =>
      clientInitiale.isEmpty ? clientPrenom : '$clientPrenom $clientInitiale.';

  factory LitigeRecu.depuisJson(Object? brut) {
    final j = _objet(brut);
    final reservation = _objet(j['reservation']);
    final client = _objet(j['client']);
    final preuves = <String>[
      for (final p in (j['preuves'] is List ? j['preuves'] as List : const []))
        if (p is Map) p['id'].toString(),
    ];
    final n = j['nombrePreuves'];
    final creation = _date(j['dateCreation']) ?? DateTime.now().toUtc();
    return LitigeRecu(
      id: j['id'].toString(),
      cloture: j['statut'] == 'cloture',
      motif: (j['motif'] ?? '').toString(),
      description: (j['description'] ?? '').toString(),
      dateCreation: creation,
      prestationNom: (reservation['prestation'] ?? '').toString(),
      dateRendezVous: _date(reservation['dateDebut']) ?? creation,
      clientPrenom: (client['prenom'] ?? '').toString(),
      clientInitiale: (client['initialeNom'] ?? '').toString(),
      nombrePreuves: n is num ? n.toInt() : preuves.length,
      preuves: preuves,
      reponsePrestataire: _texte(j['reponsePrestataire']),
      reponseLe: _date(j['reponseLe']),
      decision: _texte(j['decision']),
      motifDecision: _texte(j['motifDecision']),
    );
  }

  static PageListe<LitigeRecu> pageDepuisJson(Object? corps) {
    final brut = _objet(corps);
    final meta = _objet(brut['meta']);
    return PageListe(
      elements: [
        for (final e
            in (brut['data'] is List ? brut['data'] as List : const []))
          if (e is Map) LitigeRecu.depuisJson(e),
      ],
      page: (meta['current_page'] as num?)?.toInt() ?? 1,
      derniere: (meta['last_page'] as num?)?.toInt() ?? 1,
    );
  }
}
