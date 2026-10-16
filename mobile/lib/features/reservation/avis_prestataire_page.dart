import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import 'depot_reservation.dart';
import 'fiche_prestataire_page.dart';
import 'formats.dart';
import 'modeles.dart';

/// Liste simple et paginée de tous les avis d'un prestataire (écran absent des maquettes).
class AvisPrestatairePage extends ConsumerStatefulWidget {
  const AvisPrestatairePage({super.key, required this.prestataireId});

  final String prestataireId;

  @override
  ConsumerState<AvisPrestatairePage> createState() =>
      _AvisPrestatairePageState();
}

class _AvisPrestatairePageState extends ConsumerState<AvisPrestatairePage> {
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

  DepotReservation get _depot => ref.read(depotReservationProvider);

  Future<void> _charger() async {
    setState(() {
      _chargement = true;
      _erreur = null;
    });
    try {
      final page = await _depot.avis(widget.prestataireId);
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
      final page = await _depot.avis(widget.prestataireId, page: p.page + 1);
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
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    final bas = MediaQuery.paddingOf(context).bottom;

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
            for (var i = 0; i < _avis.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Apparition.indexee(
                  index: i,
                  child: CarteAvis(avis: _avis[i]),
                ),
              ),
            if (_suite)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: SizedBox(
                  width: 28,
                  height: 28,
                  child: CircularProgressIndicator(strokeWidth: 3),
                ),
              ),
            if (_erreurSuite != null)
              Column(
                children: [
                  Text(_erreurSuite!, textAlign: TextAlign.center),
                  const SizedBox(height: 8),
                  BoutonCompact(
                    libelle: 'Réessayer',
                    ton: TonBoutonCompact.clair,
                    onPressed: _chargerSuite,
                  ),
                ],
              ),
          ],
        ),
      );
    }

    return Scaffold(
      body: NotificationListener<ScrollNotification>(
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
              SliverToBoxAdapter(
                child: DescenteDepuisLeHaut(
                  child: ClipRRect(
                    borderRadius: const BorderRadius.vertical(
                      bottom: Radius.circular(DimensionsSopi.rayonEntete),
                    ),
                    child: FondDegrade(
                      child: Padding(
                        padding: EdgeInsets.fromLTRB(20, haut + 24, 20, 22),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                BoutonRetour(onPressed: () => context.pop()),
                                Expanded(
                                  child: Text(
                                    'Avis',
                                    textAlign: TextAlign.center,
                                    style: theme.textTheme.titleMedium
                                        ?.copyWith(
                                          fontSize: 17,
                                          fontWeight: FontWeight.w700,
                                        ),
                                  ),
                                ),
                                const SizedBox(width: 64),
                              ],
                            ),
                            const SizedBox(height: 16),
                            if (!_chargement && _erreur == null)
                              Center(
                                child: Text(
                                  _nombre == 0
                                      ? 'Aucun avis'
                                      : _note == null
                                      ? '$_nombre avis'
                                      : '${formaterNote(_note!)} sur 5 · $_nombre avis',
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w600,
                                    color: CouleursSopi.texteSecondaire,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              SliverToBoxAdapter(child: corps),
              SliverToBoxAdapter(child: SizedBox(height: bas + 24)),
            ],
          ),
        ),
      ),
    );
  }
}
