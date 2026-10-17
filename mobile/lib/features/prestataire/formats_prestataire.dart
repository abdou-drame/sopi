/// « Il reste 11 h 20 » : temps restant avant l'échéance de réponse, calculé avec l'heure du téléphone.
/// Sous une heure : « Il reste 45 min ». À zéro ou après : « Délai dépassé ».
String formaterReste(Duration restant) {
  if (restant <= Duration.zero) return 'Délai dépassé';
  // Arrondi à la minute supérieure : « 0 min » ne s'affiche jamais tant qu'il reste du temps.
  final minutes = (restant.inSeconds / 60).ceil();
  if (minutes < 60) return 'Il reste $minutes min';
  final h = minutes ~/ 60;
  final m = minutes % 60;
  return 'Il reste $h h ${m.toString().padLeft(2, '0')}';
}
