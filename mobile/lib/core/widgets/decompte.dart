import 'dart:async';

import 'package:flutter/material.dart';

import '../theme/couleurs.dart';

/// « 04:59 » sous une heure, « 1 h 52 » à partir d'une heure, « 00:00 » à zéro. Arrondi à la seconde supérieure.
String formaterDecompte(Duration restant) {
  if (restant <= Duration.zero) return '00:00';
  final secondes = (restant.inMilliseconds / 1000).ceil();
  if (secondes >= 3600) {
    final h = secondes ~/ 3600;
    final m = (secondes % 3600) ~/ 60;
    return '$h h ${m.toString().padLeft(2, '0')}';
  }
  final m = secondes ~/ 60;
  final s = secondes % 60;
  return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
}

/// Compte à rebours jusqu'à [expiration] (verrou de créneau, délai de paiement, délai de réponse…).
/// [onExpire] est appelé une seule fois quand zéro est atteint (ou tout de suite si la date est déjà passée).
class Decompte extends StatefulWidget {
  const Decompte({
    super.key,
    required this.expiration,
    this.onExpire,
    this.style,
    this.libelleExpire = 'Expiré',
    this.maintenant = DateTime.now,
  });

  final DateTime expiration;
  final VoidCallback? onExpire;
  final TextStyle? style;
  final String libelleExpire;

  /// Horloge injectable pour les tests.
  final DateTime Function() maintenant;

  @override
  State<Decompte> createState() => _DecompteState();
}

class _DecompteState extends State<Decompte> {
  Timer? _minuteur;
  late Duration _restant;
  bool _preveni = false;

  @override
  void initState() {
    super.initState();
    _restant = _calculer();
    if (_restant <= Duration.zero) {
      _prevenir();
    } else {
      _minuteur = Timer.periodic(const Duration(seconds: 1), (_) => _tic());
    }
  }

  Duration _calculer() => widget.expiration.difference(widget.maintenant());

  void _tic() {
    final restant = _calculer();
    if (!mounted) return;
    setState(() => _restant = restant);
    if (restant <= Duration.zero) {
      _minuteur?.cancel();
      _prevenir();
    }
  }

  void _prevenir() {
    if (_preveni) return;
    _preveni = true;
    // Après le build en cours, pour pouvoir changer d'état dans le parent.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) widget.onExpire?.call();
    });
  }

  @override
  void didUpdateWidget(Decompte ancien) {
    super.didUpdateWidget(ancien);
    if (ancien.expiration != widget.expiration) {
      _preveni = false;
      _minuteur?.cancel();
      _restant = _calculer();
      if (_restant <= Duration.zero) {
        _prevenir();
      } else {
        _minuteur = Timer.periodic(const Duration(seconds: 1), (_) => _tic());
      }
    }
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final expire = _restant <= Duration.zero;
    final style = (widget.style ?? Theme.of(context).textTheme.titleMedium)
        ?.copyWith(
          fontFeatures: const [FontFeature.tabularFigures()],
          color: expire ? CouleursSopi.erreurTexte : null,
        );
    return Text(
      expire ? widget.libelleExpire : formaterDecompte(_restant),
      style: style,
    );
  }
}
