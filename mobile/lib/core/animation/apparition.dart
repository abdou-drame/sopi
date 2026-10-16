import 'package:flutter/material.dart';

/// Les animations sont-elles autorisées ? Faux quand l'utilisateur a désactivé les animations (accessibilité).
bool animationsActives(BuildContext context) =>
    !MediaQuery.disableAnimationsOf(context);

/// Délai entre deux éléments d'une cascade, et nombre d'éléments au-delà duquel les suivants apparaissent ensemble.
const decalageCascade = Duration(milliseconds: 70);
const plafondCascade = 12;

/// Délai d'apparition de l'élément [index] d'une cascade (plafonné pour ne jamais ralentir une longue liste).
Duration delaiCascade(
  int index, {
  Duration decalage = decalageCascade,
  int plafond = plafondCascade,
}) {
  final rang = index < 0 ? 0 : (index >= plafond ? plafond - 1 : index);
  return decalage * rang;
}

/// Indique aux descendants qu'un parent (cascade, liste) gère déjà leur apparition : ils ne s'animent pas eux-mêmes.
class ApparitionHeritee extends InheritedWidget {
  const ApparitionHeritee({super.key, required super.child});

  static bool dans(BuildContext context) =>
      context.getInheritedWidgetOfExactType<ApparitionHeritee>() != null;

  @override
  bool updateShouldNotify(ApparitionHeritee oldWidget) => false;
}

/// Fondu + glissement de [decalage] pixels. Ne joue qu'une fois, à la première apparition du widget (une
/// reconstruction ne le rejoue pas). Sans animation (accessibilité), le contenu est affiché tout de suite.
class Apparition extends StatefulWidget {
  const Apparition({
    super.key,
    required this.child,
    this.delai = Duration.zero,
    this.duree = const Duration(milliseconds: 300),
    this.decalage = const Offset(0, 16),
  });

  /// Élément [index] d'une liste : délai de cascade plafonné.
  Apparition.indexee({
    super.key,
    required int index,
    required this.child,
    this.duree = const Duration(milliseconds: 300),
    this.decalage = const Offset(0, 16),
  }) : delai = delaiCascade(index);

  final Widget child;
  final Duration delai;
  final Duration duree;

  /// Glissement initial en pixels (x, y) ; l'élément arrive à sa place.
  final Offset decalage;

  @override
  State<Apparition> createState() => _ApparitionState();
}

class _ApparitionState extends State<Apparition>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controleur;
  late final Animation<double> _progression;
  bool _initialise = false;
  bool _anime = false;

  @override
  void initState() {
    super.initState();
    final total = widget.delai + widget.duree;
    _controleur = AnimationController(vsync: this, duration: total);
    final debut = total.inMicroseconds == 0
        ? 0.0
        : widget.delai.inMicroseconds / total.inMicroseconds;
    _progression = CurvedAnimation(
      parent: _controleur,
      curve: Interval(debut, 1, curve: Curves.easeOutCubic),
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
      animation: _progression,
      child: widget.child,
      builder: (context, enfant) {
        final reste = 1 - _progression.value;
        return Opacity(
          opacity: _progression.value,
          child: Transform.translate(
            offset: Offset(
              widget.decalage.dx * reste,
              widget.decalage.dy * reste,
            ),
            child: enfant,
          ),
        );
      },
    );
  }
}

/// Enveloppe une liste d'éléments : ils arrivent en fondu et glissement, avec 70 ms entre deux éléments
/// (au-delà de [plafond] éléments, les suivants arrivent ensemble). Pour une ligne de tuiles, utiliser
/// [axe] = horizontal : elles arrivent de gauche à droite.
class ApparitionEnCascade extends StatelessWidget {
  const ApparitionEnCascade({
    super.key,
    required this.children,
    this.axe = Axis.vertical,
    this.decalage = decalageCascade,
    this.plafond = plafondCascade,
    this.delaiInitial = Duration.zero,
    this.espacement = 0,
  });

  final List<Widget> children;
  final Axis axe;
  final Duration decalage;
  final int plafond;
  final Duration delaiInitial;
  final double espacement;

  @override
  Widget build(BuildContext context) {
    final vertical = axe == Axis.vertical;
    final elements = <Widget>[
      for (var i = 0; i < children.length; i++)
        ApparitionHeritee(
          child: Apparition(
            delai:
                delaiInitial +
                delaiCascade(i, decalage: decalage, plafond: plafond),
            decalage: vertical ? const Offset(0, 16) : const Offset(-16, 0),
            child: children[i],
          ),
        ),
    ];
    final avecEspace = <Widget>[
      for (var i = 0; i < elements.length; i++) ...[
        if (i > 0 && espacement > 0)
          vertical ? SizedBox(height: espacement) : SizedBox(width: espacement),
        elements[i],
      ],
    ];
    return vertical
        ? Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: avecEspace,
          )
        : Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: avecEspace,
          );
  }
}
