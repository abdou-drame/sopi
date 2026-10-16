/// Configuration injectée au build : --dart-define=API_URL=https://.../api/v1
///
/// Rétrocompatibilité : le workflow CI existant injecte `API_BASE_URL` (hôte seul, sans /api/v1).
/// Si `API_URL` est absent mais `API_BASE_URL` présent, on y ajoute `/api/v1`.
const _apiUrl = String.fromEnvironment('API_URL');
const _apiBaseUrl = String.fromEnvironment('API_BASE_URL');

const urlApiParDefaut = 'https://sopi-api.duckdns.org/api/v1';

/// URL de l'API (avec /api/v1), sans barre finale.
String urlApi() {
  final brute = _apiUrl.isNotEmpty
      ? _apiUrl
      : _apiBaseUrl.isNotEmpty
      ? '${_apiBaseUrl.replaceAll(RegExp(r'/+$'), '')}/api/v1'
      : urlApiParDefaut;
  return brute.replaceAll(RegExp(r'/+$'), '');
}
