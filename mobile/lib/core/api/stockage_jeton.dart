import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Où le jeton Sanctum est conservé. Abstraction pour pouvoir le remplacer dans les tests.
abstract interface class StockageJeton {
  Future<String?> lire();
  Future<void> ecrire(String jeton);
  Future<void> effacer();
}

/// Stockage chiffré du système (Keystore Android).
class StockageJetonSecurise implements StockageJeton {
  StockageJetonSecurise([FlutterSecureStorage? stockage])
    : _stockage = stockage ?? const FlutterSecureStorage();

  static const _cle = 'sopi_jeton';
  final FlutterSecureStorage _stockage;

  @override
  Future<String?> lire() async {
    try {
      return await _stockage.read(key: _cle);
    } catch (_) {
      // Keystore illisible (réinstallation, restauration de sauvegarde) : on repart déconnecté.
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
class StockageJetonMemoire implements StockageJeton {
  StockageJetonMemoire([this._jeton]);

  String? _jeton;

  @override
  Future<String?> lire() async => _jeton;

  @override
  Future<void> ecrire(String jeton) async => _jeton = jeton;

  @override
  Future<void> effacer() async => _jeton = null;
}
