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

/// Ne garde que les chiffres et groupe les milliers par un espace : « 20000 » → « 20 000 ».
class SaisieMilliers extends TextInputFormatter {
  const SaisieMilliers();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue ancien,
    TextEditingValue nouveau,
  ) {
    final chiffres = nouveau.text.replaceAll(RegExp(r'\D'), '');
    final sortie = StringBuffer();
    for (var i = 0; i < chiffres.length; i++) {
      if (i > 0 && (chiffres.length - i) % 3 == 0) sortie.write(' ');
      sortie.write(chiffres[i]);
    }
    final texte = sortie.toString();
    return TextEditingValue(
      text: texte,
      selection: TextSelection.collapsed(offset: texte.length),
    );
  }
}

/// « 20 000 » → 20000 ; `null` si la saisie est vide.
int? lireMontant(String saisie) =>
    int.tryParse(saisie.replaceAll(RegExp(r'\D'), ''));

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
