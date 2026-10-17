import 'package:flutter/services.dart';

/// Ne garde que les chiffres et pose les séparateurs d'un motif : `##:##` pour une heure, `##/##/####` pour une date.
class SaisieMasquee extends TextInputFormatter {
  const SaisieMasquee(this.motif);

  /// Heure « HH:MM ».
  static const heure = SaisieMasquee('##:##');

  /// Date « JJ/MM/AAAA ».
  static const date = SaisieMasquee('##/##/####');

  final String motif;

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue ancien,
    TextEditingValue nouveau,
  ) {
    final chiffres = nouveau.text.replaceAll(RegExp(r'\D'), '');
    final sortie = StringBuffer();
    var i = 0;
    for (final c in motif.split('')) {
      if (i >= chiffres.length) break;
      if (c == '#') {
        sortie.write(chiffres[i++]);
      } else {
        sortie.write(c);
      }
    }
    final texte = sortie.toString();
    return TextEditingValue(
      text: texte,
      selection: TextSelection.collapsed(offset: texte.length),
    );
  }
}

/// « 08:00 » → « 08:00 » ; `null` si l'heure n'est pas valide.
String? lireHeure(String saisie) {
  final m = RegExp(r'^(\d{2}):(\d{2})$').firstMatch(saisie.trim());
  if (m == null) return null;
  final h = int.parse(m.group(1)!);
  final min = int.parse(m.group(2)!);
  return h < 24 && min < 60 ? saisie.trim() : null;
}

/// « 26/10/2026 » → `2026-10-26` (format de l'API) ; `null` si la date n'existe pas.
String? lireDate(String saisie) {
  final m = RegExp(r'^(\d{2})/(\d{2})/(\d{4})$').firstMatch(saisie.trim());
  if (m == null) return null;
  final j = int.parse(m.group(1)!);
  final mo = int.parse(m.group(2)!);
  final a = int.parse(m.group(3)!);
  final d = DateTime.utc(a, mo, j);
  if (d.year != a || d.month != mo || d.day != j) return null;
  return '${m.group(3)}-${m.group(2)}-${m.group(1)}';
}
