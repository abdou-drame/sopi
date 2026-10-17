import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';

const _espaceInsecable = ' ';

/// À appeler une fois au démarrage (et dans les tests qui formatent des dates).
Future<void> initialiserFormats() => initializeDateFormatting('fr_FR');

/// « 1 500 FCFA » : séparateur de milliers et espace avant FCFA insécables.
String formaterArgent(num montant) {
  final entier = montant.round().abs().toString();
  final groupes = StringBuffer();
  for (var i = 0; i < entier.length; i++) {
    if (i > 0 && (entier.length - i) % 3 == 0) groupes.write(_espaceInsecable);
    groupes.write(entier[i]);
  }
  return '${montant < 0 ? '-' : ''}$groupes${_espaceInsecable}FCFA';
}

/// « Sam. 24 oct., 10h00 ». L'API envoie des dates ISO 8601 ; Africa/Dakar est à UTC+0 toute l'année,
/// on affiche donc l'heure UTC, quel que soit le fuseau du téléphone.
/// Nécessite [initialiserFormats].
String formaterDate(DateTime date) {
  final d = date.toUtc();
  final jour = DateFormat('EEE d MMM', 'fr_FR').format(d);
  final heure =
      '${d.hour.toString().padLeft(2, '0')}h${d.minute.toString().padLeft(2, '0')}';
  return '${jour[0].toUpperCase()}${jour.substring(1)}, $heure';
}

/// « 16 oct. 2026 » (date seule, heure UTC comme [formaterDate]). Nécessite [initialiserFormats].
String formaterDateCourte(DateTime date) =>
    DateFormat('d MMM y', 'fr_FR').format(date.toUtc());

/// « 22 oct. » (jour et mois, sans l'année). Nécessite [initialiserFormats].
String formaterJourMois(DateTime date) =>
    DateFormat('d MMM', 'fr_FR').format(date.toUtc());

/// « +221 77 123 45 67 » à partir de +221771234567, 221771234567 ou 771234567. Autre valeur : renvoyée telle quelle
/// (par exemple un numéro masqué comme +221******567).
String formaterTelephone(String numero) {
  final chiffres = numero.replaceAll(RegExp(r'[\s.\-()]'), '');
  final correspondance = RegExp(r'^(?:\+?221)?(\d{9})$').firstMatch(chiffres);
  if (correspondance == null) return numero;
  final n = correspondance.group(1)!;
  return '+221 ${n.substring(0, 2)} ${n.substring(2, 5)} ${n.substring(5, 7)} ${n.substring(7)}';
}
