import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import 'apparition.dart';

/// Retour au toucher : le contenu se réduit à l'appui et rebondit au relâchement. N'intercepte aucun geste
/// (simple écoute des pointeurs) ; sans animation (accessibilité) ou si [actif] est faux, rien ne bouge.
class Appui extends StatefulWidget {
  const Appui({
    super.key,
    required this.child,
    this.echelle = 0.96,
    this.actif = true,
  });

  final Widget child;
  final double echelle;
  final bool actif;

  @override
  State<Appui> createState() => _EtatAppui();
}

class _EtatAppui extends State<Appui> {
  bool _enBas = false;

  void _definir(bool valeur) {
    if (_enBas != valeur && mounted) setState(() => _enBas = valeur);
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.actif || !animationsActives(context)) return widget.child;
    return Listener(
      onPointerDown: (_) => _definir(true),
      onPointerUp: (_) => _definir(false),
      onPointerCancel: (_) => _definir(false),
      child: AnimatedScale(
        scale: _enBas ? widget.echelle : 1,
        duration: Duration(milliseconds: _enBas ? 90 : 260),
        curve: _enBas ? Curves.easeOut : Curves.easeOutBack,
        child: widget.child,
      ),
    );
  }
}

/// Petite impulsion (1 → 1,05 → 1) quand [valeur] change après le premier affichage et que l'ancienne valeur
/// existait (un montant qui vient d'être crédité, par exemple). Ne joue pas au chargement initial.
class Impulsion extends StatefulWidget {
  const Impulsion({super.key, required this.valeur, required this.child});

  final Object? valeur;
  final Widget child;

  @override
  State<Impulsion> createState() => _EtatImpulsion();
}

class _EtatImpulsion extends State<Impulsion>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 520),
  );

  @override
  void didUpdateWidget(Impulsion ancien) {
    super.didUpdateWidget(ancien);
    if (ancien.valeur != null &&
        widget.valeur != null &&
        ancien.valeur != widget.valeur &&
        animationsActives(context)) {
      _c.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: TweenSequence<double>([
        TweenSequenceItem(
          tween: Tween(
            begin: 1.0,
            end: 1.05,
          ).chain(CurveTween(curve: Curves.easeOut)),
          weight: 40,
        ),
        TweenSequenceItem(
          tween: Tween(
            begin: 1.05,
            end: 1.0,
          ).chain(CurveTween(curve: Curves.easeOutBack)),
          weight: 60,
        ),
      ]).animate(_c),
      child: widget.child,
    );
  }
}

/// Point qui pulse quelques fois (attente d'une réponse). Durée finie : ne bloque jamais un test ni la batterie.
class PointPulsant extends StatefulWidget {
  const PointPulsant({
    super.key,
    this.couleur = CouleursSopi.bleu,
    this.taille = 8,
  });

  final Color couleur;
  final double taille;

  @override
  State<PointPulsant> createState() => _EtatPointPulsant();
}

class _EtatPointPulsant extends State<PointPulsant>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1300),
  );
  bool _demarre = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_demarre && animationsActives(context)) {
      _demarre = true;
      _c.repeat(count: 8);
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = widget.taille;
    return SizedBox(
      width: t * 2.6,
      height: t * 2.6,
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, _) {
          final v = _c.value;
          return CustomPaint(
            painter: _PeintrePoint(
              widget.couleur,
              t,
              _demarre ? v : 0,
              _demarre,
            ),
          );
        },
      ),
    );
  }
}

class _PeintrePoint extends CustomPainter {
  _PeintrePoint(this.couleur, this.taille, this.v, this.anime);

  final Color couleur;
  final double taille;
  final double v;
  final bool anime;

  @override
  void paint(Canvas canvas, Size size) {
    final centre = size.center(Offset.zero);
    if (anime) {
      canvas.drawCircle(
        centre,
        taille / 2 + taille * 0.9 * v,
        Paint()..color = couleur.withValues(alpha: 0.35 * (1 - v)),
      );
    }
    canvas.drawCircle(centre, taille / 2, Paint()..color = couleur);
  }

  @override
  bool shouldRepaint(covariant _PeintrePoint ancien) =>
      ancien.v != v || ancien.couleur != couleur;
}

/// Coche qui se dessine trait par trait ([delai] puis 600 ms). Sans animation, elle est affichée entière.
class CocheDessinee extends StatefulWidget {
  const CocheDessinee({
    super.key,
    required this.taille,
    required this.couleur,
    this.delai = const Duration(milliseconds: 350),
  });

  final double taille;
  final Color couleur;
  final Duration delai;

  @override
  State<CocheDessinee> createState() => _EtatCocheDessinee();
}

class _EtatCocheDessinee extends State<CocheDessinee>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: widget.delai + const Duration(milliseconds: 600),
  );
  bool _demarre = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_demarre) return;
    _demarre = true;
    if (animationsActives(context)) {
      _c.forward();
    } else {
      _c.value = 1;
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final debut =
        widget.delai.inMilliseconds /
        (widget.delai + const Duration(milliseconds: 600)).inMilliseconds;
    return ExcludeSemantics(
      child: SizedBox(
        width: widget.taille,
        height: widget.taille,
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) => CustomPaint(
            painter: _PeintreCoche(
              widget.couleur,
              Curves.easeOutCubic.transform(
                ((_c.value - debut) / (1 - debut)).clamp(0.0, 1.0),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PeintreCoche extends CustomPainter {
  _PeintreCoche(this.couleur, this.progression);

  final Color couleur;
  final double progression;

  @override
  void paint(Canvas canvas, Size size) {
    final chemin = Path()
      ..moveTo(size.width * 0.22, size.height * 0.53)
      ..lineTo(size.width * 0.43, size.height * 0.73)
      ..lineTo(size.width * 0.79, size.height * 0.31);
    final mesure = chemin.computeMetrics().first;
    canvas.drawPath(
      mesure.extractPath(0, mesure.length * progression),
      Paint()
        ..color = couleur
        ..style = PaintingStyle.stroke
        ..strokeWidth = size.width * 0.1
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
  }

  @override
  bool shouldRepaint(covariant _PeintreCoche ancien) =>
      ancien.progression != progression || ancien.couleur != couleur;
}

/// Confettis qui jaillissent du centre puis retombent (1,8 s, une seule fois). Décoratif, sans interaction.
class Confettis extends StatefulWidget {
  const Confettis({
    super.key,
    this.taille = 280,
    this.delai = const Duration(milliseconds: 250),
  });

  final double taille;
  final Duration delai;

  @override
  State<Confettis> createState() => _EtatConfettis();
}

class _EtatConfettis extends State<Confettis>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  );
  bool _demarre = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_demarre || !animationsActives(context)) return;
    _demarre = true;
    Future.delayed(widget.delai, () {
      if (mounted) _c.forward();
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: ExcludeSemantics(
        child: SizedBox(
          width: widget.taille,
          height: widget.taille,
          child: AnimatedBuilder(
            animation: _c,
            builder: (context, _) =>
                CustomPaint(painter: _PeintreConfettis(_c.value)),
          ),
        ),
      ),
    );
  }
}

class _PeintreConfettis extends CustomPainter {
  _PeintreConfettis(this.t);

  final double t;

  static const _couleurs = [
    CouleursSopi.bleu,
    Color(0xFFF59E0B),
    Color(0xFF7FA2F2),
    Color(0xFF16A34A),
  ];

  @override
  void paint(Canvas canvas, Size size) {
    if (t <= 0 || t >= 1) return;
    final alea = math.Random(11);
    final centre = size.center(Offset.zero);
    final portee = size.width / 2;
    for (var i = 0; i < 30; i++) {
      final angle = alea.nextDouble() * 2 * math.pi;
      final vitesse = 0.45 + alea.nextDouble() * 0.55;
      final tour = alea.nextDouble() * 6;
      final couleur = _couleurs[i % _couleurs.length];
      final cote = 4 + alea.nextDouble() * 4;
      final lancer = Curves.easeOutCubic.transform(t);
      final pos =
          centre +
          Offset(
            math.cos(angle) * portee * vitesse * lancer,
            math.sin(angle) * portee * vitesse * lancer + portee * 0.5 * t * t,
          );
      canvas
        ..save()
        ..translate(pos.dx, pos.dy)
        ..rotate(tour * t)
        ..drawRRect(
          RRect.fromRectAndRadius(
            Rect.fromCenter(
              center: Offset.zero,
              width: cote,
              height: cote * 1.6,
            ),
            const Radius.circular(1.5),
          ),
          Paint()
            ..color = couleur.withValues(
              alpha: (t < 0.7 ? 1.0 : (1 - t) / 0.3).clamp(0.0, 1.0),
            ),
        )
        ..restore();
    }
  }

  @override
  bool shouldRepaint(covariant _PeintreConfettis ancien) => ancien.t != t;
}

/// Un caractère qui se retourne en apparaissant (code de réservation). Le texte final est toujours exact.
class CaractereRetourne extends StatelessWidget {
  const CaractereRetourne({
    super.key,
    required this.delai,
    required this.child,
  });

  final Duration delai;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (!animationsActives(context)) return child;
    return _Retourne(delai: delai, child: child);
  }
}

class _Retourne extends StatefulWidget {
  const _Retourne({required this.delai, required this.child});

  final Duration delai;
  final Widget child;

  @override
  State<_Retourne> createState() => _EtatRetourne();
}

class _EtatRetourne extends State<_Retourne>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 420),
  );

  @override
  void initState() {
    super.initState();
    Future.delayed(widget.delai, () {
      if (mounted) _c.forward();
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      child: widget.child,
      builder: (context, enfant) {
        final v = Curves.easeOutBack.transform(_c.value);
        return Opacity(
          opacity: (_c.value * 3).clamp(0.0, 1.0),
          child: Transform(
            alignment: Alignment.center,
            transform: Matrix4.identity()
              ..setEntry(3, 2, 0.002)
              ..rotateX((1 - v) * math.pi / 2),
            child: enfant,
          ),
        );
      },
    );
  }
}
