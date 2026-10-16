import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

/// Ouvre une adresse hors de l'application (appel téléphonique, page de paiement).
/// Remplaçable dans les tests : renvoie faux si rien n'a pu être ouvert.
typedef LanceurUrl = Future<bool> Function(Uri adresse);

final lanceurUrlProvider = Provider<LanceurUrl>(
  (ref) => (adresse) async {
    try {
      return await launchUrl(adresse, mode: LaunchMode.externalApplication);
    } catch (_) {
      return false;
    }
  },
);
