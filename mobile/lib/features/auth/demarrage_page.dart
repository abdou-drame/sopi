import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/animation/apparition.dart';
import '../../core/theme/couleurs.dart';

/// Vrai une fois l'écran de démarrage joué en entier : le routeur attend ce signal avant de quitter `/demarrage`.
class DemarrageFini extends Notifier<bool> {
  @override
  bool build() => false;

  void terminer() => state = true;
}

final demarrageFiniProvider = NotifierProvider<DemarrageFini, bool>(
  DemarrageFini.new,
);

/// Écran de démarrage : le nom « Sopi » apparaît en s'écartant, puis sa description, puis tout s'efface vers
/// l'orientation (ou l'accueil si la session est déjà ouverte). Sans animation (accessibilité), il passe tout de suite.
class DemarragePage extends ConsumerStatefulWidget {
  const DemarragePage({super.key});

  @override
  ConsumerState<DemarragePage> createState() => _DemarragePageState();
}

/// Ne laisse voir que la partie du nom déjà traversée par le trait de lumière.
class _Revele extends CustomClipper<Rect> {
  _Revele(this.p);
  final double p;

  @override
  Rect getClip(Size size) =>
      Rect.fromLTWH(-20, -20, (size.width + 40) * p, size.height + 40);

  @override
  bool shouldReclip(_Revele ancien) => ancien.p != p;
}

/// Le trait lumineux : une fine ligne verticale blanche avec un halo, qui s'éteint en fin de course.
class _Lueur extends CustomPainter {
  _Lueur(this.p);
  final double p;

  @override
  void paint(Canvas canvas, Size size) {
    if (p <= 0 || p >= 1) return;
    final x = size.width * p;
    final intensite = p > 0.85 ? (1 - p) / 0.15 : 1.0;
    canvas.drawCircle(
      Offset(x, size.height / 2),
      22,
      Paint()
        ..color = CouleursSopi.blanc.withValues(alpha: .35 * intensite)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 14),
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromCenter(
          center: Offset(x, size.height / 2),
          width: 2.5,
          height: size.height * 0.9,
        ),
        const Radius.circular(2),
      ),
      Paint()..color = CouleursSopi.blanc.withValues(alpha: intensite),
    );
  }

  @override
  bool shouldRepaint(_Lueur ancien) => ancien.p != p;
}

class _DemarragePageState extends ConsumerState<DemarragePage>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 3200),
  );
  bool _lance = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_lance) return;
    _lance = true;
    if (animationsActives(context)) {
      _c.addStatusListener((s) {
        if (s == AnimationStatus.completed) {
          ref.read(demarrageFiniProvider.notifier).terminer();
        }
      });
      _c.forward();
    } else {
      _c.value = 1;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) ref.read(demarrageFiniProvider.notifier).terminer();
      });
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final fondFinal = theme.scaffoldBackgroundColor;
    return Semantics(
      label: 'Sopi, vos services de proximité',
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, _) {
          final t = _c.value;
          // Animation finie mais session pas encore connue (réseau lent) : un simple indicateur, jamais un écran vide.
          if (t >= 1) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }
          double seg(double a, double b, [Curve c = Curves.easeOutCubic]) =>
              c.transform(((t - a) / (b - a)).clamp(0.0, 1.0));
          // Un trait de lumière traverse le nom de gauche à droite et révèle les lettres sur son passage.
          final balayage = seg(0.06, 0.42, Curves.easeInOut);
          final description = seg(0.5, 0.68);
          final sortie = seg(0.88, 1, Curves.easeInOut);
          return Scaffold(
            backgroundColor: Color.lerp(CouleursSopi.bleu, fondFinal, sortie),
            body: Center(
              child: Opacity(
                opacity: 1 - sortie,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Stack(
                      children: [
                        ClipRect(
                          clipper: _Revele(balayage),
                          child: Text(
                            'SOPI',
                            style: theme.textTheme.displaySmall?.copyWith(
                              color: CouleursSopi.blanc,
                              fontSize: 44,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 10,
                            ),
                          ),
                        ),
                        Positioned.fill(
                          child: CustomPaint(painter: _Lueur(balayage)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Opacity(
                      opacity: description,
                      child: Transform.translate(
                        offset: Offset(0, 10 * (1 - description)),
                        child: Text(
                          'Vos services de proximité, à Keur Massar',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: CouleursSopi.blanc.withValues(alpha: .85),
                            fontSize: 14,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
