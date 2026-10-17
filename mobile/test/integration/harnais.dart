import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show ProviderListenable;
import 'package:sopi/core/api/client_api.dart';
import 'package:sopi/core/api/erreurs.dart';
import 'package:sopi/core/api/stockage_jeton.dart';
import 'package:sopi/core/api_client.dart';
import 'package:sopi/features/auth/session.dart';

/// Adresse de l'API locale, injectée par `--dart-define=API_URL=http://127.0.0.1:8765/api/v1`
/// (le vrai `dioProvider` de l'application la lit, rien n'est simulé).
const urlLocale = String.fromEnvironment('API_URL');

/// Dossier du backend, pour vider le cache des limites de débit entre deux parcours.
const dossierBackend = String.fromEnvironment(
  'BACKEND_DIR',
  defaultValue: '../backend',
);

/// Secret de signature du faux webhook : le même que `DEXPAY_SECRET_KEY` dans le `.env` local jetable
/// écrit par `lancer_backend_local.sh`. Valeur factice, jamais utilisée en production.
const secretWebhookLocal = 'secret-factice-local-uniquement';

const adminEmail = 'admin-local@sopi.invalid';
const adminMotDePasse = 'MotDePasseLocal-123';

bool get integrationActive => urlLocale.startsWith('http://127.0.0.1');

/// Un utilisateur de l'application : un conteneur Riverpod avec le VRAI client HTTP et les VRAIS dépôts.
class Acteur {
  Acteur() : stockage = StockageJetonMemoire() {
    conteneur = ProviderContainer(
      overrides: [stockageJetonProvider.overrideWithValue(stockage)],
    );
  }

  final StockageJetonMemoire stockage;
  late final ProviderContainer conteneur;

  SessionControleur get session => conteneur.read(sessionProvider.notifier);
  EtatSession get etat => conteneur.read(sessionProvider);
  T lire<T>(ProviderListenable<T> p) => conteneur.read(p);
  Dio get dio => conteneur.read(dioProvider);

  void fermer() => conteneur.dispose();
}

/// Client brut pour ce que l'application ne fait pas (administrateur local, webhook signé).
class ClientBrut {
  ClientBrut(this.stockage)
    : dio = creerClientApi(
        stockage: stockage,
        surSessionExpiree: () {},
        urlBase: urlLocale,
      );

  final StockageJetonMemoire stockage;
  final Dio dio;

  static Future<ClientBrut> admin() async {
    final c = ClientBrut(StockageJetonMemoire());
    final r = await c.dio.post<Object?>(
      cheminConnexion,
      data: {'identifiant': adminEmail, 'password': adminMotDePasse},
    );
    await c.stockage.ecrire((r.data as Map)['token'] as String);
    return c;
  }
}

/// Envoie un faux webhook de paiement signé HMAC-SHA256 (aucun appel à un vrai prestataire de paiement).
Future<void> confirmerPaiementLocal({
  required String reference,
  required num montant,
  String statut = 'reussi',
}) async {
  final corps = jsonEncode({
    'reference': reference,
    'statut': statut,
    'montant': montant.toStringAsFixed(2),
  });
  final signature = Hmac(
    sha256,
    utf8.encode(secretWebhookLocal),
  ).convert(utf8.encode(corps)).toString();
  final dio = Dio(BaseOptions(baseUrl: urlLocale));
  await dio.post<Object?>(
    '/webhooks/paiement',
    data: corps,
    options: Options(
      contentType: 'application/json',
      headers: {'X-Webhook-Signature': signature},
    ),
  );
}

/// Dernier segment de l'URL de paiement : la référence du paiement chez le (faux) prestataire.
String referenceDepuisUrl(String urlPaiement) =>
    Uri.parse(urlPaiement).pathSegments.last;

/// Vide le cache du serveur local : remet à zéro les limites de débit (throttle) entre deux parcours.
Future<void> viderLimites() async {
  final r = await Process.run('php', [
    'artisan',
    'cache:clear',
    '--no-ansi',
  ], workingDirectory: dossierBackend);
  if (r.exitCode != 0) {
    throw StateError('cache:clear a échoué : ${r.stderr}');
  }
}

/// Exécute [action] et renvoie l'[ErreurApi] levée (échoue si rien n'est levé).
Future<ErreurApi> erreurDe(Future<Object?> Function() action) async {
  try {
    await action();
  } catch (e) {
    return ErreurApi.depuis(e);
  }
  throw StateError("Une erreur d'API était attendue, mais l'appel a réussi.");
}

/// Plus petit PNG valide (1×1) pour les photos et justificatifs de test.
final Uint8List petitPng = Uint8List.fromList(
  base64Decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  ),
);

/// Un PDF minimal pour les justificatifs.
final Uint8List petitPdf = Uint8List.fromList(
  utf8.encode(
    '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n'
    '2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n'
    '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 10 10]>>endobj\n'
    'trailer<</Root 1 0 R>>\n%%EOF\n',
  ),
);

/// Date `AAAA-MM-JJ` à [jours] jours d'aujourd'hui (heure de Dakar = UTC).
String jourDans(int jours) {
  final d = DateTime.now().toUtc().add(Duration(days: jours));
  String deux(int n) => n.toString().padLeft(2, '0');
  return '${d.year}-${deux(d.month)}-${deux(d.day)}';
}

/// Numéro de téléphone sénégalais unique pour un test (le serveur exige l'unicité).
String telephoneUnique(int n) =>
    '+22177${(1000000 + n).toString().padLeft(7, '0')}';
