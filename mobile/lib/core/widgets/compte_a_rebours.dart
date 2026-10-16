import 'dart:async';

import 'package:flutter/material.dart';

/// Compte à rebours de [duree] qui descend d'une seconde par tic (blocage de connexion, renvoi d'un lien…).
/// [builder] dessine l'écran à partir du temps restant ; [onFin] est appelé une seule fois à zéro.
/// Le décompte suit le minuteur de l'application : il s'arrête si le système suspend l'application, le serveur
/// restant seul juge (un nouvel essai trop tôt renvoie un 429 avec un délai à jour).
class CompteARebours extends StatefulWidget {
  const CompteARebours({
    super.key,
    required this.duree,
    required this.builder,
    this.onFin,
  });

  final Duration duree;
  final Widget Function(BuildContext context, Duration restant) builder;
  final VoidCallback? onFin;

  @override
  State<CompteARebours> createState() => _CompteAReboursState();
}

class _CompteAReboursState extends State<CompteARebours> {
  Timer? _minuteur;
  late Duration _restant;

  @override
  void initState() {
    super.initState();
    _demarrer();
  }

  void _demarrer() {
    _minuteur?.cancel();
    _restant = widget.duree;
    if (_restant <= Duration.zero) {
      _terminer();
      return;
    }
    _minuteur = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      final suivant = _restant - const Duration(seconds: 1);
      if (suivant <= Duration.zero) {
        setState(() => _restant = Duration.zero);
        _minuteur?.cancel();
        _terminer();
      } else {
        setState(() => _restant = suivant);
      }
    });
  }

  void _terminer() {
    // Après le build en cours : le parent peut changer d'état.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) widget.onFin?.call();
    });
  }

  @override
  void didUpdateWidget(CompteARebours ancien) {
    super.didUpdateWidget(ancien);
    if (ancien.duree != widget.duree) _demarrer();
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.builder(context, _restant);
}
