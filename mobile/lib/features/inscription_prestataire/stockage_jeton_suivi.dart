import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Où le jeton de suivi du dossier est conservé. Il n'est jamais écrit en clair ailleurs (ni préférences, ni journaux).
abstract interface class StockageJetonSuivi {
  Future<String?> lire();
  Future<void> ecrire(String jeton);
  Future<void> effacer();
}

/// Stockage chiffré du système (Keystore Android), comme le jeton de session.
class StockageJetonSuiviSecurise implements StockageJetonSuivi {
  StockageJetonSuiviSecurise([FlutterSecureStorage? stockage])
    : _stockage = stockage ?? const FlutterSecureStorage();

  static const _cle = 'sopi_jeton_suivi';
  final FlutterSecureStorage _stockage;

  @override
  Future<String?> lire() async {
    try {
      return await _stockage.read(key: _cle);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> ecrire(String jeton) => _stockage.write(key: _cle, value: jeton);

  @override
  Future<void> effacer() async {
    try {
      await _stockage.delete(key: _cle);
    } catch (_) {}
  }
}

/// Stockage en mémoire (tests).
class StockageJetonSuiviMemoire implements StockageJetonSuivi {
  StockageJetonSuiviMemoire([this._jeton]);

  String? _jeton;

  /// Valeur actuellement stockée (pour les assertions des tests).
  String? get valeur => _jeton;

  @override
  Future<String?> lire() async => _jeton;

  @override
  Future<void> ecrire(String jeton) async => _jeton = jeton;

  @override
  Future<void> effacer() async => _jeton = null;
}

final stockageJetonSuiviProvider = Provider<StockageJetonSuivi>(
  (ref) => StockageJetonSuiviSecurise(),
);

/// Jeton de suivi connu (null s'il n'y en a pas). Chargé au démarrage ; le routeur et l'écran de connexion le lisent
/// de façon synchrone.
class JetonSuiviControleur extends Notifier<String?> {
  @override
  String? build() {
    Future.microtask(_charger);
    return null;
  }

  Future<void> _charger() async {
    final jeton = await ref.read(stockageJetonSuiviProvider).lire();
    if (ref.mounted && jeton != null && jeton.isNotEmpty) state = jeton;
  }

  Future<void> definir(String jeton) async {
    await ref.read(stockageJetonSuiviProvider).ecrire(jeton);
    if (ref.mounted) state = jeton;
  }

  Future<void> oublier() async {
    await ref.read(stockageJetonSuiviProvider).effacer();
    if (ref.mounted) state = null;
  }
}

final jetonSuiviProvider = NotifierProvider<JetonSuiviControleur, String?>(
  JetonSuiviControleur.new,
);
