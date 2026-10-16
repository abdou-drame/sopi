import 'package:flutter/material.dart';

import '../animation/chargement.dart';
import '../api/erreurs.dart';
import '../theme/dimensions.dart';
import 'boutons.dart';
import 'en_tete_degrade.dart';
import 'etats.dart';

/// Une page de résultats : les éléments, la page courante et la dernière.
class PageListe<T> {
  const PageListe({
    required this.elements,
    required this.page,
    required this.derniere,
  });

  final List<T> elements;
  final int page;
  final int derniere;
}

/// Permet à l'écran qui contient la liste de la recharger ou de modifier un élément déjà affiché.
class ControleurListe<T> {
  _EtatListeInfinie<T>? _etat;

  List<T> get elements => List.unmodifiable(_etat?._elements ?? const []);

  Future<void> recharger() async => _etat?._charger();

  /// Remplace chaque élément pour lequel [concerne] est vrai par [maj].
  void modifier(bool Function(T) concerne, T Function(T) maj) =>
      _etat?._modifier(concerne, maj);

  /// Remplace tous les éléments.
  void transformerTous(T Function(T) maj) => _etat?._modifier((_) => true, maj);
}

/// Liste paginée (page suivante chargée en fin de défilement) sous un en-tête dégradé, avec les états
/// chargement, erreur, vide et le geste « tirer pour actualiser ».
class ListeInfinie<T> extends StatefulWidget {
  const ListeInfinie({
    super.key,
    required this.titreLigne1,
    required this.titreLigne2,
    required this.charger,
    required this.construire,
    required this.messageVide,
    this.iconeVide = Icons.inbox_outlined,
    this.controleur,
    this.action,
    this.enTete,
  });

  final String titreLigne1;
  final String titreLigne2;
  final Future<PageListe<T>> Function(int page) charger;
  final Widget Function(BuildContext, T) construire;
  final String messageVide;
  final IconData iconeVide;
  final ControleurListe<T>? controleur;

  /// Widget à droite du bouton retour de l'en-tête.
  final Widget? action;

  /// Contenu affiché entre l'en-tête et la liste (bouton, bandeau…) quand la liste n'est pas vide.
  final Widget? enTete;

  @override
  State<ListeInfinie<T>> createState() => _EtatListeInfinie<T>();
}

class _EtatListeInfinie<T> extends State<ListeInfinie<T>> {
  final _elements = <T>[];
  int _page = 1;
  int _derniere = 1;
  bool _chargement = true;
  bool _suite = false;
  String? _erreur;
  String? _erreurSuite;

  @override
  void initState() {
    super.initState();
    widget.controleur?._etat = this;
    Future.microtask(_charger);
  }

  @override
  void dispose() {
    if (widget.controleur?._etat == this) widget.controleur?._etat = null;
    super.dispose();
  }

  void _modifier(bool Function(T) concerne, T Function(T) maj) {
    setState(() {
      for (var i = 0; i < _elements.length; i++) {
        if (concerne(_elements[i])) _elements[i] = maj(_elements[i]);
      }
    });
  }

  Future<void> _charger() async {
    setState(() {
      _chargement = _elements.isEmpty;
      _erreur = null;
    });
    try {
      final page = await widget.charger(1);
      if (!mounted) return;
      setState(() {
        _elements
          ..clear()
          ..addAll(page.elements);
        _page = page.page;
        _derniere = page.derniere;
        _chargement = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _erreur = ErreurApi.depuis(e).message;
        _chargement = false;
      });
    }
  }

  Future<void> _chargerSuite() async {
    if (_chargement || _suite || _page >= _derniere || _erreur != null) return;
    setState(() {
      _suite = true;
      _erreurSuite = null;
    });
    try {
      final page = await widget.charger(_page + 1);
      if (!mounted) return;
      setState(() {
        _elements.addAll(page.elements);
        _page = page.page;
        _derniere = page.derniere;
        _suite = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _erreurSuite = ErreurApi.depuis(e).message;
        _suite = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final haut = MediaQuery.paddingOf(context).top;
    final bas = MediaQuery.paddingOf(context).bottom;
    final enTete = EnTeteDegrade(
      titreLigne1: widget.titreLigne1,
      titreLigne2: widget.titreLigne2,
      action: widget.action,
    );

    Widget zone(Widget enfant, {double hauteur = 320}) => SliverToBoxAdapter(
      child: SizedBox(height: hauteur, child: enfant),
    );

    final List<Widget> corps;
    if (_chargement) {
      corps = [
        const SliverPadding(
          padding: EdgeInsets.fromLTRB(16, 20, 16, 0),
          sliver: SliverToBoxAdapter(
            child: Column(
              children: [
                Squelette(hauteur: 90, rayon: 22),
                SizedBox(height: 10),
                Squelette(hauteur: 90, rayon: 22),
                SizedBox(height: 10),
                Squelette(hauteur: 90, rayon: 22),
              ],
            ),
          ),
        ),
      ];
    } else if (_erreur != null) {
      corps = [zone(EtatErreur(message: _erreur!, onReessayer: _charger))];
    } else if (_elements.isEmpty) {
      corps = [
        zone(EtatVide(message: widget.messageVide, icone: widget.iconeVide)),
      ];
    } else {
      corps = [
        if (widget.enTete != null) SliverToBoxAdapter(child: widget.enTete),
        SliverPadding(
          padding: EdgeInsets.fromLTRB(
            DimensionsSopi.margeCarte,
            16,
            DimensionsSopi.margeCarte,
            bas + 24,
          ),
          sliver: SliverList.separated(
            itemCount: _elements.length + 1,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, i) {
              if (i < _elements.length) {
                return widget.construire(context, _elements[i]);
              }
              if (_suite) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Center(
                    child: SizedBox(
                      width: 28,
                      height: 28,
                      child: CircularProgressIndicator(strokeWidth: 3),
                    ),
                  ),
                );
              }
              if (_erreurSuite != null) {
                return Column(
                  children: [
                    Text(_erreurSuite!, textAlign: TextAlign.center),
                    const SizedBox(height: 8),
                    BoutonCompact(
                      libelle: 'Réessayer',
                      ton: TonBoutonCompact.clair,
                      onPressed: _chargerSuite,
                    ),
                  ],
                );
              }
              return const SizedBox.shrink();
            },
          ),
        ),
      ];
    }

    return NotificationListener<ScrollNotification>(
      onNotification: (n) {
        if (n.metrics.axis == Axis.vertical && n.metrics.extentAfter < 400) {
          _chargerSuite();
        }
        return false;
      },
      child: RefreshIndicator(
        onRefresh: _charger,
        edgeOffset: haut,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(child: enTete),
            ...corps,
          ],
        ),
      ),
    );
  }
}
