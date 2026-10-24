/// Validations locales des formulaires d'accès. Elles évitent un aller-retour réseau pour une erreur évidente ;
/// le serveur reste l'autorité (il revalide tout).
abstract final class ValidateursAcces {
  static const messageTelephone =
      'Le numéro de téléphone doit être un mobile sénégalais valide (ex. : 77 123 45 67).';

  static final _mobile = RegExp(r'^7[05678]\d{7}$');
  static final _email = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]{2,}$');

  /// Numéro national à 9 chiffres (sans indicatif ni espaces) si [saisie] est un mobile sénégalais, sinon null.
  /// Accepte « +221 77 123 45 67 », « 00221771234567 », « 77 123 45 67 », « 77.123.45.67 »…
  static String? mobileNational(String saisie) {
    var chiffres = saisie.trim().replaceAll(RegExp(r'[\s.\-]'), '');
    if (chiffres.startsWith('+221')) {
      chiffres = chiffres.substring(4);
    } else if (chiffres.startsWith('00221')) {
      chiffres = chiffres.substring(5);
    }
    return _mobile.hasMatch(chiffres) ? chiffres : null;
  }

  /// Numéro au format envoyé à l'API : « +221771234567 ».
  static String? telephoneApi(String saisie) {
    final national = mobileNational(saisie);
    return national == null ? null : '+221$national';
  }

  static String? nom(String valeur, String libelle) {
    final v = valeur.trim();
    if (v.isEmpty) return '$libelle est obligatoire.';
    if (v.length > 100) return '$libelle ne doit pas dépasser 100 caractères.';
    return null;
  }

  static String? telephone(String valeur) {
    if (valeur.trim().isEmpty) {
      return 'Le numéro de téléphone est obligatoire.';
    }
    return mobileNational(valeur) == null ? messageTelephone : null;
  }

  /// E-mail obligatoire (inscription, mot de passe oublié).
  static String? emailObligatoire(String valeur) {
    final v = valeur.trim();
    if (v.isEmpty) return "L'adresse e-mail est obligatoire.";
    return emailValide(v) ? null : "L'adresse e-mail n'est pas valide.";
  }

  static bool emailValide(String valeur) => _email.hasMatch(valeur.trim());

  static String? motDePasse(String valeur) {
    if (valeur.isEmpty) return 'Le mot de passe est obligatoire.';
    if (valeur.length < 8) {
      return 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    return null;
  }

  static String? confirmation(String motDePasse, String confirmation) {
    if (confirmation.isEmpty) return 'Confirmez votre mot de passe.';
    return motDePasse == confirmation
        ? null
        : 'Les deux mots de passe ne sont pas identiques.';
  }
}
