import '../reservation/modeles.dart' show Pagination;

num? _nombre(Object? brut) {
  if (brut is num) return brut;
  if (brut is String) return num.tryParse(brut);
  return null;
}

Map<String, dynamic> _objet(Object? brut) =>
    brut is Map ? Map<String, dynamic>.from(brut) : <String, dynamic>{};

String? _texte(Object? brut) =>
    brut is String && brut.trim().isNotEmpty ? brut : null;

/// Opérateur mobile money qui reçoit un versement.
enum OperateurRetrait {
  wave('wave', 'Wave', 'assets/wave.png'),
  orangeMoney('orange_money', 'Orange Money', 'assets/orange_money.png');

  const OperateurRetrait(this.code, this.libelle, this.logo);

  /// Valeur échangée avec l'API.
  final String code;
  final String libelle;
  final String logo;

  /// Null si l'API ne le connaît pas (opérateur inconnu ou absent).
  static OperateurRetrait? depuisCode(Object? brut) {
    for (final o in values) {
      if (o.code == brut) return o;
    }
    return null;
  }
}

enum StatutRetrait {
  enCours,
  reussi,
  echoue;

  static StatutRetrait? depuisCode(Object? brut) => switch (brut) {
    'en_cours' => StatutRetrait.enCours,
    'reussi' => StatutRetrait.reussi,
    'echoue' => StatutRetrait.echoue,
    _ => null,
  };
}

enum TypeMouvement {
  credit,
  retrait,
  remboursementRetrait,
  inconnu;

  static TypeMouvement depuisCode(Object? brut) => switch (brut) {
    'credit' => TypeMouvement.credit,
    'retrait' => TypeMouvement.retrait,
    'remboursement_retrait' => TypeMouvement.remboursementRetrait,
    _ => TypeMouvement.inconnu,
  };

  /// Le mouvement augmente le solde (le montant de l'API est toujours positif, le sens vient du type).
  bool get augmenteLeSolde => this != TypeMouvement.retrait;
}

/// Solde et limites d'un retrait (`GET /prestataire/solde`). Les limites viennent du serveur ; null si absentes.
class SoldePortefeuille {
  const SoldePortefeuille({required this.solde, this.minimum, this.maximum});

  final num solde;
  final num? minimum;
  final num? maximum;

  factory SoldePortefeuille.depuisJson(Object? brut) {
    final j = _objet(brut);
    return SoldePortefeuille(
      solde: _nombre(j['solde']) ?? 0,
      minimum: _nombre(j['montantMinimumRetrait']),
      maximum: _nombre(j['montantMaximumRetrait']),
    );
  }
}

/// Une ligne de l'historique (`GET /prestataire/portefeuille/mouvements`). Les champs facultatifs peuvent être null.
class MouvementPortefeuille {
  const MouvementPortefeuille({
    required this.id,
    required this.type,
    required this.montant,
    required this.date,
    this.libelle,
    this.prestation,
    this.operateur,
    this.statutRetrait,
  });

  final String id;
  final TypeMouvement type;
  final num montant;
  final DateTime date;
  final String? libelle;

  /// Nom du service (crédit de fin de prestation).
  final String? prestation;
  final OperateurRetrait? operateur;
  final StatutRetrait? statutRetrait;

  factory MouvementPortefeuille.depuisJson(Object? brut) {
    final j = _objet(brut);
    return MouvementPortefeuille(
      id: j['id'].toString(),
      type: TypeMouvement.depuisCode(j['type']),
      montant: _nombre(j['montant']) ?? 0,
      date:
          DateTime.tryParse('${j['date']}')?.toUtc() ?? DateTime.now().toUtc(),
      libelle: _texte(j['libelle']),
      prestation: _texte(j['prestation']),
      operateur: OperateurRetrait.depuisCode(j['operateur']),
      statutRetrait: StatutRetrait.depuisCode(j['statutRetrait']),
    );
  }
}

class PageMouvements {
  const PageMouvements({required this.elements, required this.pagination});

  final List<MouvementPortefeuille> elements;
  final Pagination pagination;

  factory PageMouvements.depuisJson(Object? corps) {
    final data = _objet(corps)['data'];
    return PageMouvements(
      elements: [
        if (data is List)
          for (final e in data)
            if (e is Map) MouvementPortefeuille.depuisJson(e),
      ],
      pagination: Pagination.depuisJson(corps),
    );
  }
}

/// Retrait créé (`POST /prestataire/retraits`). Le numéro est masqué par le serveur (« +221******567 »).
class RetraitCree {
  const RetraitCree({
    required this.id,
    required this.montant,
    required this.statut,
    this.operateur,
    this.telephone,
  });

  final String id;
  final num montant;
  final StatutRetrait statut;
  final OperateurRetrait? operateur;
  final String? telephone;

  factory RetraitCree.depuisJson(Object? brut) {
    final j = _objet(brut);
    return RetraitCree(
      id: j['id'].toString(),
      montant: _nombre(j['montant']) ?? 0,
      statut: StatutRetrait.depuisCode(j['statut']) ?? StatutRetrait.enCours,
      operateur: OperateurRetrait.depuisCode(j['operateur']),
      telephone: _texte(j['telephoneVersement']),
    );
  }
}
