/// Rôles gérés par l'application mobile (l'administrateur utilise le back-office web).
enum RoleSopi {
  client,
  prestataire;

  static RoleSopi? depuis(Object? brut) =>
      switch (brut?.toString().toLowerCase()) {
        'client' => RoleSopi.client,
        'prestataire' => RoleSopi.prestataire,
        _ => null,
      };
}

/// Bloc `prestataire` de `/me` (rôle prestataire uniquement).
class IdentitePrestataire {
  const IdentitePrestataire({
    required this.id,
    required this.nomEntreprise,
    this.metier,
    this.quartier,
  });

  final String id;
  final String nomEntreprise;
  final String? metier;
  final String? quartier;

  static IdentitePrestataire? depuisJson(Object? brut) {
    if (brut is! Map || brut['id'] == null) return null;
    String? texte(Object? v) {
      final s = (v ?? '').toString().trim();
      return s.isEmpty ? null : s;
    }

    return IdentitePrestataire(
      id: brut['id'].toString(),
      nomEntreprise: texte(brut['nomEntreprise']) ?? "",
      metier: texte(brut['metier']),
      quartier: texte(brut['quartier']),
    );
  }
}

class Utilisateur {
  const Utilisateur({
    required this.id,
    required this.role,
    this.prenom,
    this.nom,
    this.email,
    this.telephone,
    this.statutCompte,
    this.prestataire,
  });

  final String id;
  final RoleSopi role;
  final String? prenom;
  final String? nom;
  final String? email;
  final String? telephone;

  /// `actif`, `suspendu`… tel que renvoyé par `/me` (null si absent).
  final String? statutCompte;
  final IdentitePrestataire? prestataire;

  String get nomComplet =>
      [prenom, nom].whereType<String>().where((s) => s.isNotEmpty).join(' ');

  /// Lit un profil renvoyé par `/me` ou `/auth/connexion`, enveloppé ou non dans `data`.
  /// Renvoie null si le rôle n'est pas client/prestataire (ex. administrateur).
  static Utilisateur? depuisJson(Object? brut) {
    if (brut is! Map) return null;
    final json = brut['data'] is Map ? brut['data'] as Map : brut;
    final role = RoleSopi.depuis(json['role']);
    if (role == null) return null;
    return Utilisateur(
      id: json['id'].toString(),
      role: role,
      prenom: json['prenom'] as String?,
      nom: json['nom'] as String?,
      email: json['email'] as String?,
      telephone: json['telephone'] as String?,
      statutCompte: json['statutCompte'] as String?,
      prestataire: IdentitePrestataire.depuisJson(json['prestataire']),
    );
  }
}
