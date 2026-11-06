import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/theme/disposition.dart';
import '../../core/widgets/sopi_logo.dart';
import 'widgets/apercu_paiement.dart';
import 'widgets/fond_blocs_anime.dart';
import 'widgets/mise_en_page_acces.dart';

/// Orange des illustrations (épingle, pastille de validation).
const _orange = Color(0xFFF59E0B);

/// Teintes propres aux deux illustrations de la maquette Orientation.
const _bleuClair = Color(0xFFBFDBFE);
const _indigoClair = Color(0xFFC7D2FE);
const _fondClair = Color(0xFFE8EEFC);
const _texteArtisan = Color(0xFFC9D6F5);

/// Écran d'orientation : premier écran sans session. Le client et l'artisan choisissent leur parcours.
class OrientationPage extends StatefulWidget {
  const OrientationPage({super.key});

  @override
  State<OrientationPage> createState() => _OrientationPageState();
}

class _OrientationPageState extends State<OrientationPage>
    with TickerProviderStateMixin {
  /// Entrée unique : blocs qui se lèvent, calendrier, épingle, coche (2,6 s).
  late final AnimationController _entree = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2600),
  );

  /// Boucle de 8 s : respiration du fond, anneau, métiers qui changent, scène de paiement.
  late final AnimationController _boucle = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 8),
  );

  bool _initialise = false;
  bool _anime = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialise) return;
    _initialise = true;
    _anime = animationsActives(context);
    if (_anime) {
      _entree.forward();
      // Trois tours, puis la scène se fige sur son état final (acompte payé, portefeuille crédité) :
      // rien ne tourne indéfiniment.
      _boucle.repeat(count: 3).whenComplete(() {
        if (!mounted) return;
        _boucle
          ..value = 0
          ..animateTo(
            0.85,
            duration: const Duration(milliseconds: 6800),
            curve: Curves.linear,
          );
      });
    } else {
      // Animations désactivées : état final, rien ne bouge.
      _entree.value = 1;
    }
  }

  @override
  void dispose() {
    _entree.dispose();
    _boucle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: IgnorePointer(
              child: ExcludeSemantics(
                child: RepaintBoundary(
                  child: FondBlocsAnime(progression: _entree, boucle: _boucle),
                ),
              ),
            ),
          ),
          EcranAcces(
            margePied: 20,
            entete: _EnteteOrientation(animer: _anime),
            corps: [
              // Entrée discrète : les deux cartes « sortent » l'une après l'autre (100 ms de délai au plus).
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: DimensionsSopi.margeCarte,
                ),
                child: IntrinsicHeight(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(
                        child: Apparition(
                          delai: const Duration(milliseconds: 40),
                          child: _CarteChoix(
                            titre: 'Je cherche un artisan',
                            texte:
                                'Mécanique, coiffure, pressing près de chez vous.',
                            artisan: false,
                            illustration: _Illustration(
                              peintre: _CalendrierEpingle(
                                entree: _entree,
                                boucle: _boucle,
                              ),
                            ),
                            apercu: ApercuPaiementClient(
                              boucle: _anime ? _boucle : null,
                            ),
                            onTap: () => context.push(Chemins.inscription),
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Apparition(
                          delai: const Duration(milliseconds: 100),
                          child: _CarteChoix(
                            titre: 'Je suis artisan',
                            texte:
                                'Recevez des clients et gérez vos réservations.',
                            artisan: true,
                            illustration: _Illustration(
                              peintre: _CleEngrenage(
                                entree: _entree,
                                boucle: _boucle,
                                anime: _anime,
                              ),
                            ),
                            apercu: ApercuCreditArtisan(
                              boucle: _anime ? _boucle : null,
                            ),
                            onTap: () =>
                                context.push(Chemins.inscriptionPrestataire),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            pied: Apparition(
              delai: const Duration(milliseconds: 100),
              child: Wrap(
                alignment: WrapAlignment.center,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 6,
                children: [
                  Text(
                    'Vous avez déjà un compte ?',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w400,
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                  LienTexte(
                    texte: 'Se connecter',
                    onTap: () => context.push(Chemins.connexion),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// En-tête sur fond uni : logo, pastille « Keur Massar », titre et sous-titre.
class _EnteteOrientation extends StatelessWidget {
  const _EnteteOrientation({required this.animer});

  /// Faux quand les animations sont désactivées : le trait d'accent est tracé tout de suite.
  final bool animer;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = hautEcran(context) - hautMaquette;
    return Padding(
      padding: EdgeInsets.fromLTRB(28, haut + 52, 24, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Apparition(
            child: Row(
              children: [
                const SopiLogo(taille: 48, ombre: true),
                const SizedBox(width: 12),
                Text(
                  'Sopi',
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(width: 12),
                const Expanded(child: _PastilleQuartier()),
              ],
            ),
          ),
          const SizedBox(height: 28),
          Apparition(
            delai: const Duration(milliseconds: 50),
            decalage: const Offset(0, 24),
            child: Semantics(
              header: true,
              child: Text.rich(
                const TextSpan(
                  children: [
                    TextSpan(
                      text: 'Bienvenue\nsur ',
                      style: TextStyle(fontWeight: FontWeight.w400),
                    ),
                    TextSpan(
                      text: 'Sopi',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
                style: theme.textTheme.headlineMedium!.copyWith(
                  fontSize: 36,
                  letterSpacing: -0.6,
                  height: 42 / 36,
                  color: CouleursSopi.texte,
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          // Trait d'accent qui se trace sous le titre, comme dans la vidéo de référence.
          TweenAnimationBuilder<double>(
            tween: Tween(begin: animer ? 0 : 1, end: 1),
            duration: animer
                ? const Duration(milliseconds: 700)
                : Duration.zero,
            curve: Curves.easeOutCubic,
            builder: (context, t, _) => Container(
              width: 56 * t,
              height: 4,
              decoration: BoxDecoration(
                color: _orange,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 8),
          Apparition(
            delai: const Duration(milliseconds: 100),
            child: Padding(
              padding: const EdgeInsets.only(right: 36),
              child: Text(
                "Choisissez comment vous souhaitez utiliser l'application.",
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 15,
                  height: 22 / 15,
                  fontWeight: FontWeight.w400,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Pastille « Keur Massar » alignée à droite du logo.
class _PastilleQuartier extends StatelessWidget {
  const _PastilleQuartier();

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerRight,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: CouleursSopi.fondSecondaire,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(10, 8, 14, 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.location_on_outlined,
                size: 16,
                color: CouleursSopi.bleu,
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  'Keur Massar',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: CouleursSopi.bleu,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Carte de choix : illustration décorative, titre, texte, puis « Commencer » et sa flèche. Toute la carte est le bouton.
class _CarteChoix extends StatelessWidget {
  const _CarteChoix({
    required this.titre,
    required this.texte,
    required this.artisan,
    required this.illustration,
    required this.apercu,
    required this.onTap,
  });

  final String titre;
  final String texte;
  final bool artisan;
  final Widget illustration;

  /// Petite scène décorative (paiement de l'acompte / crédit du portefeuille), sous le texte.
  final Widget apercu;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final couleurTexte = artisan ? _texteArtisan : CouleursSopi.texteSecondaire;
    return Semantics(
      button: true,
      label: '$titre. $texte Commencer',
      excludeSemantics: true,
      onTap: onTap,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(32.0),
          boxShadow: artisan
              ? OmbresSopi.carte
              : const [
                  BoxShadow(
                    color: Color(0x1A0F1B3D),
                    blurRadius: 36,
                    offset: Offset(0, 18),
                  ),
                ],
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            borderRadius: BorderRadius.circular(32.0),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ExcludeSemantics(child: illustration),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 14, 8, 0),
                    child: Text(
                      titre,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 18,
                        height: 22 / 18,
                        fontWeight: FontWeight.w800,
                        color: CouleursSopi.texte,
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 6, 8, 0),
                    child: Text(
                      texte,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 13,
                        height: 18 / 14,
                        fontWeight: FontWeight.w400,
                        color: couleurTexte,
                      ),
                    ),
                  ),
                  const Spacer(),
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: ExcludeSemantics(child: apercu),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 12, 8, 6),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Commencer',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: artisan
                                  ? CouleursSopi.blanc
                                  : CouleursSopi.bleu,
                            ),
                          ),
                        ),
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: artisan
                                ? CouleursSopi.blanc
                                : CouleursSopi.bleu,
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            Icons.arrow_forward_rounded,
                            size: 18,
                            color: artisan
                                ? CouleursSopi.texte
                                : CouleursSopi.blanc,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Zone d'illustration : 176 px de haut, pleine largeur de la carte ; le dessin (repère 150 × 176 de la maquette)
/// est centré et mis à l'échelle, le fond remplit toute la zone.
class _Illustration extends StatelessWidget {
  const _Illustration({required this.peintre});

  final _Peinture peintre;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(24),
      child: SizedBox(
        height: 176,
        child: CustomPaint(painter: peintre, size: Size.infinite),
      ),
    );
  }
}

/// Avancement 0 → 1 de [t] entre [debut] et [fin], avec la courbe [c].
double _iv(
  double t,
  double debut,
  double fin, [
  Curve c = Curves.easeOutCubic,
]) {
  if (t <= debut) return 0;
  if (t >= fin) return 1;
  return c.transform((t - debut) / (fin - debut));
}

/// Tracé progressif d'un chemin : de 0 (rien) à 1 (tout).
Path _tracer(Path chemin, double t) {
  final sortie = Path();
  for (final m in chemin.computeMetrics()) {
    sortie.addPath(m.extractPath(0, m.length * t), Offset.zero);
  }
  return sortie;
}

abstract class _Peinture extends CustomPainter {
  _Peinture({required this.entree, required this.boucle})
    : super(repaint: Listenable.merge([entree, boucle]));

  /// 0 → 1, une seule fois à l'ouverture.
  final Animation<double> entree;

  /// 0 → 1 en boucle (8 s).
  final Animation<double> boucle;

  static const largeur = 150.0;
  static const hauteur = 176.0;

  /// Fond de toute la zone.
  Shader fond(Rect zone);

  /// Dessin dans le repère 150 × 176 (équivalent du viewBox SVG de la maquette).
  void dessiner(Canvas canvas);

  @override
  void paint(Canvas canvas, Size size) {
    final zone = Offset.zero & size;
    canvas.clipRect(zone);
    canvas.drawRect(zone, Paint()..shader = fond(zone));
    final echelle = math.min(size.width / largeur, size.height / hauteur);
    canvas.translate(
      (size.width - largeur * echelle) / 2,
      (size.height - hauteur * echelle) / 2,
    );
    canvas.scale(echelle);
    dessiner(canvas);
  }

  @override
  bool shouldRepaint(covariant _Peinture ancien) =>
      ancien.entree != entree || ancien.boucle != boucle;
}

class _CalendrierEpingle extends _Peinture {
  _CalendrierEpingle({required super.entree, required super.boucle});

  @override
  Shader fond(Rect zone) => const LinearGradient(
    begin: Alignment(-0.34, -1),
    end: Alignment(0.34, 1),
    colors: [CouleursSopi.bleuPastel, _fondClair],
  ).createShader(zone);

  @override
  void dessiner(Canvas canvas) {
    final e = entree.value;
    final b = boucle.value;

    final halo = _iv(e, 0, 0.25);
    canvas.drawCircle(
      const Offset(118, 40),
      44 * halo,
      Paint()..color = _bleuClair.withValues(alpha: 0.7),
    );
    canvas.drawCircle(
      const Offset(22, 150),
      34 * halo,
      Paint()..color = _indigoClair.withValues(alpha: 0.6),
    );

    // Le calendrier monte en rebondissant légèrement et apparaît en fondu.
    final monte = _iv(e, 0, 0.32, Curves.easeOutBack);
    final visible = _iv(e, 0, 0.18, Curves.easeOut);
    canvas.saveLayer(
      const Rect.fromLTWH(-20, -20, 200, 240),
      Paint()..color = Color.fromRGBO(255, 255, 255, visible),
    );
    canvas.translate(0, (1 - monte) * 40);
    _calendrier(canvas, e);
    canvas.restore();

    // L'épingle tombe, rebondit, puis flotte doucement.
    final chute = _iv(e, 0.24, 0.56, Curves.bounceOut);
    final flotte = e >= 1 ? math.sin(b * 8 * math.pi) * 2 : 0.0;
    final epingle = _iv(e, 0.24, 0.3, Curves.easeOut);
    if (epingle > 0) {
      canvas.save();
      canvas.translate(92, 14 - (1 - chute) * 70 + flotte);
      canvas.drawPath(
        Path()
          ..moveTo(22, 0)
          ..cubicTo(10, 0, 0, 10, 0, 22)
          ..relativeCubicTo(0, 16, 22, 38, 22, 38)
          ..relativeCubicTo(0, 0, 22, -22, 22, -38)
          ..cubicTo(44, 10, 34, 0, 22, 0)
          ..close(),
        Paint()..color = _orange.withValues(alpha: epingle),
      );
      canvas.drawCircle(
        const Offset(22, 22),
        9,
        Paint()..color = CouleursSopi.blanc.withValues(alpha: epingle),
      );
      canvas.restore();
    }
  }

  void _calendrier(Canvas canvas, double e) {
    final corps = RRect.fromRectAndRadius(
      const Rect.fromLTWH(22, 50, 96, 92),
      const Radius.circular(16),
    );
    canvas.drawRRect(corps, Paint()..color = CouleursSopi.blanc);
    canvas.drawRRect(
      corps,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1
        ..color = const Color(0xFFC7D6F5),
    );
    canvas.drawPath(
      Path()
        ..moveTo(22, 66)
        ..arcToPoint(const Offset(38, 50), radius: const Radius.circular(16))
        ..lineTo(102, 50)
        ..arcToPoint(const Offset(118, 66), radius: const Radius.circular(16))
        ..lineTo(118, 76)
        ..lineTo(22, 76)
        ..close(),
      Paint()..color = CouleursSopi.bleu,
    );
    final anneau = Paint()..color = CouleursSopi.texte;
    for (final x in const [44.0, 90.0]) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, 42, 6, 16),
          const Radius.circular(3),
        ),
        anneau,
      );
    }

    // Les cases apparaissent l'une après l'autre.
    const cases = [
      Rect.fromLTWH(34, 88, 18, 14),
      Rect.fromLTWH(60, 88, 18, 14),
      Rect.fromLTWH(34, 110, 18, 14),
      Rect.fromLTWH(60, 110, 18, 14),
    ];
    for (var i = 0; i < cases.length; i++) {
      final p = _iv(e, 0.22 + i * 0.05, 0.38 + i * 0.05, Curves.easeOutBack);
      if (p <= 0) continue;
      canvas.save();
      canvas.translate(cases[i].center.dx, cases[i].center.dy);
      canvas.scale(p);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          cases[i].shift(-cases[i].center),
          const Radius.circular(5),
        ),
        Paint()..color = CouleursSopi.bleuPastel,
      );
      canvas.restore();
    }

    // La case choisie « pop », puis la coche se trace.
    const choisie = Rect.fromLTWH(86, 88, 18, 14);
    final pop = _iv(e, 0.5, 0.68, Curves.elasticOut);
    if (pop > 0) {
      canvas.save();
      canvas.translate(choisie.center.dx, choisie.center.dy);
      canvas.scale(pop);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          choisie.shift(-choisie.center),
          const Radius.circular(5),
        ),
        Paint()..color = CouleursSopi.bleu,
      );
      canvas.restore();
    }
    final coche = _iv(e, 0.64, 0.82, Curves.easeInOut);
    if (coche > 0) {
      canvas.drawPath(
        _tracer(
          Path()
            ..moveTo(91, 95)
            ..relativeLineTo(3, 3)
            ..relativeLineTo(6, -6),
          coche,
        ),
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2.4
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round
          ..color = CouleursSopi.blanc,
      );
    }
  }
}

class _CleEngrenage extends _Peinture {
  _CleEngrenage({
    required super.entree,
    required super.boucle,
    required this.anime,
  });

  /// Faux quand les animations sont désactivées : la clé reste affichée, sans métier qui change.
  final bool anime;

  /// Métiers de Sopi qui se relaient dans l'anneau : mécanique (la clé tracée), coiffure, pressing, auto.
  static const _icones = [
    null,
    Icons.content_cut,
    Icons.dry_cleaning_outlined,
    Icons.directions_car_outlined,
  ];

  @override
  Shader fond(Rect zone) => const LinearGradient(
    begin: Alignment(-0.34, -1),
    end: Alignment(0.34, 1),
    colors: [Color(0xFF2F5BE0), CouleursSopi.bleu, Color(0xFF17338F)],
    stops: [0, 0.45, 1],
  ).createShader(zone);

  @override
  void dessiner(Canvas canvas) {
    final e = entree.value;
    final b = boucle.value;

    final voile = Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.08);
    final halo = _iv(e, 0, 0.25);
    canvas.drawCircle(const Offset(30, 30), 46 * halo, voile);
    canvas.drawCircle(const Offset(128, 150), 40 * halo, voile);

    // Couronne de dents qui tourne lentement : cercle de rayon 40, trait de 12, tirets de 9,4.
    const centre = Offset(75, 84);
    const rayon = 40.0;
    final apparition = _iv(e, 0.05, 0.4, Curves.easeOutBack);
    canvas.save();
    canvas.translate(centre.dx, centre.dy);
    canvas.rotate(b * 2 * math.pi + (1 - apparition) * -1.2);
    canvas.scale(apparition);
    final dent = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 12
      ..color = CouleursSopi.blanc.withValues(alpha: 0.28);
    const tiret = 9.4;
    final perimetre = 2 * math.pi * rayon;
    for (var debut = 0.0; debut < perimetre; debut += 2 * tiret) {
      canvas.drawArc(
        Rect.fromCircle(center: Offset.zero, radius: rayon),
        debut / rayon,
        math.min(tiret, perimetre - debut) / rayon,
        false,
        dent,
      );
    }
    canvas.restore();
    canvas.drawCircle(
      centre,
      30 * apparition,
      Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.16),
    );

    _metier(canvas, centre, e, b);

    // Pastille de validation : elle « pop », sa coche se trace, puis elle pulse doucement.
    final pop = _iv(e, 0.5, 0.7, Curves.elasticOut);
    if (pop > 0) {
      final pulse = e >= 1 ? 1 + 0.05 * math.sin(b * 16 * math.pi) : 1.0;
      canvas.save();
      canvas.translate(112, 138);
      canvas.scale(pop * pulse);
      canvas.drawCircle(Offset.zero, 20, Paint()..color = _orange);
      canvas.restore();
    }
    final coche = _iv(e, 0.66, 0.86, Curves.easeInOut);
    if (coche > 0) {
      canvas.drawPath(
        _tracer(
          Path()
            ..moveTo(103, 138)
            ..relativeLineTo(7, 7)
            ..relativeLineTo(12, -14),
          coche,
        ),
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 4
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round
          ..color = CouleursSopi.blanc,
      );
    }
  }

  /// Logo du métier au centre de l'anneau : arrive en grossissant, reste, puis s'efface vers le suivant.
  void _metier(Canvas canvas, Offset centre, double e, double b) {
    final entreeMetier = _iv(e, 0.1, 0.4, Curves.easeOutBack);
    var echelle = entreeMetier;
    var alpha = 1.0;
    var indice = 0;
    if (anime) {
      final phase = b * _icones.length;
      indice = phase.floor() % _icones.length;
      final p = phase - phase.floor();
      echelle *= p < 0.25
          ? Curves.easeOutBack.transform(p / 0.25)
          : p > 0.8
          ? 1 - 0.4 * Curves.easeIn.transform((p - 0.8) / 0.2)
          : 1;
      if (p > 0.8) alpha = 1 - Curves.easeIn.transform((p - 0.8) / 0.2);
    }
    if (echelle <= 0) return;

    canvas.save();
    canvas.translate(centre.dx, centre.dy);
    canvas.scale(echelle);
    final icone = _icones[indice];
    if (icone == null) {
      _cle(canvas, alpha);
    } else {
      final texte = TextPainter(
        text: TextSpan(
          text: String.fromCharCode(icone.codePoint),
          style: TextStyle(
            fontFamily: icone.fontFamily,
            package: icone.fontPackage,
            fontSize: 82,
            color: CouleursSopi.blanc.withValues(alpha: alpha),
          ),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      texte.paint(canvas, Offset(-texte.width / 2, -texte.height / 2));
    }
    canvas.restore();
  }

  /// Clé à molette (tracé 24 × 24 mis à l'échelle 3,4), centrée sur l'origine.
  void _cle(Canvas canvas, double alpha) {
    canvas.save();
    canvas.scale(3.4);
    canvas.translate(-12, -12);
    final cle = Path()
      ..moveTo(14.7, 6.3)
      ..relativeArcToPoint(
        const Offset(0, 1.4),
        radius: const Radius.circular(1),
      )
      ..relativeLineTo(1.6, 1.6)
      ..relativeArcToPoint(
        const Offset(1.4, 0),
        radius: const Radius.circular(1),
      )
      ..relativeLineTo(3.77, -3.77)
      ..relativeArcToPoint(
        const Offset(-7.94, 7.94),
        radius: const Radius.circular(6),
        clockwise: true,
      )
      ..relativeLineTo(-6.91, 6.91)
      ..relativeArcToPoint(
        const Offset(-3, -3),
        radius: const Radius.circular(2.12),
        clockwise: true,
      )
      ..relativeLineTo(6.91, -6.91)
      ..relativeArcToPoint(
        const Offset(7.94, -7.94),
        radius: const Radius.circular(6),
        clockwise: true,
      )
      ..relativeLineTo(-3.76, 3.76)
      ..close();
    canvas.drawPath(
      cle,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.7
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..color = CouleursSopi.blanc.withValues(alpha: alpha),
    );
    canvas.restore();
  }
}
