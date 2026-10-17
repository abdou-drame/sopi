import 'package:dio/dio.dart';

const messageErreurGenerique = 'Une erreur est survenue. Réessayez.';
const messageReseau = 'Pas de connexion. Vérifiez votre réseau.';
const messageSessionExpiree = 'Votre session a expiré. Reconnectez-vous.';

/// Erreur d'API présentable à l'utilisateur : toujours un [message] en français, prêt à afficher.
sealed class ErreurApi implements Exception {
  const ErreurApi(this.message);

  final String message;

  /// Convertit n'importe quelle erreur (DioException ou déjà convertie) en [ErreurApi].
  static ErreurApi depuis(Object erreur) {
    if (erreur is ErreurApi) return erreur;
    if (erreur is DioException) {
      final interne = erreur.error;
      if (interne is ErreurApi) return interne;
      return _depuisDio(erreur);
    }
    return const ErreurInconnue();
  }

  static ErreurApi _depuisDio(DioException e) {
    final reponse = e.response;
    if (reponse == null) {
      return switch (e.type) {
        DioExceptionType.connectionError ||
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout => const ErreurReseau(),
        _ => const ErreurInconnue(),
      };
    }
    final corps = reponse.data is Map
        ? Map<String, dynamic>.from(reponse.data as Map)
        : const <String, dynamic>{};
    final message =
        corps['message'] is String &&
            (corps['message'] as String).trim().isNotEmpty
        ? corps['message'] as String
        : null;

    switch (reponse.statusCode) {
      case 401:
        return ErreurSession(message ?? messageSessionExpiree);
      case 403:
        return ErreurAccesRefuse(
          message ?? "Vous n'avez pas le droit d'effectuer cette action.",
          motif: _texte(corps['motif'] ?? _dedans(corps['detail'], 'motif')),
          finSuspension: _date(
            corps['finSuspension'] ?? _dedans(corps['detail'], 'finSuspension'),
          ),
          statutCompte: _texte(corps['statutCompte']),
        );
      case 422:
        return ErreurValidation(
          message ?? 'Certaines informations sont invalides.',
          _champs(corps['errors']),
        );
      case 429:
        return ErreurTropDeRequetes(
          message ?? 'Trop de tentatives. Réessayez dans quelques instants.',
          delai: _delai(reponse.headers.value('retry-after')),
        );
      case 409:
      case 503:
      case 404:
        return ErreurServeur(
          message ?? messageErreurGenerique,
          reponse.statusCode!,
        );
      default:
        return const ErreurInconnue();
    }
  }

  static Object? _dedans(Object? objet, String cle) =>
      objet is Map ? objet[cle] : null;

  static String? _texte(Object? valeur) =>
      valeur is String && valeur.isNotEmpty ? valeur : null;

  static DateTime? _date(Object? valeur) =>
      valeur is String ? DateTime.tryParse(valeur) : null;

  static Map<String, List<String>> _champs(Object? brut) {
    if (brut is! Map) return const {};
    return {
      for (final entree in brut.entries)
        entree.key.toString(): entree.value is List
            ? [for (final m in entree.value as List) m.toString()]
            : [entree.value.toString()],
    };
  }

  /// Retry-After en secondes (la forme « date HTTP » n'est pas utilisée par Laravel).
  static Duration? _delai(String? brut) {
    final secondes = int.tryParse(brut?.trim() ?? '');
    return secondes == null || secondes < 0
        ? null
        : Duration(seconds: secondes);
  }

  @override
  String toString() => message;
}

/// 401 : jeton absent, invalide ou expiré (ou identifiants faux sur la connexion).
class ErreurSession extends ErreurApi {
  const ErreurSession(super.message);
}

/// 422 : messages par champ (`errors`). [premierMessage] sert aux champs affichés sous un `ChampFormulaire`.
class ErreurValidation extends ErreurApi {
  const ErreurValidation(super.message, [this.champs = const {}]);

  final Map<String, List<String>> champs;

  String? premierMessage(String champ) {
    final messages = champs[champ];
    return messages == null || messages.isEmpty ? null : messages.first;
  }
}

/// 429 : trop de tentatives. [delai] vient de l'en-tête Retry-After (null s'il est absent).
class ErreurTropDeRequetes extends ErreurApi {
  const ErreurTropDeRequetes(super.message, {this.delai});

  final Duration? delai;
}

/// 403 : action refusée. Sur la connexion, [motif] et [finSuspension] décrivent la sanction ou le refus.
class ErreurAccesRefuse extends ErreurApi {
  const ErreurAccesRefuse(
    super.message, {
    this.motif,
    this.finSuspension,
    this.statutCompte,
  });

  final String? motif;
  final DateTime? finSuspension;

  /// Statut du compte donné par la connexion : `en_attente`, `refuse`, `suspendu` ou `banni`. Null s'il est absent.
  final String? statutCompte;
}

/// 404, 409 ou 503 : le message du serveur est affiché tel quel.
class ErreurServeur extends ErreurApi {
  const ErreurServeur(super.message, this.statut);

  final int statut;
}

/// Réseau coupé, serveur injoignable ou délai dépassé.
class ErreurReseau extends ErreurApi {
  const ErreurReseau() : super(messageReseau);
}

/// Tout autre cas.
class ErreurInconnue extends ErreurApi {
  const ErreurInconnue() : super(messageErreurGenerique);
}
