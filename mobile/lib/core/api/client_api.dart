import 'package:dio/dio.dart';

import '../config/environnement.dart';
import 'erreurs.dart';
import 'stockage_jeton.dart';

const cheminConnexion = '/auth/connexion';

/// Construit le client HTTP unique de l'application.
///
/// - `Authorization: Bearer` si un jeton est stocké, `Accept-Language: fr` toujours ;
/// - toute erreur HTTP ressort en [DioException] dont `error` est une [ErreurApi] (messages en français) ;
/// - un 401 hors connexion appelle [surSessionExpiree] (la session locale est alors effacée).
Dio creerClientApi({
  required StockageJeton stockage,
  required void Function() surSessionExpiree,
  String? urlBase,
  HttpClientAdapter? adaptateur,
}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: urlBase ?? urlApi(),
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 15),
      headers: {'Accept': 'application/json', 'Accept-Language': 'fr'},
    ),
  );
  if (adaptateur != null) dio.httpClientAdapter = adaptateur;

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        if (!options.headers.containsKey('Authorization')) {
          final jeton = await stockage.lire();
          if (jeton != null && jeton.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $jeton';
          }
        }
        handler.next(options);
      },
      onError: (e, handler) {
        final erreur = ErreurApi.depuis(e);
        if (erreur is ErreurSession &&
            e.requestOptions.path != cheminConnexion) {
          surSessionExpiree();
        }
        handler.reject(
          DioException(
            requestOptions: e.requestOptions,
            response: e.response,
            type: e.type,
            error: erreur,
          ),
        );
      },
    ),
  );
  return dio;
}
