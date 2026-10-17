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

/// « Il vous reste 11 h 20 pour répondre. » (détail d'une demande), ou le constat du délai dépassé.
String formaterResteVous(Duration restant) {
  if (restant <= Duration.zero) {
    return 'Le délai de réponse est dépassé. La demande va être annulée.';
  }
  final reste = formaterReste(
    restant,
  ).replaceFirst('Il reste', 'Il vous reste');
  return '$reste pour répondre. Passé ce délai, la demande est annulée.';
}
