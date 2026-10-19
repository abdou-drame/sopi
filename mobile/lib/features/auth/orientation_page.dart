import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
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
        chevauchement: 32,
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
        bottom: Radius.circular(DimensionsSopi.rayonEnteteConnexion),
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
                    Container(
                      width: 52,
                      height: 52,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: CouleursSopi.bleu,
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Text(
                        'S',
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontSize: 26,
                          fontWeight: FontWeight.w800,
                          color: CouleursSopi.blanc,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      'Sopi',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontSize: 28,
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
                      height: 42 / 36,
                      color: CouleursSopi.marine,
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  "Choisissez comment vous souhaitez utiliser l'application.",
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 16,
                    height: 22 / 16,
                    fontWeight: FontWeight.w400,
                    color: CouleursSopi.texteSecondaire,
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
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.location_on_outlined,
                size: 18,
                color: CouleursSopi.bleu,
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  'Keur Massar',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontSize: 14,
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
        ? CouleursSopi.blanc.withValues(alpha: 0.72)
        : CouleursSopi.texteSecondaire;
    return Semantics(
      button: true,
      label: '$titre. $texte Commencer',
      excludeSemantics: true,
      onTap: onTap,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: fond,
          borderRadius: BorderRadius.circular(
            DimensionsSopi.rayonCarteFormulaire,
          ),
          border: sombre ? null : Border.all(color: CouleursSopi.bordure),
          boxShadow: [
            BoxShadow(
              color: CouleursSopi.marine.withValues(
                alpha: sombre ? 0.22 : 0.08,
              ),
              blurRadius: 24,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            borderRadius: BorderRadius.circular(
              DimensionsSopi.rayonCarteFormulaire,
            ),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ExcludeSemantics(child: illustration),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(6, 14, 6, 0),
                    child: Text(
                      titre,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 20,
                        height: 22 / 20,
                        fontWeight: FontWeight.w800,
                        color: couleurTitre,
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(6, 6, 6, 0),
                    child: Text(
                      texte,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 14,
                        height: 18 / 14,
                        fontWeight: FontWeight.w400,
                        color: couleurTexte,
                      ),
                    ),
                  ),
                  const Spacer(),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(6, 16, 0, 4),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Commencer',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: sombre
                                  ? CouleursSopi.blanc
                                  : CouleursSopi.bleu,
                            ),
                          ),
                        ),
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: sombre
                                ? CouleursSopi.blanc
                                : CouleursSopi.bleu,
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            Icons.arrow_forward_rounded,
                            size: 22,
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

/// Zone d'illustration : proportions fixes, dessin mis à l'échelle de la largeur de la carte.
class _Illustration extends StatelessWidget {
  const _Illustration({required this.peintre});

  final CustomPainter peintre;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(24),
      child: AspectRatio(
        aspectRatio: _PeintureBase.largeur / _PeintureBase.hauteur,
        child: CustomPaint(painter: peintre),
      ),
    );
  }
}

class _PeintureClient extends StatelessWidget {
  const _PeintureClient();

  @override
  Widget build(BuildContext context) =>
      _Illustration(peintre: _CalendrierEpingle());
}

class _PeintureArtisan extends StatelessWidget {
  const _PeintureArtisan();

  @override
  Widget build(BuildContext context) => _Illustration(peintre: _CleEngrenage());
}

/// Les dessins sont tracés dans un repère de 151 × 176 puis mis à l'échelle.
abstract class _PeintureBase extends CustomPainter {
  static const largeur = 151.0;
  static const hauteur = 176.0;

  void dessiner(Canvas canvas);

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / largeur, size.height / hauteur);
    dessiner(canvas);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _CalendrierEpingle extends _PeintureBase {
  @override
  void dessiner(Canvas canvas) {
    const rect = Rect.fromLTWH(0, 0, 151, 176);
    canvas.drawRect(
      rect,
      Paint()
        ..shader = const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFE6EEFF), Color(0xFFDCE8FF)],
        ).createShader(rect),
    );
    canvas.drawCircle(
      const Offset(125, 30),
      52,
      Paint()..color = CouleursSopi.enteteTacheBleue.withValues(alpha: 0.55),
    );
    canvas.drawCircle(
      const Offset(14, 160),
      40,
      Paint()..color = CouleursSopi.enteteTacheIndigo.withValues(alpha: 0.6),
    );

    // Calendrier : corps blanc, bandeau bleu, deux anneaux marine.
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
        ..color = CouleursSopi.bordureForte,
    );
    canvas.save();
    canvas.clipRRect(corps);
    canvas.drawRect(
      const Rect.fromLTWH(22, 50, 96, 27),
      Paint()..color = CouleursSopi.bleu,
    );
    canvas.restore();
    final anneau = Paint()..color = CouleursSopi.marine;
    for (final x in const [47.0, 93.0]) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, 38, 5, 20),
          const Radius.circular(3),
        ),
        anneau,
      );
    }
    final cellule = Paint()..color = const Color(0xFFDBE7FF);
    const cases = [
      Rect.fromLTWH(34, 88, 18, 14),
      Rect.fromLTWH(60, 88, 18, 14),
      Rect.fromLTWH(34, 110, 18, 14),
      Rect.fromLTWH(60, 110, 18, 14),
    ];
    for (final c in cases) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(c, const Radius.circular(5)),
        cellule,
      );
    }
    const choisie = Rect.fromLTWH(87, 88, 18, 14);
    canvas.drawRRect(
      RRect.fromRectAndRadius(choisie, const Radius.circular(5)),
      Paint()..color = CouleursSopi.bleu,
    );
    canvas.drawPath(
      Path()
        ..moveTo(91.5, 95)
        ..lineTo(94.8, 98.2)
        ..lineTo(100.5, 91.6),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..color = CouleursSopi.blanc,
    );

    // Épingle orange : disque et pointe, point blanc au centre.
    const centre = Offset(114, 36);
    const r = 22.0;
    final epingle = Path()
      ..addOval(Rect.fromCircle(center: centre, radius: r))
      ..moveTo(centre.dx - r * 0.9, centre.dy + r * 0.42)
      ..quadraticBezierTo(
        centre.dx - r * 0.35,
        centre.dy + r * 1.05,
        centre.dx,
        centre.dy + r * 1.7,
      )
      ..quadraticBezierTo(
        centre.dx + r * 0.35,
        centre.dy + r * 1.05,
        centre.dx + r * 0.9,
        centre.dy + r * 0.42,
      )
      ..close();
    canvas.drawPath(epingle, Paint()..color = _orange);
    canvas.drawCircle(centre, 9, Paint()..color = CouleursSopi.blanc);
  }
}

class _CleEngrenage extends _PeintureBase {
  @override
  void dessiner(Canvas canvas) {
    const rect = Rect.fromLTWH(0, 0, 151, 176);
    canvas.drawRect(
      rect,
      Paint()
        ..shader = const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF2252D6), Color(0xFF1D4ED8), Color(0xFF1E3FAE)],
        ).createShader(rect),
    );
    canvas.drawCircle(
      const Offset(38, 28),
      76,
      Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.1),
    );
    canvas.drawCircle(
      const Offset(125, 150),
      58,
      Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.07),
    );

    // Engrenage : disque pâle et douze dents arrondies.
    const centre = Offset(76, 85);
    canvas.drawCircle(
      centre,
      31,
      Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.12),
    );
    final dent = Paint()..color = CouleursSopi.blanc.withValues(alpha: 0.35);
    for (var i = 0; i < 12; i++) {
      canvas.save();
      canvas.translate(centre.dx, centre.dy);
      canvas.rotate(i * math.pi / 6);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          const Rect.fromLTWH(-4.5, -46, 9, 12),
          const Radius.circular(3),
        ),
        dent,
      );
      canvas.restore();
    }

    // Clé à molette : contour blanc.
    final cle = TextPainter(
      text: TextSpan(
        text: String.fromCharCode(Icons.build_outlined.codePoint),
        style: TextStyle(
          fontFamily: Icons.build_outlined.fontFamily,
          package: Icons.build_outlined.fontPackage,
          fontSize: 74,
          color: CouleursSopi.blanc,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    cle.paint(canvas, centre - Offset(cle.width / 2, cle.height / 2));

    // Pastille de validation orange.
    const pastille = Offset(113, 137);
    canvas.drawCircle(pastille, 20, Paint()..color = _orange);
    canvas.drawPath(
      Path()
        ..moveTo(pastille.dx - 8, pastille.dy)
        ..lineTo(pastille.dx - 2, pastille.dy + 6)
        ..lineTo(pastille.dx + 9, pastille.dy - 6),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.5
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..color = CouleursSopi.blanc,
    );
  }
}
