import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'environnement.dart';

/// Adresse du support (vide si non configurée). Surchargeable dans les tests.
final supportEmailProvider = Provider<String>((ref) => supportEmail);

/// Ouvre une adresse dans une application externe (messagerie). Surchargeable dans les tests : aucun appel
/// système réel n'y est fait.
final lanceurUrlProvider = Provider<Future<bool> Function(Uri)>(
  (ref) =>
      (uri) => launchUrl(uri, mode: LaunchMode.externalApplication),
);

/// Ouvre la messagerie vers le support. Renvoie faux si le support n'est pas configuré ou si rien ne s'ouvre.
Future<bool> contacterSupport(WidgetRef ref) async {
  final adresse = ref.read(supportEmailProvider);
  if (adresse.isEmpty) return false;
  try {
    return await ref.read(lanceurUrlProvider)(
      Uri(scheme: 'mailto', path: adresse),
    );
  } catch (_) {
    return false;
  }
}
