import 'package:intl/intl.dart';

import '../../core/format/formateurs.dart';

/// « 4,8 » (une décimale, virgule).
String formaterNote(num note) => note.toStringAsFixed(1).replaceAll('.', ',');

/// « 45 min », « 1 h », « 1 h 30 ».
String formaterDuree(int minutes) {
  if (minutes < 60) return '$minutes min';
  final h = minutes ~/ 60;
  final m = minutes % 60;
  return m == 0 ? '$h h' : '$h h ${m.toString().padLeft(2, '0')}';
}

/// « 10h00 » (heure UTC = heure de Dakar).
String formaterHeure(DateTime date) {
  final d = date.toUtc();
  return '${d.hour.toString().padLeft(2, '0')}h${d.minute.toString().padLeft(2, '0')}';
}

/// « AAAA-MM-JJ » (jour UTC).
String formaterJourApi(DateTime date) {
  final d = date.toUtc();
  return '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}

DateTime debutDuJour(DateTime date) {
  final d = date.toUtc();
  return DateTime.utc(d.year, d.month, d.day);
}

/// « Demain, 10h00 », « Aujourd'hui, 10h00 » ou « Sam. 24 oct., 10h00 ». Nécessite `initialiserFormats`.
String libelleProchaineReservation(DateTime debut, DateTime maintenant) {
  final ecart = debutDuJour(debut).difference(debutDuJour(maintenant)).inDays;
  if (ecart == 0) return "Aujourd'hui, ${formaterHeure(debut)}";
  if (ecart == 1) return 'Demain, ${formaterHeure(debut)}';
  return formaterDate(debut);
}

/// « Sam. 24 oct. » (sans l'heure). Nécessite `initialiserFormats`.
String formaterJourCourt(DateTime date) {
  final texte = DateFormat('EEE d MMM', 'fr_FR').format(date.toUtc());
  return '${texte[0].toUpperCase()}${texte.substring(1)}';
}

/// « Octobre 2026 ». Nécessite `initialiserFormats`.
String formaterMoisAnnee(DateTime date) {
  final texte = DateFormat('MMMM y', 'fr_FR').format(date.toUtc());
  return '${texte[0].toUpperCase()}${texte.substring(1)}';
}

/// Abréviation du jour de la semaine (« Ven. »). Nécessite `initialiserFormats`.
String formaterJourSemaine(DateTime date) {
  final texte = DateFormat('E', 'fr_FR').format(date.toUtc());
  final t = '${texte[0].toUpperCase()}${texte.substring(1)}';
  return t.endsWith('.') ? t : '$t.';
}
