// Modèles du module Réservation (découverte des prestataires, créneaux, demande). Lus tels que l'API les renvoie.

num? _nombre(Object? brut) {
  if (brut is num) return brut;
  if (brut is String) return num.tryParse(brut);
  return null;
}

DateTime _date(Object? brut) => DateTime.parse(brut.toString()).toUtc();

Map<String, dynamic> _objet(Object? brut) =>
    brut is Map ? Map<String, dynamic>.from(brut) : <String, dynamic>{};

List<Map<String, dynamic>> _liste(Object? brut) => brut is List
    ? [
        for (final e in brut)
          if (e is Map) Map<String, dynamic>.from(e),
      ]
    : const [];

/// Métier (catégorie de services) : `code` sert de filtre, `nom` est affiché.
class Metier {
  const Metier({required this.id, required this.code, required this.nom});

  final String id;
  final String code;
  final String nom;

  factory Metier.depuisJson(Map<String, dynamic> j) => Metier(
    id: j['id'].toString(),
    code: j['code'].toString(),
    nom: j['nom'].toString(),
  );
}

/// Pagination de l'API : `meta.current_page`, `meta.last_page`, `meta.total`.
class Pagination {
  const Pagination({required this.page, required this.derniere, this.total});

  final int page;
  final int derniere;
  final int? total;

  bool get encore => page < derniere;

  factory Pagination.depuisJson(Object? corps) {
    final meta = _objet(_objet(corps)['meta']);
    return Pagination(
      page: _nombre(meta['current_page'])?.toInt() ?? 1,
      derniere: _nombre(meta['last_page'])?.toInt() ?? 1,
      total: _nombre(meta['total'])?.toInt(),
    );
  }
}

/// Prestataire tel qu'il apparaît dans une liste (recherche, accueil).
class PrestataireResume {
  const PrestataireResume({
    required this.id,
    required this.nomEntreprise,
    required this.metierCode,
    required this.quartier,
    this.prixMin,
    this.noteMoyenne,
    this.nombreAvis = 0,
  });

  final String id;
  final String nomEntreprise;
  final String metierCode;
  final String quartier;
  final num? prixMin;
  final double? noteMoyenne;
  final int nombreAvis;

  bool get estNouveau => nombreAvis == 0 || noteMoyenne == null;

  factory PrestataireResume.depuisJson(Map<String, dynamic> j) =>
      PrestataireResume(
        id: j['id'].toString(),
        nomEntreprise: (j['nomEntreprise'] ?? '').toString(),
        metierCode: (j['metier'] ?? '').toString(),
        quartier: (j['quartier'] ?? '').toString(),
        prixMin: _nombre(j['prixMin']),
        noteMoyenne: _nombre(j['noteMoyenne'])?.toDouble(),
        nombreAvis: _nombre(j['nombreAvis'])?.toInt() ?? 0,
      );
}

class PagePrestataires {
  const PagePrestataires({required this.elements, required this.pagination});

  final List<PrestataireResume> elements;
  final Pagination pagination;

  factory PagePrestataires.depuisJson(Object? corps) => PagePrestataires(
    elements: [
      for (final e in _liste(_objet(corps)['data']))
        PrestataireResume.depuisJson(e),
    ],
    pagination: Pagination.depuisJson(corps),
  );
}

/// Service proposé par un prestataire. Les montants viennent de l'API (jamais recalculés ici).
class Prestation {
  const Prestation({
    required this.id,
    required this.nom,
    required this.dureeMinutes,
    required this.prix,
    required this.montantAcompte,
  });

  final String id;
  final String nom;
  final int dureeMinutes;
  final num prix;
  final num montantAcompte;

  factory Prestation.depuisJson(Map<String, dynamic> j) => Prestation(
    id: j['id'].toString(),
    nom: (j['nom'] ?? '').toString(),
    dureeMinutes: _nombre(j['dureeMinutes'])?.toInt() ?? 0,
    prix: _nombre(j['prix']) ?? 0,
    montantAcompte: _nombre(j['montantAcompte']) ?? 0,
  );
}

class PhotoPrestataire {
  const PhotoPrestataire({required this.id, required this.url});

  final String id;

  /// Chemin renvoyé par l'API (`/api/v1/prestataires/{id}/photos/{photo}`) ou adresse complète.
  final String url;

  factory PhotoPrestataire.depuisJson(Map<String, dynamic> j) =>
      PhotoPrestataire(
        id: j['id'].toString(),
        url: (j['url'] ?? '').toString(),
      );
}

class ProfilPrestataire {
  const ProfilPrestataire({
    required this.id,
    required this.nomEntreprise,
    required this.metierCode,
    required this.quartier,
    required this.prestations,
    required this.photos,
    this.adresse,
    this.noteMoyenne,
    this.nombreAvis = 0,
  });

  final String id;
  final String nomEntreprise;
  final String metierCode;
  final String quartier;
  final String? adresse;
  final double? noteMoyenne;
  final int nombreAvis;
  final List<Prestation> prestations;
  final List<PhotoPrestataire> photos;

  factory ProfilPrestataire.depuisJson(Object? corps) {
    final brut = _objet(corps);
    final j = brut['data'] is Map ? _objet(brut['data']) : brut;
    return ProfilPrestataire(
      id: j['id'].toString(),
      nomEntreprise: (j['nomEntreprise'] ?? '').toString(),
      metierCode: (j['metier'] ?? '').toString(),
      quartier: (j['quartier'] ?? '').toString(),
      adresse: j['adresse'] as String?,
      noteMoyenne: _nombre(j['noteMoyenne'])?.toDouble(),
      nombreAvis: _nombre(j['nombreAvis'])?.toInt() ?? 0,
      prestations: [
        for (final e in _liste(j['prestations'])) Prestation.depuisJson(e),
      ],
      photos: [
        for (final e in _liste(j['photos'])) PhotoPrestataire.depuisJson(e),
      ],
    );
  }
}

class Avis {
  const Avis({
    required this.id,
    required this.note,
    required this.auteur,
    this.commentaire,
    this.dateCreation,
  });

  final String id;
  final int note;
  final String auteur;
  final String? commentaire;
  final DateTime? dateCreation;

  factory Avis.depuisJson(Map<String, dynamic> j) => Avis(
    id: j['id'].toString(),
    note: _nombre(j['note'])?.toInt() ?? 0,
    auteur: (j['auteur'] ?? '').toString(),
    commentaire: j['commentaire'] as String?,
    dateCreation: j['dateCreation'] == null ? null : _date(j['dateCreation']),
  );
}

class PageAvis {
  const PageAvis({
    required this.elements,
    required this.pagination,
    this.noteMoyenne,
    this.nombreAvis = 0,
  });

  final List<Avis> elements;
  final Pagination pagination;
  final double? noteMoyenne;
  final int nombreAvis;

  factory PageAvis.depuisJson(Object? corps) {
    final j = _objet(corps);
    return PageAvis(
      elements: [for (final e in _liste(j['data'])) Avis.depuisJson(e)],
      pagination: Pagination.depuisJson(corps),
      noteMoyenne: _nombre(j['noteMoyenne'])?.toDouble(),
      nombreAvis: _nombre(j['nombreAvis'])?.toInt() ?? 0,
    );
  }
}

/// Créneau libre de l'agenda d'un prestataire.
class CreneauLibre {
  const CreneauLibre({
    required this.id,
    required this.debut,
    required this.fin,
  });

  final String id;
  final DateTime debut;
  final DateTime fin;

  factory CreneauLibre.depuisJson(Map<String, dynamic> j) => CreneauLibre(
    id: j['id'].toString(),
    debut: _date(j['dateDebut']),
    fin: _date(j['dateFin']),
  );
}

/// Verrou de 5 minutes posé sur un créneau.
class VerrouCreneau {
  const VerrouCreneau({required this.creneauId, required this.expireA});

  final String creneauId;
  final DateTime expireA;

  factory VerrouCreneau.depuisJson(Object? corps) {
    final brut = _objet(corps);
    final j = brut['data'] is Map ? _objet(brut['data']) : brut;
    return VerrouCreneau(
      creneauId: j['creneauId'].toString(),
      expireA: _date(j['verrouExpireA']),
    );
  }
}

/// Réponse à la création d'une demande (POST /reservations).
class ReservationCreee {
  const ReservationCreee({
    required this.id,
    required this.statut,
    required this.debut,
    required this.prestationNom,
    required this.prix,
    required this.prestataireNom,
    this.peutPayer = false,
  });

  final String id;
  final String statut;
  final DateTime debut;
  final String prestationNom;
  final num prix;
  final String prestataireNom;

  /// Absent de la réponse de création : faux. Le paiement n'est possible qu'après acceptation.
  final bool peutPayer;

  factory ReservationCreee.depuisJson(Object? corps) {
    final brut = _objet(corps);
    final j = brut['data'] is Map ? _objet(brut['data']) : brut;
    final creneau = _objet(j['creneau']);
    final prestation = _objet(j['prestation']);
    final prestataire = _objet(j['prestataire']);
    return ReservationCreee(
      id: j['id'].toString(),
      statut: (j['statut'] ?? '').toString(),
      debut: _date(creneau['dateDebut']),
      prestationNom: (prestation['nom'] ?? '').toString(),
      prix: _nombre(prestation['prix']) ?? 0,
      prestataireNom: (prestataire['nomEntreprise'] ?? '').toString(),
      peutPayer: j['peutPayer'] == true,
    );
  }
}

/// Réservation à venir la plus proche (carte « Prochaine réservation » de l'accueil).
class ReservationAVenir {
  const ReservationAVenir({
    required this.id,
    required this.debut,
    required this.prestataireId,
    required this.prestataireNom,
  });

  final String id;
  final DateTime debut;
  final String prestataireId;
  final String prestataireNom;

  static ReservationAVenir? premiereDepuisJson(Object? corps) {
    final liste = _liste(_objet(corps)['data']);
    if (liste.isEmpty) return null;
    final j = liste.first;
    final prestataire = _objet(j['prestataire']);
    return ReservationAVenir(
      id: j['id'].toString(),
      debut: _date(_objet(j['creneau'])['dateDebut']),
      prestataireId: prestataire['id'].toString(),
      prestataireNom: (prestataire['nomEntreprise'] ?? '').toString(),
    );
  }
}
