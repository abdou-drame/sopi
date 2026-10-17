import '../reservation/modeles.dart';

num? _nombre(Object? brut) {
  if (brut is num) return brut;
  if (brut is String) return num.tryParse(brut);
  return null;
}

Map<String, dynamic> _objet(Object? brut) =>
    brut is Map ? Map<String, dynamic>.from(brut) : <String, dynamic>{};

/// Demande de réservation vue par le prestataire (`GET /prestataire/reservations` et `/{id}`).
///
/// L'API n'expose ni le téléphone ni le nom complet du client : seulement son prénom et l'initiale de son nom.
/// `acompte`, `commission` et `montantCredite` ne sont présents que dans la liste et le détail, pas dans les
/// réponses d'action (accepter, refuser) ; ils valent alors null.
class DemandeReservation {
  const DemandeReservation({
    required this.id,
    required this.statut,
    required this.debut,
    required this.fin,
    required this.prestationNom,
    required this.dureeMinutes,
    required this.prix,
    required this.clientPrenom,
    required this.clientInitiale,
    this.motifRefus,
    this.reponseAvant,
    this.acompte,
    this.commission,
    this.montantCredite,
  });

  final String id;
  final StatutReservation statut;
  final DateTime debut;
  final DateTime fin;
  final String prestationNom;
  final int dureeMinutes;
  final num prix;
  final String clientPrenom;
  final String clientInitiale;
  final String? motifRefus;

  /// Échéance de réponse du prestataire (UTC) ; null sauf pour une demande en attente.
  final DateTime? reponseAvant;
  final num? acompte;
  final num? commission;
  final num? montantCredite;

  /// « Aminata D. »
  String get clientAffiche =>
      clientInitiale.isEmpty ? clientPrenom : '$clientPrenom $clientInitiale.';

  bool get aTraiter => statut == StatutReservation.enAttente;

  /// Les montants sont connus (liste ou détail, pas une réponse d'action).
  bool get montantsConnus =>
      acompte != null && commission != null && montantCredite != null;

  factory DemandeReservation.depuisJson(Object? brut) {
    final j = _objet(brut);
    final creneau = _objet(j['creneau']);
    final prestation = _objet(j['prestation']);
    final client = _objet(j['client']);
    return DemandeReservation(
      id: j['id'].toString(),
      statut: StatutReservation.depuisCode(j['statut']),
      debut: DateTime.parse(creneau['dateDebut'].toString()).toUtc(),
      fin: DateTime.parse(creneau['dateFin'].toString()).toUtc(),
      prestationNom: (prestation['nom'] ?? '').toString(),
      dureeMinutes: _nombre(prestation['dureeMinutes'])?.toInt() ?? 0,
      prix: _nombre(prestation['prix']) ?? 0,
      clientPrenom: (client['prenom'] ?? '').toString(),
      clientInitiale: (client['initialeNom'] ?? '').toString(),
      motifRefus: j['motifRefus'] as String?,
      reponseAvant: j['reponseAvant'] == null
          ? null
          : DateTime.parse(j['reponseAvant'].toString()).toUtc(),
      acompte: _nombre(j['acompte']),
      commission: _nombre(j['commission']),
      montantCredite: _nombre(j['montantCredite']),
    );
  }
}

/// Une page de demandes avec sa pagination (`meta.total` sert au compteur « Demandes à traiter »).
class PageDemandes {
  const PageDemandes({required this.elements, required this.pagination});

  final List<DemandeReservation> elements;
  final Pagination pagination;

  factory PageDemandes.depuisJson(Object? corps) {
    final data = _objet(corps)['data'];
    return PageDemandes(
      elements: [
        if (data is List)
          for (final e in data)
            if (e is Map) DemandeReservation.depuisJson(e),
      ],
      pagination: Pagination.depuisJson(corps),
    );
  }
}

/// Les quatre onglets de la liste du prestataire et le filtre que l'API attend pour chacun.
enum OngletDemandes {
  aTraiter('À traiter', statut: 'en_attente'),
  aVenir('À venir', groupe: 'a_venir'),
  terminees('Terminées', groupe: 'terminees'),
  annulees('Annulées', groupe: 'annulees');

  const OngletDemandes(this.libelle, {this.statut, this.groupe});

  final String libelle;
  final String? statut;
  final String? groupe;
}

/// Détail du crédit du portefeuille donné par `POST /prestataire/reservations/{id}/terminer`.
/// Tous les montants viennent du serveur ; ils valent null si aucun crédit n'a eu lieu.
class CreditPrestation {
  const CreditPrestation({
    required this.reservation,
    this.montantTotal,
    this.commission,
    this.montantCredite,
    this.soldeApres,
  });

  final DemandeReservation reservation;
  final num? montantTotal;
  final num? commission;
  final num? montantCredite;
  final num? soldeApres;

  factory CreditPrestation.depuisJson(Object? brut) {
    final j = _objet(brut);
    return CreditPrestation(
      reservation: DemandeReservation.depuisJson(j),
      montantTotal: _nombre(j['montantTotal']),
      commission: _nombre(j['commission']),
      montantCredite: _nombre(j['montantCredite']),
      soldeApres: _nombre(j['soldeApres']),
    );
  }
}

/// Prestation proposée par le prestataire (`GET /prestataire/prestations`). L'acompte n'y figure pas : il est calculé par le serveur.
class PrestationService {
  const PrestationService({
    required this.id,
    required this.nom,
    required this.dureeMinutes,
    required this.prix,
    required this.actif,
    this.domaine,
  });

  final String id;
  final String nom;
  final String? domaine;
  final int dureeMinutes;
  final num prix;
  final bool actif;

  /// Accepte l'objet seul ou enveloppé dans `data` (réponses de création et de modification).
  factory PrestationService.depuisJson(Object? brut) {
    var j = _objet(brut);
    if (j['data'] is Map) j = _objet(j['data']);
    return PrestationService(
      id: '${j['id']}',
      nom: '${j['nom'] ?? ''}',
      domaine: j['domaine'] is String ? j['domaine'] as String : null,
      dureeMinutes: _nombre(j['dureeMinutes'])?.toInt() ?? 0,
      prix: _nombre(j['prix']) ?? 0,
      actif: j['actif'] != false,
    );
  }
}
