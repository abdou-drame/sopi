import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/client_api.dart';
import '../../core/api/erreurs.dart';
import '../../core/api_client.dart';
import 'utilisateur.dart';

/// État de la session, base des redirections du routeur.
sealed class EtatSession {
  const EtatSession();
}

/// Démarrage : on vérifie le jeton stocké.
class SessionInconnue extends EtatSession {
  const SessionInconnue();
}

class NonConnecte extends EtatSession {
  const NonConnecte();
}

class Connecte extends EtatSession {
  const Connecte(this.utilisateur);
  final Utilisateur utilisateur;
}

/// Compte suspendu, refusé ou banni : connexion refusée (403) avec le motif du serveur.
class Restreint extends EtatSession {
  const Restreint({
    required this.message,
    this.motif,
    this.finSuspension,
    this.statutCompte,
  });
  final String message;
  final String? motif;
  final DateTime? finSuspension;

  /// `en_attente`, `refuse`, `suspendu` ou `banni` (null si le serveur ne l'envoie pas).
  final String? statutCompte;
}

const messageRoleNonSupporte =
    "Ce compte n'est pas utilisable sur l'application mobile.";

class SessionControleur extends Notifier<EtatSession> {
  @override
  EtatSession build() {
    Future.microtask(restaurer);
    return const SessionInconnue();
  }

  Dio get _dio => ref.read(dioProvider);

  /// Au démarrage : relit le jeton stocké et le valide par `GET /me`.
  Future<void> restaurer() async {
    final stockage = ref.read(stockageJetonProvider);
    final jeton = await stockage.lire();
    if (jeton == null || jeton.isEmpty) {
      _definir(const NonConnecte());
      return;
    }
    try {
      final reponse = await _dio.get<Object?>('/me');
      final utilisateur = Utilisateur.depuisJson(reponse.data);
      if (utilisateur == null) {
        await _oublier();
        return;
      }
      _definir(Connecte(utilisateur));
    } catch (e) {
      final erreur = ErreurApi.depuis(e);
      switch (erreur) {
        case ErreurAccesRefuse():
          await _oublier();
          _definir(_restreint(erreur));
        case ErreurSession():
          await _oublier();
        default:
          // Réseau coupé ou serveur en panne : on garde le jeton, l'utilisateur peut réessayer.
          _definir(const NonConnecte());
      }
    }
  }

  /// Connexion. Lève une [ErreurApi] (identifiants faux, 422, 429, réseau…) sauf pour un 403,
  /// qui passe l'état à [Restreint].
  Future<void> connecter(String identifiant, String motDePasse) =>
      _ouvrirSession(cheminConnexion, {
        'identifiant': identifiant,
        'password': motDePasse,
      });

  /// Inscription d'un client. Le serveur répond 201 avec `{token, user}` : la session s'ouvre aussitôt.
  /// Lève une [ErreurApi] (422 par champ, 429, réseau…).
  Future<void> inscrire(Map<String, Object?> champs) =>
      _ouvrirSession(cheminInscription, champs);

  Future<void> _ouvrirSession(
    String chemin,
    Map<String, Object?> donnees,
  ) async {
    try {
      final reponse = await _dio.post<Object?>(chemin, data: donnees);
      final corps = reponse.data is Map ? reponse.data as Map : const {};
      final jeton = corps['token'];
      final utilisateur = Utilisateur.depuisJson(corps['user']);
      if (jeton is! String || jeton.isEmpty) throw const ErreurInconnue();

      if (utilisateur == null) {
        // Administrateur : pas d'accès mobile. On ferme le jeton qui vient d'être créé.
        await _fermerJetonDistant(jeton);
        throw const ErreurAccesRefuse(messageRoleNonSupporte);
      }
      await ref.read(stockageJetonProvider).ecrire(jeton);
      _definir(Connecte(utilisateur));
    } catch (e) {
      final erreur = ErreurApi.depuis(e);
      if (erreur is ErreurAccesRefuse &&
          erreur.message != messageRoleNonSupporte) {
        _definir(_restreint(erreur));
        return;
      }
      throw erreur;
    }
  }

  /// Déconnexion : appel serveur, puis effacement local même si l'appel échoue.
  Future<void> deconnecter() async {
    try {
      await _dio.post<Object?>('/auth/deconnexion');
    } catch (_) {
      // Hors ligne ou jeton déjà invalide : la session locale est effacée quand même.
    }
    await _oublier();
  }

  /// Appelé par le client HTTP sur un 401 : le jeton n'est plus valable.
  void expirer() {
    if (state is! Connecte) return;
    _oublier();
  }

  /// Sort de l'état « compte restreint » (retour à l'écran de connexion).
  void quitterRestriction() {
    if (state is Restreint) state = const NonConnecte();
  }

  void _definir(EtatSession etat) {
    if (ref.mounted) state = etat;
  }

  Future<void> _oublier() async {
    if (!ref.mounted) return;
    await ref.read(stockageJetonProvider).effacer();
    _definir(const NonConnecte());
  }

  Future<void> _fermerJetonDistant(String jeton) async {
    try {
      await _dio.post<Object?>(
        '/auth/deconnexion',
        options: Options(headers: {'Authorization': 'Bearer $jeton'}),
      );
    } catch (_) {}
  }

  Restreint _restreint(ErreurAccesRefuse e) => Restreint(
    message: e.message,
    motif: e.motif,
    finSuspension: e.finSuspension,
    statutCompte: e.statutCompte,
  );
}

final sessionProvider = NotifierProvider<SessionControleur, EtatSession>(
  SessionControleur.new,
);
