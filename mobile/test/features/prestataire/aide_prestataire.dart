// Réponses simulées de l'espace prestataire, fidèles au format réel de l'API (`/api/v1/prestataire`).

/// Une demande de réservation. [avecMontants] est faux pour les réponses d'action (accepter, refuser…).
Map<String, Object?> demandeApi({
  String id = 'r-1',
  String statut = 'en_attente',
  String prenom = 'Aminata',
  String initiale = 'D',
  String prestation = 'Coupe femme',
  int duree = 60,
  num prix = 5000,
  String debut = '2026-10-24T10:00:00+00:00',
  String fin = '2026-10-24T11:00:00+00:00',
  String? reponseAvant = '2026-10-24T12:00:00+00:00',
  String? motifRefus,
  bool avecMontants = true,
  num acompte = 1500,
  num commission = 500,
  num montantCredite = 1000,
}) => {
  'id': id,
  'statut': statut,
  'dateCreation': '2026-10-23T12:00:00+00:00',
  'motifRefus': motifRefus,
  'reponseAvant': statut == 'en_attente' ? reponseAvant : null,
  if (avecMontants) ...{
    'acompte': acompte,
    'commission': commission,
    'montantCredite': montantCredite,
  },
  'creneau': {'id': 'c-$id', 'dateDebut': debut, 'dateFin': fin},
  'prestation': {
    'id': 'pr-1',
    'nom': prestation,
    'dureeMinutes': duree,
    'prix': prix,
  },
  'client': {'prenom': prenom, 'initialeNom': initiale},
};

Map<String, Object?> pageDemandesApi(
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
