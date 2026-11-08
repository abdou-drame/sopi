import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/effets.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';

/// Accent orange sous le titre.
const _orange = Color(0xFFF59E0B);

/// Fond de la feuille du bas et carte « artisan ».
const _fondFeuille = Color(0xFFF4F6FA);
const _marine = Color(0xFF111628);
const _vert = Color(0xFF16A34A);
const _vertClair = Color(0xFF4ADE80);

/// Chiffre de démonstration : acompte 30 % moins commission 10 % d'un tarif de 15 000 FCFA.
const _creditDemo = 3000;

/// Écran d'orientation : premier écran sans session. Le client et l'artisan choisissent leur parcours.
///
/// Disposition : un grand en-tête bleu (logo, titre, trois métiers) et, par-dessus, une feuille claire qui monte
/// du bas avec les deux choix animés (créneaux côté client, demande et crédit côté artisan), puis la connexion.
class OrientationPage extends StatefulWidget {
  const OrientationPage({super.key});

  @override
  State<OrientationPage> createState() => _OrientationPageState();
}

class _OrientationPageState extends State<OrientationPage>
    with SingleTickerProviderStateMixin {
  /// Boucle de 8 s : disques qui flottent, métier mis en avant, créneau choisi, crédit du portefeuille.
  /// Trois tours, puis tout se fige sur l'état final.
  late final AnimationController _boucle = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 8),
  );

  bool _initialise = false;
  bool _anime = false;

  @override
  void initState() {
    super.initState();
    // Créé tout de suite : un contrôleur créé à la volée dans dispose() échoue (arbre déjà désactivé).
    _boucle;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialise) return;
    _initialise = true;
    _anime = animationsActives(context);
    if (_anime) _boucle.repeat(count: 3);
  }

  @override
  void dispose() {
    _boucle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final boucle = _anime ? _boucle : null;
    return Scaffold(
      backgroundColor: _fondFeuille,
      body: CustomScrollView(
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _EnteteBleu(boucle: boucle),
                Expanded(child: _FeuilleChoix(boucle: boucle)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Haut de l'écran : disques décoratifs, logo, pastille du quartier, titre, sous-titre et trois métiers.
class _EnteteBleu extends StatelessWidget {
  const _EnteteBleu({this.boucle});

  final Animation<double>? boucle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    final styleTitre = theme.textTheme.headlineMedium!.copyWith(
      fontSize: 34,
      fontWeight: FontWeight.w400,
      letterSpacing: -0.8,
      height: 40 / 34,
      color: CouleursSopi.blanc,
    );
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [CouleursSopi.bleu, Color(0xFF1D4ED8)],
        ),
      ),
      child: Stack(
        children: [
          Positioned.fill(
            child: IgnorePointer(
              child: ExcludeSemantics(
                child: RepaintBoundary(child: _Disques(boucle: boucle)),
              ),
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(24, haut + 24, 24, 52),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Apparition(
                  duree: const Duration(milliseconds: 500),
                  decalage: const Offset(0, -18),
                  child: Row(
                    children: [
                      const _LogoBlanc(),
                      const SizedBox(width: 10),
                      Text(
                        'Sopi',
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontSize: 24,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.4,
                          color: CouleursSopi.blanc,
                        ),
                      ),
                      const SizedBox(width: 10),
                      const Expanded(child: _PastilleQuartier()),
                    ],
                  ),
                ),
                const SizedBox(height: 28),
                Semantics(
                  header: true,
                  label: 'Réservez les services de votre quartier',
                  excludeSemantics: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Apparition(
                        delai: const Duration(milliseconds: 130),
                        duree: const Duration(milliseconds: 550),
                        decalage: const Offset(0, 30),
                        child: Text.rich(
                          const TextSpan(
                            children: [
                              TextSpan(text: 'Réservez les '),
                              TextSpan(
                                text: 'services',
                                style: TextStyle(fontWeight: FontWeight.w800),
                              ),
                            ],
                          ),
                          style: styleTitre,
                        ),
                      ),
                      Apparition(
                        delai: const Duration(milliseconds: 260),
                        duree: const Duration(milliseconds: 550),
                        decalage: const Offset(0, 30),
                        child: Text(
                          'de votre quartier',
                          style: styleTitre.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                // Trait d'accent qui se trace sous le titre.
                _TraitAccent(animer: boucle != null),
                const SizedBox(height: 10),
                Apparition(
                  delai: const Duration(milliseconds: 320),
                  child: Text(
                    "Mécanique, coiffure, pressing : trouvez, réservez, payez l'acompte.",
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 15,
                      height: 22 / 15,
                      color: const Color(0xFFDBEAFE),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                _TuilesMetiers(boucle: boucle),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Petit trait orange qui s'étire de 0 à 56 px.
class _TraitAccent extends StatelessWidget {
  const _TraitAccent({required this.animer});

  final bool animer;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: animer ? 0 : 1, end: 1),
      duration: animer ? const Duration(milliseconds: 700) : Duration.zero,
      curve: Curves.easeOutCubic,
      builder: (context, t, _) => Container(
        width: 56 * t,
        height: 4,
        decoration: BoxDecoration(
          color: _orange,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
    );
  }
}

/// Grands disques translucides qui dérivent lentement derrière l'en-tête.
class _Disques extends StatelessWidget {
  const _Disques({this.boucle});

  final Animation<double>? boucle;

  @override
  Widget build(BuildContext context) =>
      CustomPaint(painter: _PeintreDisques(boucle), size: Size.infinite);
}

class _PeintreDisques extends CustomPainter {
  _PeintreDisques(this.boucle) : super(repaint: boucle);

  final Animation<double>? boucle;

  @override
  void paint(Canvas canvas, Size size) {
    final b = boucle?.value ?? 0;
    const disques = [
      (0.95, 0.02, 0.45, 0.10, 0.0),
      (0.0, 0.62, 0.30, 0.08, 0.4),
      (0.70, 0.90, 0.22, 0.06, 0.7),
    ];
    for (final (x, y, r, alpha, phase) in disques) {
      final angle = (b + phase) * 2 * math.pi;
      canvas.drawCircle(
        Offset(
          size.width * x + math.sin(angle) * 16,
          size.height * y + math.cos(angle) * 20,
        ),
        size.width * r,
        Paint()..color = CouleursSopi.blanc.withValues(alpha: alpha),
      );
    }
  }

  @override
  bool shouldRepaint(covariant _PeintreDisques ancien) =>
      ancien.boucle != boucle;
}

/// Pastille « Keur Massar » : le quartier desservi.
class _PastilleQuartier extends StatelessWidget {
  const _PastilleQuartier();

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerRight,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: CouleursSopi.blanc.withValues(alpha: 0.16),
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
                color: CouleursSopi.blanc,
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
                    color: CouleursSopi.blanc,
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

/// Les trois métiers en grandes tuiles (icône au-dessus du nom). Elles apparaissent en rebondissant l'une après
/// l'autre, puis la tuile « allumée » change à chaque temps de la boucle.
class _TuilesMetiers extends StatelessWidget {
  const _TuilesMetiers({this.boucle});

  final Animation<double>? boucle;

  static const _metiers = [
    ('Mécanique', Icons.build_outlined),
    ('Coiffure', Icons.content_cut_rounded),
    ('Pressing', Icons.checkroom_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    final b = boucle;
    if (b == null) return _contenu(-1);
    return AnimatedBuilder(
      animation: b,
      builder: (context, _) => _contenu((b.value * 3).floor() % 3),
    );
  }

  Widget _contenu(int actif) {
    return Row(
      children: [
        for (var i = 0; i < _metiers.length; i++) ...[
          if (i > 0) const SizedBox(width: 10),
          Expanded(
            child: Pop(
              delai: Duration(milliseconds: 380 + 110 * i),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                curve: Curves.easeOut,
                height: 84,
                decoration: BoxDecoration(
                  color: CouleursSopi.blanc.withValues(
                    alpha: i == actif ? 0.30 : 0.14,
                  ),
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    AnimatedScale(
                      scale: i == actif ? 1.15 : 1,
                      duration: const Duration(milliseconds: 300),
                      curve: Curves.easeOutBack,
                      child: Icon(
                        _metiers[i].$2,
                        size: 28,
                        color: CouleursSopi.blanc,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6),
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(
                          _metiers[i].$1,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: CouleursSopi.blanc,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// Feuille claire qui monte du bas : les deux choix puis le lien vers la connexion. Elle chevauche l'en-tête.
class _FeuilleChoix extends StatelessWidget {
  const _FeuilleChoix({this.boucle});

  final Animation<double>? boucle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bas = MediaQuery.paddingOf(context).bottom;
    return Transform.translate(
      offset: const Offset(0, -28),
      child: Apparition(
        delai: const Duration(milliseconds: 150),
        duree: const Duration(milliseconds: 750),
        decalage: const Offset(0, 320),
        child: DecoratedBox(
          decoration: const BoxDecoration(
            color: _fondFeuille,
            borderRadius: BorderRadius.vertical(top: Radius.circular(36)),
          ),
          child: Padding(
            padding: EdgeInsets.fromLTRB(20, 26, 20, 4 + bas),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.only(left: 4, bottom: 16),
                  child: Text(
                    'Vous êtes…',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: CouleursSopi.texte,
                    ),
                  ),
                ),
                Apparition(
                  delai: const Duration(milliseconds: 500),
                  decalage: const Offset(0, 24),
                  child: _OptionProfil(
                    titre: 'Je cherche un artisan',
                    texte: 'Près de chez vous, en quelques secondes.',
                    icone: Icons.search_rounded,
                    artisan: false,
                    boucle: boucle,
                    apercu: _ApercuCreneaux(boucle: boucle),
                    onTap: () => context.push(Chemins.inscription),
                  ),
                ),
                const SizedBox(height: 14),
                Apparition(
                  delai: const Duration(milliseconds: 640),
                  decalage: const Offset(0, 24),
                  child: _OptionProfil(
                    titre: 'Je suis artisan',
                    texte: 'Recevez des clients, gérez vos réservations.',
                    icone: Icons.build_outlined,
                    artisan: true,
                    boucle: boucle,
                    apercu: _ApercuDemande(boucle: boucle),
                    onTap: () => context.push(Chemins.inscriptionPrestataire),
                  ),
                ),
                const SizedBox(height: 20),
                Apparition(
                  delai: const Duration(milliseconds: 800),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Vous avez déjà un compte ?',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 14,
                          color: CouleursSopi.texteSecondaire,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Appui(
                        child: OutlinedButton(
                          onPressed: () => context.push(Chemins.connexion),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size.fromHeight(50),
                            foregroundColor: CouleursSopi.bleu,
                            side: const BorderSide(
                              color: CouleursSopi.bleu,
                              width: 1.5,
                            ),
                            shape: const StadiumBorder(),
                            textStyle: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          child: const Text('Se connecter'),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Avancement 0 → 1 de [t] entre [debut] et [fin].
double _etape(double t, double debut, double fin) {
  if (t <= debut) return 0;
  if (t >= fin) return 1;
  return Curves.easeOut.transform((t - debut) / (fin - debut));
}

/// Fondu sans widget [Opacity] quand l'élément est entièrement visible (animations désactivées ou terminées).
class _Fondu extends StatelessWidget {
  const _Fondu({required this.valeur, required this.child});

  final double valeur;
  final Widget child;

  @override
  Widget build(BuildContext context) =>
      valeur >= 1 ? child : Opacity(opacity: valeur, child: child);
}

/// Un choix de profil : pastille d'icône, titre, texte, flèche, puis une petite scène animée.
/// Toute la carte est le bouton. Le client est en blanc cerclé de bleu, l'artisan en bleu marine.
class _OptionProfil extends StatelessWidget {
  const _OptionProfil({
    required this.titre,
    required this.texte,
    required this.icone,
    required this.artisan,
    required this.apercu,
    required this.onTap,
    this.boucle,
  });

  final String titre;
  final String texte;
  final IconData icone;
  final bool artisan;
  final Widget apercu;
  final VoidCallback onTap;
  final Animation<double>? boucle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      button: true,
      label: '$titre. $texte',
      excludeSemantics: true,
      onTap: onTap,
      child: Appui(
        echelle: 0.98,
        child: Material(
          color: artisan ? _marine : CouleursSopi.blanc,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(28),
            side: artisan
                ? BorderSide.none
                : const BorderSide(color: CouleursSopi.bleu, width: 1.5),
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(28),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Pop(
                        delai: Duration(milliseconds: artisan ? 900 : 780),
                        duree: const Duration(milliseconds: 550),
                        echelleDepart: 0.3,
                        child: Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: artisan
                                ? const Color(0xFF2B3040)
                                : const Color(0xFFDBEAFE),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Icon(
                            icone,
                            size: 24,
                            color: artisan
                                ? CouleursSopi.blanc
                                : CouleursSopi.bleu,
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              titre,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontSize: 19,
                                height: 24 / 19,
                                fontWeight: FontWeight.w800,
                                color: artisan
                                    ? CouleursSopi.blanc
                                    : CouleursSopi.texte,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              texte,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                fontSize: 14,
                                height: 20 / 14,
                                color: artisan
                                    ? const Color(0xFFC9D0E0)
                                    : CouleursSopi.texteSecondaire,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: artisan
                              ? CouleursSopi.blanc
                              : CouleursSopi.bleu,
                          shape: BoxShape.circle,
                        ),
                        child: _FlecheQuiPousse(
                          boucle: boucle,
                          decalagePhase: artisan ? 0.25 : 0,
                          couleur: artisan ? _marine : CouleursSopi.blanc,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  ExcludeSemantics(child: apercu),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Flèche des choix : elle glisse un peu vers la droite, par à-coups, pour inviter à toucher.
class _FlecheQuiPousse extends StatelessWidget {
  const _FlecheQuiPousse({
    this.boucle,
    required this.decalagePhase,
    required this.couleur,
  });

  final Animation<double>? boucle;
  final double decalagePhase;
  final Color couleur;

  @override
  Widget build(BuildContext context) {
    final fleche = Center(
      child: Icon(Icons.arrow_forward_rounded, size: 22, color: couleur),
    );
    final b = boucle;
    if (b == null) return fleche;
    return AnimatedBuilder(
      animation: b,
      builder: (context, enfant) {
        // Six petits va-et-vient par tour, décalés d'un choix à l'autre.
        final v = math.max(
          0.0,
          math.sin((b.value * 6 + decalagePhase) * 2 * math.pi),
        );
        return Transform.translate(offset: Offset(4 * v, 0), child: enfant);
      },
      child: fleche,
    );
  }
}

/// Aperçu client : trois créneaux, celui qui est choisi change (09:00, 10:30, 14:00), et l'acompte de 30 %.
/// Sans animation : 10:30 choisi.
class _ApercuCreneaux extends StatelessWidget {
  const _ApercuCreneaux({this.boucle});

  final Animation<double>? boucle;

  static const _heures = ['09:00', '10:30', '14:00'];

  @override
  Widget build(BuildContext context) {
    final b = boucle;
    if (b == null) return _contenu(1);
    return AnimatedBuilder(
      animation: b,
      // Départ et fin sur 10:30 ; entre les deux, 14:00 puis 09:00.
      builder: (context, _) => _contenu(((b.value * 3).floor() + 1) % 3),
    );
  }

  Widget _contenu(int choisi) {
    return Row(
      children: [
        Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Row(
              children: [
                for (var i = 0; i < _heures.length; i++) ...[
                  if (i > 0) const SizedBox(width: 8),
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 250),
                    curve: Curves.easeOut,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 11,
                    ),
                    decoration: BoxDecoration(
                      color: i == choisi
                          ? CouleursSopi.bleu
                          : const Color(0xFFF0F2F7),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      _heures[i],
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        fontFeatures: const [FontFeature.tabularFigures()],
                        color: i == choisi
                            ? CouleursSopi.blanc
                            : CouleursSopi.texte,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(width: 8),
        const Icon(Icons.check_rounded, size: 18, color: _vert),
        const SizedBox(width: 4),
        const Text(
          '30 %',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: _vert,
          ),
        ),
      ],
    );
  }
}

/// Aperçu artisan : une demande arrive, puis le portefeuille est crédité de 3 000 FCFA (acompte moins commission).
/// Sans animation : état final.
class _ApercuDemande extends StatelessWidget {
  const _ApercuDemande({this.boucle});

  final Animation<double>? boucle;

  @override
  Widget build(BuildContext context) {
    final b = boucle;
    if (b == null) return _contenu(1, 1);
    return AnimatedBuilder(
      animation: b,
      builder: (context, _) =>
          _contenu(_etape(b.value, 0.08, 0.22), _etape(b.value, 0.3, 0.55)),
    );
  }

  Widget _contenu(double demande, double credit) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: CouleursSopi.blanc.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 14, 10),
        child: Row(
          children: [
            Expanded(
              child: _Fondu(
                valeur: demande,
                child: Transform.translate(
                  offset: Offset(-12 * (1 - demande), 0),
                  child: const Row(
                    children: [
                      Icon(
                        Icons.notifications_none_rounded,
                        size: 18,
                        color: CouleursSopi.blanc,
                      ),
                      SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          'Nouvelle demande',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: CouleursSopi.blanc,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Text(
              '+${formaterArgent(_creditDemo * credit)}',
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: _vertClair,
                fontFeatures: [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Logo sur fond bleu : carré arrondi blanc, « S » bleu.
class _LogoBlanc extends StatelessWidget {
  const _LogoBlanc();

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: Container(
        width: 44,
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(14),
        ),
        child: const Text(
          'S',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w800,
            color: CouleursSopi.bleu,
          ),
        ),
      ),
    );
  }
}
