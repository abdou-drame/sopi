import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/sopi_logo.dart';
import '../../core/widgets/en_tete_degrade.dart';
import 'widgets/mise_en_page_acces.dart';

/// Orange des illustrations (épingle, pastille de validation).
const _orange = Color(0xFFF59E0B);

/// Écran d'orientation : premier écran sans session. Le client et l'artisan choisissent leur parcours.
class OrientationPage extends StatelessWidget {
  const OrientationPage({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: EcranAcces(
        chevauchement: 34,
        margePied: 28,
        entete: const _EnteteOrientation(),
        corps: [
          // Entrée discrète : 100 ms de délai + 300 ms, soit 400 ms au plus.
          Apparition(
            delai: const Duration(milliseconds: 100),
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: DimensionsSopi.margeCarte,
              ),
              child: IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: _CarteChoix(
                        titre: 'Je cherche un artisan',
                        texte:
                            'Mécanique, coiffure, pressing près de chez vous.',
                        sombre: false,
                        illustration: const _PeintureClient(),
                        onTap: () => context.push(Chemins.inscription),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: _CarteChoix(
                        titre: 'Je suis artisan',
                        texte: 'Recevez des clients et gérez vos réservations.',
                        sombre: true,
                        illustration: const _PeintureArtisan(),
                        onTap: () =>
                            context.push(Chemins.inscriptionPrestataire),
                      ),
                    ),
                  ],
                ),
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
    );
  }
}

/// En-tête : même dégradé que la connexion, logo, pastille « Keur Massar », titre et sous-titre.
class _EnteteOrientation extends StatelessWidget {
  const _EnteteOrientation();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    return ClipRRect(
      borderRadius: const BorderRadius.vertical(
        bottom: Radius.circular(DimensionsSopi.rayonEnteteRestreint),
      ),
      child: FondDegrade(
        tacheBasse: true,
        child: Apparition(
          child: Padding(
            padding: EdgeInsets.fromLTRB(28, haut + 28, 28, 60),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
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
                const SizedBox(height: 34),
                Semantics(
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
                      color: CouleursSopi.marine,
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Padding(
                  padding: const EdgeInsets.only(right: 32),
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
              ],
            ),
          ),
        ),
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
          color: CouleursSopi.blanc.withValues(alpha: 0.85),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: CouleursSopi.bordure),
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
    required this.sombre,
    required this.illustration,
    required this.onTap,
  });

  final String titre;
  final String texte;
  final bool sombre;
  final Widget illustration;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final fond = sombre ? CouleursSopi.marine : CouleursSopi.blanc;
    final couleurTitre = sombre ? CouleursSopi.blanc : CouleursSopi.marine;
    final couleurTexte = sombre
        ? const Color(0xFFC9D6F5)
        : CouleursSopi.texteSecondaire;
    return Semantics(
      button: true,
      label: '$titre. $texte Commencer',
      excludeSemantics: true,
      onTap: onTap,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: fond,
          borderRadius: BorderRadius.circular(32.0),
          border: sombre ? null : Border.all(color: CouleursSopi.bordure),
          boxShadow: [
            BoxShadow(
              color: CouleursSopi.marine.withValues(
                alpha: sombre ? 0.30 : 0.10,
              ),
              blurRadius: 36,
              offset: const Offset(0, 18),
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
                        color: couleurTitre,
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
                    padding: const EdgeInsets.fromLTRB(8, 12, 8, 6),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Commencer',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: sombre
                                  ? CouleursSopi.blanc
                                  : CouleursSopi.bleu,
                            ),
                          ),
                        ),
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: sombre
                                ? CouleursSopi.blanc
                                : CouleursSopi.bleu,
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            Icons.arrow_forward_rounded,
                            size: 18,
                            color: sombre
                                ? CouleursSopi.marine
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

class _PeintureClient extends StatelessWidget {
  const _PeintureClient();

  @override
  Widget build(BuildContext context) =>
      const _Illustration(peintre: _CalendrierEpingle());
}

class _PeintureArtisan extends StatelessWidget {
  const _PeintureArtisan();

  @override
  Widget build(BuildContext context) =>
      const _Illustration(peintre: _CleEngrenage());
}

abstract class _Peinture extends CustomPainter {
  const _Peinture();

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
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _CalendrierEpingle extends _Peinture {
  const _CalendrierEpingle();

  @override
  Shader fond(Rect zone) => const LinearGradient(
    begin: Alignment(-0.34, -1),
    end: Alignment(0.34, 1),
    colors: [CouleursSopi.bleuPastel, CouleursSopi.enteteFond],
  ).createShader(zone);

  @override
  void dessiner(Canvas canvas) {
    canvas.drawCircle(
      const Offset(118, 40),
      44,
      Paint()..color = CouleursSopi.enteteTacheBleue.withValues(alpha: 0.7),
    );
    canvas.drawCircle(
      const Offset(22, 150),
      34,
      Paint()..color = CouleursSopi.enteteTacheIndigo.withValues(alpha: 0.6),
    );

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
    final anneau = Paint()..color = CouleursSopi.marine;
    for (final x in const [44.0, 90.0]) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, 42, 6, 16),
          const Radius.circular(3),
        ),
        anneau,
      );
    }
    final cellule = Paint()..color = CouleursSopi.bleuPastel;
    for (final c in const [
      Rect.fromLTWH(34, 88, 18, 14),
      Rect.fromLTWH(60, 88, 18, 14),
      Rect.fromLTWH(34, 110, 18, 14),
      Rect.fromLTWH(60, 110, 18, 14),
    ]) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(c, const Radius.circular(5)),
        cellule,
      );
    }
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(86, 88, 18, 14),
        const Radius.circular(5),
      ),
      Paint()..color = CouleursSopi.bleu,
    );
    canvas.drawPath(
      Path()
        ..moveTo(91, 95)
        ..relativeLineTo(3, 3)
        ..relativeLineTo(6, -6),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.4
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..color = CouleursSopi.blanc,
    );

    // Épingle orange (translate 92, 14).
    canvas.save();
    canvas.translate(92, 14);
    canvas.drawPath(
      Path()
        ..moveTo(22, 0)
        ..cubicTo(10, 0, 0, 10, 0, 22)
        ..relativeCubicTo(0, 16, 22, 38, 22, 38)
        ..relativeCubicTo(0, 0, 22, -22, 22, -38)
        ..cubicTo(44, 10, 34, 0, 22, 0)
        ..close(),
      Paint()..color = _orange,
    );
    canvas.drawCircle(
      const Offset(22, 22),
      9,
      Paint()..color = CouleursSopi.blanc,
    );
    canvas.restore();
  }
}

class _CleEngrenage extends _Peinture {
  const _CleEngrenage();

  @override
  Shader fond(Rect zone) => const LinearGradient(
    begin: Alignment(-0.34, -1),
    end: Alignment(0.34, 1),
    colors: [Color(0xFF2F5BE0), CouleursSopi.bleu, Color(0xFF17338F)],
    stops: [0, 0.45, 1],
  ).createShader(zone);

  @override
  void dessiner(Canvas canvas) {
    final voile = Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.08);
    canvas.drawCircle(const Offset(30, 30), 46, voile);
    canvas.drawCircle(const Offset(128, 150), 40, voile);

    // Couronne de dents : cercle de rayon 40, trait de 12, tirets de 9,4 séparés de 9,4.
    const centre = Offset(75, 84);
    const rayon = 40.0;
    final dent = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 12
      ..color = CouleursSopi.blanc.withValues(alpha: 0.28);
    const tiret = 9.4;
    final perimetre = 2 * math.pi * rayon;
    for (var debut = 0.0; debut < perimetre; debut += 2 * tiret) {
      canvas.drawArc(
        Rect.fromCircle(center: centre, radius: rayon),
        debut / rayon,
        math.min(tiret, perimetre - debut) / rayon,
        false,
        dent,
      );
    }
    canvas.drawCircle(
      centre,
      30,
      Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.16),
    );

    // Clé à molette (tracé 24 × 24, translate 34, 43, échelle 3,4).
    canvas.save();
    canvas.translate(34, 43);
    canvas.scale(3.4);
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
        ..color = CouleursSopi.blanc,
    );
    canvas.restore();

    canvas.drawCircle(const Offset(112, 138), 20, Paint()..color = _orange);
    canvas.drawPath(
      Path()
        ..moveTo(103, 138)
        ..relativeLineTo(7, 7)
        ..relativeLineTo(12, -14),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 4
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..color = CouleursSopi.blanc,
    );
  }
}
