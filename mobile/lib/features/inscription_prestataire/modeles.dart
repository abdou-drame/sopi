import 'dart:typed_data';

/// Pièces du dossier, avec le `type_document` attendu par l'API (cni, diplome, attestation).
/// Les trois lignes de l'étape 3 correspondent dans l'ordre à ces trois types.
enum TypeJustificatif {
  cni('cni', "Carte d'identité", obligatoire: true),
  diplome('diplome', 'Diplôme ou attestation', obligatoire: true),
  attestation('attestation', "Justificatif d'activité", obligatoire: false);

  const TypeJustificatif(
    this.valeurApi,
    this.libelle, {
    required this.obligatoire,
  });

  final String valeurApi;
  final String libelle;
  final bool obligatoire;

  static TypeJustificatif? depuisApi(String? valeur) {
    for (final t in values) {
      if (t.valeurApi == valeur) return t;
    }
    return null;
  }
}

/// Formats et taille acceptés par l'API (PDF, JPEG, PNG ; 5 Mo).
const typesJustificatifAcceptes = <String>{
  'application/pdf',
  'image/jpeg',
  'image/png',
};
const tailleMaxJustificatif = 5 * 1024 * 1024;

/// Fichier choisi pour un justificatif, lu en mémoire.
class FichierJustificatif {
  const FichierJustificatif({
    required this.nom,
    required this.octets,
    required this.typeMime,
  });

  final String nom;
  final Uint8List octets;
  final String typeMime;
}

/// Contenu du formulaire d'inscription sur les trois étapes. Conservé en mémoire seulement : le mot de passe
/// n'est jamais écrit sur le disque.
class DossierInscription {
  const DossierInscription({
    this.nomEntreprise = '',
    this.metierCode,
    this.quartier,
    this.quartierAutre = false,
    this.adresse = '',
    this.prenom = '',
    this.nom = '',
    this.telephone = '',
    this.email = '',
    this.motDePasse = '',
    this.confirmation = '',
    this.fichiers = const {},
  });

  final String nomEntreprise;

  /// Code du métier (champ `metier` de l'API), choisi parmi `GET /metiers`.
  final String? metierCode;
  final String? quartier;

  /// Vrai quand le prestataire a choisi « Autre quartier » : [quartier] est alors le texte qu'il a saisi.
  final bool quartierAutre;
  final String adresse;
  final String prenom;
  final String nom;
  final String telephone;
  final String email;
  final String motDePasse;
  final String confirmation;
  final Map<TypeJustificatif, FichierJustificatif> fichiers;

  /// Quartier tel qu'il est envoyé et validé : espaces de début, de fin et répétés retirés.
  String get quartierNettoye =>
      (quartier ?? '').trim().replaceAll(RegExp(r's+'), ' ');

  DossierInscription copie({
    String? nomEntreprise,
    Object? metierCode = _inchange,
    Object? quartier = _inchange,
    bool? quartierAutre,
    String? adresse,
    String? prenom,
    String? nom,
    String? telephone,
    String? email,
    String? motDePasse,
    String? confirmation,
    Map<TypeJustificatif, FichierJustificatif>? fichiers,
  }) => DossierInscription(
    nomEntreprise: nomEntreprise ?? this.nomEntreprise,
    metierCode: identical(metierCode, _inchange)
        ? this.metierCode
        : metierCode as String?,
    quartier: identical(quartier, _inchange)
        ? this.quartier
        : quartier as String?,
    quartierAutre: quartierAutre ?? this.quartierAutre,
    adresse: adresse ?? this.adresse,
    prenom: prenom ?? this.prenom,
    nom: nom ?? this.nom,
    telephone: telephone ?? this.telephone,
    email: email ?? this.email,
    motDePasse: motDePasse ?? this.motDePasse,
    confirmation: confirmation ?? this.confirmation,
    fichiers: fichiers ?? this.fichiers,
  );
}

const Object _inchange = Object();

/// Statut d'un justificatif (`en_attente`, `valide`, `rejete`).
enum StatutJustificatif {
  enAttente('En attente'),
  valide('Validé'),
  rejete('Refusé');

  const StatutJustificatif(this.libelle);

  final String libelle;

  static StatutJustificatif depuisApi(Object? valeur) => switch (valeur) {
    'valide' => valide,
    'rejete' => rejete,
    _ => enAttente,
  };
}

class JustificatifSuivi {
  const JustificatifSuivi({required this.type, required this.statut});

  /// Null si l'API envoie un type que l'application ne connaît pas.
  final TypeJustificatif? type;
  final StatutJustificatif statut;
}

/// Réponse de `GET /suivi/{jeton}`.
class SuiviDossier {
  const SuiviDossier({
    required this.nomEntreprise,
    required this.metier,
    required this.statutCompte,
    this.motifRefus,
    this.dateSoumission,
    this.justificatifs = const [],
  });

  final String nomEntreprise;

  /// Code du métier (`coiffure`) ; le nom affichable vient de `GET /metiers`.
  final String metier;

  /// `en_attente`, `refuse`, `actif`, `suspendu` ou `banni`.
  final String statutCompte;
  final String? motifRefus;
  final DateTime? dateSoumission;
  final List<JustificatifSuivi> justificatifs;

  bool get enAttente => statutCompte == 'en_attente';
  bool get refuse => statutCompte == 'refuse';
  bool get accepte => statutCompte == 'actif';

  factory SuiviDossier.depuisJson(Object? corps) {
    final j = corps is Map ? corps : const {};
    final motif = j['motifRefus']?.toString().trim();
    return SuiviDossier(
      nomEntreprise: (j['nomEntreprise'] ?? '').toString(),
      metier: (j['metier'] ?? '').toString(),
      statutCompte: (j['statutCompte'] ?? '').toString(),
      motifRefus: motif == null || motif.isEmpty ? null : motif,
      dateSoumission: DateTime.tryParse((j['dateSoumission'] ?? '').toString()),
      justificatifs: [
        for (final e in (j['justificatifs'] as List? ?? const []))
          if (e is Map)
            JustificatifSuivi(
              type: TypeJustificatif.depuisApi(e['typeDocument']?.toString()),
              statut: StatutJustificatif.depuisApi(e['statut']),
            ),
      ],
    );
  }
}
