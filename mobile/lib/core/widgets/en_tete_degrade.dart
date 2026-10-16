import 'package:flutter/material.dart';

import '../animation/apparition.dart';
import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

/// Bouton retour : pilule marine 64×44 avec flèche blanche.
class BoutonRetour extends StatelessWidget {
  const BoutonRetour({super.key, this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Retour',
      child: Material(
        color: CouleursSopi.marine,
        borderRadius: BorderRadius.circular(DimensionsSopi.hauteurRetour / 2),
        child: InkWell(
          borderRadius: BorderRadius.circular(DimensionsSopi.hauteurRetour / 2),
          onTap: onPressed ?? () => Navigator.of(context).maybePop(),
          child: const SizedBox(
            width: DimensionsSopi.largeurRetour,
            height: DimensionsSopi.hauteurRetour,
            child: Icon(Icons.arrow_back_rounded, color: CouleursSopi.blanc),
          ),
        ),
      ),
    );
  }
}

/// Fond de l'en-tête : #eef4ff avec deux taches radiales (bleu clair et indigo clair).
class FondDegrade extends StatelessWidget {
  const FondDegrade({super.key, this.child, this.tacheBasse = false});

  final Widget? child;

  /// Troisième tache, bleu pastel en bas au centre (maquette Connexion).
  final bool tacheBasse;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(color: CouleursSopi.enteteFond),
      child: Stack(
        fit: StackFit.passthrough,
        children: [
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(-0.9, -1.1),
                  radius: 0.9,
                  colors: [CouleursSopi.enteteTacheBleue, Color(0x00BFDBFE)],
                ),
              ),
            ),
          ),
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(1.0, 0.2),
                  radius: 0.8,
                  colors: [CouleursSopi.enteteTacheIndigo, Color(0x00C7D2FE)],
                ),
              ),
            ),
          ),
          if (tacheBasse)
            const Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: RadialGradient(
                    center: Alignment(0.1, 1.0),
                    radius: 0.7,
                    colors: [CouleursSopi.bleuPastel, Color(0x00DBEAFE)],
                  ),
                ),
              ),
            ),
          ?child,
        ],
      ),
    );
  }
}

/// Fait descendre son contenu depuis le haut de l'écran (450 ms). Ne joue qu'une fois ; sans animation
/// (accessibilité), le contenu est affiché tout de suite.
class DescenteDepuisLeHaut extends StatefulWidget {
  const DescenteDepuisLeHaut({super.key, required this.child});

  final Widget child;

  static const duree = Duration(milliseconds: 450);

  @override
  State<DescenteDepuisLeHaut> createState() => _DescenteDepuisLeHautState();
}

class _DescenteDepuisLeHautState extends State<DescenteDepuisLeHaut>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controleur;
  late final Animation<double> _descente;
  bool _initialise = false;
  bool _anime = false;

  @override
  void initState() {
    super.initState();
    _controleur = AnimationController(
      vsync: this,
      duration: DescenteDepuisLeHaut.duree,
    );
    _descente = CurvedAnimation(
      parent: _controleur,
      curve: Curves.easeOutCubic,
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialise) return;
    _initialise = true;
    _anime = animationsActives(context);
    if (_anime) {
      _controleur.forward();
    } else {
      _controleur.value = 1;
    }
  }

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_anime) return widget.child;
    return AnimatedBuilder(
      animation: _descente,
      child: widget.child,
      builder: (context, enfant) => FractionalTranslation(
        translation: Offset(0, -(1 - _descente.value)),
        child: enfant,
      ),
    );
  }
}

/// En-tête dégradé aux angles inférieurs arrondis (36), avec titre en deux lignes (400 puis 800) et bouton
/// retour. Il descend du haut (450 ms), puis le bouton et le titre apparaissent en fondu (300 ms chacun).
class EnTeteDegrade extends StatefulWidget {
  const EnTeteDegrade({
    super.key,
    required this.titreLigne1,
    this.titreLigne2,
    this.avecRetour = true,
    this.onRetour,
    this.action,
    this.enfant,
  });

  final String titreLigne1;
  final String? titreLigne2;
  final bool avecRetour;
  final VoidCallback? onRetour;

  /// Widget à droite du bouton retour (par exemple une pastille).
  final Widget? action;

  /// Contenu sous le titre (champ de recherche, onglets…).
  final Widget? enfant;

  static const dureeDescente = Duration(milliseconds: 450);

  @override
  State<EnTeteDegrade> createState() => _EnTeteDegradeState();
}

class _EnTeteDegradeState extends State<EnTeteDegrade>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controleur;
  late final Animation<double> _descente;
  bool _initialise = false;
  bool _anime = false;

  @override
  void initState() {
    super.initState();
    _controleur = AnimationController(
      vsync: this,
      duration: EnTeteDegrade.dureeDescente,
    );
    _descente = CurvedAnimation(
      parent: _controleur,
      curve: Curves.easeOutCubic,
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialise) return;
    _initialise = true;
    _anime = animationsActives(context);
    if (_anime) {
      _controleur.forward();
    } else {
      _controleur.value = 1;
    }
  }

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    final titre = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.titreLigne1,
          style: theme.textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w400,
            height: 1.15,
          ),
        ),
        if (widget.titreLigne2 != null)
          Text(
            widget.titreLigne2!,
            style: theme.textTheme.headlineMedium?.copyWith(
              fontWeight: FontWeight.w800,
              height: 1.15,
            ),
          ),
      ],
    );

    final contenu = Padding(
      padding: EdgeInsets.fromLTRB(
        DimensionsSopi.margeEcran,
        haut + 12,
        DimensionsSopi.margeEcran,
        24,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (widget.avecRetour || widget.action != null)
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                if (widget.avecRetour)
                  Apparition(
                    delai: EnTeteDegrade.dureeDescente,
                    child: BoutonRetour(onPressed: widget.onRetour),
                  )
                else
                  const SizedBox.shrink(),
                if (widget.action != null)
                  Apparition(
                    delai: EnTeteDegrade.dureeDescente,
                    child: widget.action!,
                  ),
              ],
            ),
          if (widget.avecRetour || widget.action != null)
            const SizedBox(height: 18),
          Apparition(
            delai:
                EnTeteDegrade.dureeDescente + const Duration(milliseconds: 80),
            child: titre,
          ),
          if (widget.enfant != null) ...[
            const SizedBox(height: 18),
            Apparition(
              delai:
                  EnTeteDegrade.dureeDescente +
                  const Duration(milliseconds: 160),
              child: widget.enfant!,
            ),
          ],
        ],
      ),
    );

    final fond = ClipRRect(
      borderRadius: const BorderRadius.vertical(
        bottom: Radius.circular(DimensionsSopi.rayonEntete),
      ),
      child: FondDegrade(child: contenu),
    );

    if (!_anime) return fond;
    return AnimatedBuilder(
      animation: _descente,
      child: fond,
      builder: (context, enfant) => FractionalTranslation(
        translation: Offset(0, -(1 - _descente.value)),
        child: enfant,
      ),
    );
  }
}
