import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/animation/chargement.dart';
import '../../../core/api/erreurs.dart';
import '../../../core/theme/couleurs.dart';
import '../../../core/widgets/boutons.dart';
import '../../../core/widgets/en_tete_degrade.dart';
import '../../../core/widgets/etats.dart';
import '../depot_reservation.dart';
import '../fiche_prestataire_page.dart';
import '../formats.dart';
import '../modeles.dart';

/// Écran « liste d'avis » commun au client (Avis sur {entreprise}) et au prestataire (Mes avis) : carte marine
/// (note moyenne, nombre d'avis), avis du plus récent au plus ancien, bouton « Voir plus d'avis » pour la page suivante.
class ListeAvisPage extends ConsumerStatefulWidget {
  const ListeAvisPage({
    super.key,
    required this.titreLigne1,
    required this.titreLigne2,
    required this.chargerPage,
    required this.onRetour,
  });

  final String titreLigne1;
  final String titreLigne2;
  final Future<PageAvis> Function(int page) chargerPage;
  final VoidCallback onRetour;

  @override
  ConsumerState<ListeAvisPage> createState() => _ListeAvisPageState();
}

class _ListeAvisPageState extends ConsumerState<ListeAvisPage> {
  final _avis = <Avis>[];
  Pagination? _pagination;
  double? _note;
  int _nombre = 0;
  bool _chargement = true;
  bool _suite = false;
  String? _erreur;
  String? _erreurSuite;

  @override
  void initState() {
    super.initState();
    Future.microtask(_charger);
  }

  Future<void> _charger() async {
    setState(() {
      _chargement = _avis.isEmpty;
      _erreur = null;
    });
    try {
      final page = await widget.chargerPage(1);
      if (!mounted) return;
      setState(() {
        _avis
          ..clear()
          ..addAll(page.elements);
        _pagination = page.pagination;
        _note = page.noteMoyenne;
        _nombre = page.nombreAvis;
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
    final p = _pagination;
    if (_chargement || _suite || p == null || !p.encore) return;
    setState(() {
      _suite = true;
      _erreurSuite = null;
    });
    try {
      final page = await widget.chargerPage(p.page + 1);
      if (!mounted) return;
      final connus = {for (final a in _avis) a.id};
      setState(() {
        _avis.addAll(page.elements.where((a) => !connus.contains(a.id)));
        _pagination = page.pagination;
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
    final maintenant = ref.watch(horlogeProvider)();
    final bas = MediaQuery.paddingOf(context).bottom;
    final synthese = !_chargement && _erreur == null
        ? _SyntheseAvis(note: _note, nombre: _nombre)
        : null;

    Widget corps;
    if (_chargement) {
      corps = const Padding(
        padding: EdgeInsets.fromLTRB(16, 20, 16, 0),
        child: Column(
          children: [
            Squelette(hauteur: 90, rayon: 22),
            SizedBox(height: 10),
            Squelette(hauteur: 90, rayon: 22),
            SizedBox(height: 10),
            Squelette(hauteur: 90, rayon: 22),
          ],
        ),
      );
    } else if (_erreur != null) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(message: _erreur!, onReessayer: _charger),
      );
    } else if (_avis.isEmpty) {
      corps = const SizedBox(
        height: 320,
        child: EtatVide(
          message: 'Aucun avis pour le moment.',
          icone: Icons.rate_review_outlined,
        ),
      );
    } else {
      corps = Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
        child: Column(
          children: [
            for (final a in _avis)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: CarteAvis(avis: a, maintenant: maintenant),
              ),
            if (_erreurSuite != null) ...[
              Text(_erreurSuite!, textAlign: TextAlign.center),
              const SizedBox(height: 8),
            ],
            if (_pagination?.encore ?? false)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: BoutonCompact(
                  libelle: "Voir plus d'avis",
                  ton: TonBoutonCompact.contour,
                  hauteur: 48,
                  taille: 14,
                  pleineLargeur: true,
                  chargement: _suite,
                  onPressed: _suite ? null : _chargerSuite,
                ),
              ),
          ],
        ),
      );
    }

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _charger,
        edgeOffset: MediaQuery.paddingOf(context).top,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: EnTeteDegrade(
                titreLigne1: widget.titreLigne1,
                titreLigne2: widget.titreLigne2,
                onRetour: widget.onRetour,
                enfant: synthese,
              ),
            ),
            SliverToBoxAdapter(child: corps),
            SliverToBoxAdapter(child: SizedBox(height: bas + 24)),
          ],
        ),
      ),
    );
  }
}

/// Carte marine de 92 px : note moyenne et nombre d'avis, séparés par un filet.
class _SyntheseAvis extends StatelessWidget {
  const _SyntheseAvis({required this.note, required this.nombre});

  final double? note;
  final int nombre;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget bloc(String valeur, String libelle) => Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          valeur,
          style: theme.textTheme.titleLarge?.copyWith(
            color: CouleursSopi.blanc,
            fontSize: 22,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          libelle,
          style: theme.textTheme.bodySmall?.copyWith(
            color: CouleursSopi.blanc.withValues(alpha: 0.7),
            fontSize: 11,
          ),
        ),
      ],
    );
    return Container(
      key: const ValueKey('synthese-avis'),
      height: 92,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      decoration: BoxDecoration(
        color: CouleursSopi.marine,
        borderRadius: BorderRadius.circular(26),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          bloc(note == null ? '–' : formaterNote(note!), 'Note moyenne'),
          Container(
            width: 1,
            height: 40,
            color: CouleursSopi.blanc.withValues(alpha: 0.18),
          ),
          bloc('$nombre', 'Avis'),
        ],
      ),
    );
  }
}
