import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import 'depot_reservation.dart';
import 'formats.dart';
import 'liste_prestataires_controleur.dart';
import 'modeles.dart';
import 'widgets/composants.dart';
import 'widgets/feuilles_filtres.dart';

/// Liste des prestataires, éventuellement limitée à un métier ([metier] = code, '' = tous).
class ListePrestatairesPage extends ConsumerStatefulWidget {
  const ListePrestatairesPage({
    super.key,
    this.metier = '',
    this.avecRetour = true,
  });

  final String metier;

  /// Faux quand la liste est un onglet de la barre (pas de retour possible).
  final bool avecRetour;

  @override
  ConsumerState<ListePrestatairesPage> createState() =>
      _ListePrestatairesPageState();
}

class _ListePrestatairesPageState extends ConsumerState<ListePrestatairesPage> {
  static const _delaiRecherche = Duration(milliseconds: 400);

  late final TextEditingController _recherche;
  Timer? _minuteur;

  @override
  void initState() {
    super.initState();
    _recherche = TextEditingController(
      text: ref
          .read(listePrestatairesProvider(widget.metier))
          .filtres
          .recherche,
    );
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    _recherche.dispose();
    super.dispose();
  }

  ListePrestatairesControleur get _controleur =>
      ref.read(listePrestatairesProvider(widget.metier).notifier);

  void _surRecherche(String texte) {
    _minuteur?.cancel();
    _minuteur = Timer(_delaiRecherche, () => _controleur.rechercher(texte));
  }

  void _appliquer(FiltresPrestataires Function(FiltresPrestataires) f) {
    final actuels = ref.read(listePrestatairesProvider(widget.metier)).filtres;
    _controleur.appliquer(f(actuels));
  }

  Future<void> _choisirQuartier(FiltresPrestataires filtres) async {
    final r = await ouvrirFeuille<Choix<String>>(
      context,
      titre: 'Quartier',
      contenu: (_) => FeuilleQuartier(initial: filtres.quartier),
    );
    if (r != null) _appliquer((f) => f.copie(quartier: r.valeur));
  }

  Future<void> _choisirNote(FiltresPrestataires filtres) async {
    final r = await ouvrirFeuille<Choix<double>>(
      context,
      titre: 'Note minimale',
      contenu: (_) => FeuilleNote(initial: filtres.noteMin),
    );
    if (r != null) _appliquer((f) => f.copie(noteMin: r.valeur));
  }

  Future<void> _choisirPrix(FiltresPrestataires filtres) async {
    final r = await ouvrirFeuille<Choix<(int?, int?)>>(
      context,
      titre: 'Prix',
      contenu: (_) => FeuillePrix(min: filtres.prixMin, max: filtres.prixMax),
    );
    final valeur = r?.valeur;
    if (r != null) {
      _appliquer((f) => f.copie(prixMin: valeur?.$1, prixMax: valeur?.$2));
    }
  }

  Future<void> _choisirTri(FiltresPrestataires filtres) async {
    final r = await ouvrirFeuille<TriPrestataires>(
      context,
      titre: 'Trier par',
      contenu: (_) => FeuilleTri(initial: filtres.tri),
    );
    if (r != null && r != filtres.tri) _appliquer((f) => f.copie(tri: r));
  }

  void _effacer() {
    _minuteur?.cancel();
    _recherche.clear();
    _controleur.effacerFiltres();
  }

  @override
  Widget build(BuildContext context) {
    final etat = ref.watch(listePrestatairesProvider(widget.metier));
    final filtres = etat.filtres;
    final metiers = ref.watch(metiersProvider).asData?.value ?? const [];
    final nomMetier = metiers
        .where((m) => m.code == widget.metier)
        .map((m) => m.nom)
        .firstOrNull;
    final titre = widget.metier.isEmpty ? 'Prestataires' : (nomMetier ?? '');

    Widget corps;
    if (etat.chargement) {
      corps = const _CorpsSquelette();
    } else if (etat.erreur != null) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(
          message: etat.erreur!,
          onReessayer: () => _controleur.charger(),
        ),
      );
    } else if (etat.elements.isEmpty) {
      corps = SizedBox(
        height: 320,
        child: EtatVide(
          message: 'Aucun prestataire ne correspond.',
          icone: Icons.search_off_rounded,
          libelleAction: filtres.actifs ? 'Effacer les filtres' : null,
          onAction: filtres.actifs ? _effacer : null,
        ),
      );
    } else {
      corps = _CorpsListe(
        etat: etat,
        onSuite: () => _controleur.chargerSuite(),
      );
    }

    return Scaffold(
      body: NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n.metrics.axis == Axis.vertical &&
              n.metrics.extentAfter < 400 &&
              etat.encore) {
            _controleur.chargerSuite();
          }
          return false;
        },
        child: RefreshIndicator(
          onRefresh: () => _controleur.charger(),
          edgeOffset: MediaQuery.paddingOf(context).top,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: _Entete(
                  titre: titre,
                  avecRetour: widget.avecRetour,
                  filtres: filtres,
                  recherche: _recherche,
                  onRecherche: _surRecherche,
                  onTri: () => _choisirTri(filtres),
                  onQuartier: () => _choisirQuartier(filtres),
                  onNote: () => _choisirNote(filtres),
                  onPrix: () => _choisirPrix(filtres),
                ),
              ),
              if (!etat.chargement && etat.erreur == null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
                    child: Text(
                      etat.total == 1
                          ? '1 prestataire disponible'
                          : '${etat.total} prestataires disponibles',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        fontSize: 13,
                        color: CouleursSopi.texteDiscret,
                      ),
                    ),
                  ),
                ),
              SliverToBoxAdapter(child: corps),
              SliverToBoxAdapter(child: SizedBox(height: margeBas(context))),
            ],
          ),
        ),
      ),
    );
  }

  double margeBas(BuildContext context) =>
      MediaQuery.paddingOf(context).bottom + 24;
}

class _Entete extends StatelessWidget {
  const _Entete({
    required this.titre,
    required this.avecRetour,
    required this.filtres,
    required this.recherche,
    required this.onRecherche,
    required this.onTri,
    required this.onQuartier,
    required this.onNote,
    required this.onPrix,
  });

  final String titre;
  final bool avecRetour;
  final FiltresPrestataires filtres;
  final TextEditingController recherche;
  final ValueChanged<String> onRecherche;
  final VoidCallback onTri;
  final VoidCallback onQuartier;
  final VoidCallback onNote;
  final VoidCallback onPrix;

  String get _libelleNote => filtres.noteMin == null
      ? 'Note'
      : 'Note : ${formaterNote(filtres.noteMin!).replaceAll(',0', '')}+';

  String get _libellePrix {
    final min = filtres.prixMin;
    final max = filtres.prixMax;
    if (min == null && max == null) return 'Prix';
    if (min != null && max != null) return 'Prix : $min – $max';
    return min != null ? 'Prix : dès $min' : 'Prix : jusqu\'à $max';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    return DescenteDepuisLeHaut(
      child: ClipRRect(
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(DimensionsSopi.rayonEntete),
        ),
        child: FondDegrade(
          child: Padding(
            padding: EdgeInsets.fromLTRB(16, haut + 24, 16, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    const SizedBox(width: 4),
                    if (avecRetour)
                      BoutonRetour(onPressed: () => context.pop())
                    else
                      const SizedBox(width: DimensionsSopi.largeurRetour),
                    Expanded(
                      child: Text(
                        titre,
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    Semantics(
                      button: true,
                      label: 'Trier',
                      child: Material(
                        color: CouleursSopi.blanc,
                        shape: const CircleBorder(),
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: onTri,
                          child: const SizedBox(
                            width: 44,
                            height: 44,
                            child: Icon(
                              Icons.filter_list_rounded,
                              color: CouleursSopi.marine,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 4),
                  ],
                ),
                const SizedBox(height: 20),
                Container(
                  height: 52,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  decoration: BoxDecoration(
                    color: CouleursSopi.blanc,
                    borderRadius: BorderRadius.circular(26),
                    border: Border.all(color: CouleursSopi.bordure),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.search_rounded,
                        size: 20,
                        color: CouleursSopi.texteDiscret,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: recherche,
                          onChanged: onRecherche,
                          textInputAction: TextInputAction.search,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontSize: 14,
                          ),
                          decoration: InputDecoration(
                            hintText: 'Rechercher un prestataire…',
                            hintStyle: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 14,
                              color: CouleursSopi.texteDiscret,
                            ),
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            filled: false,
                            isDense: true,
                            contentPadding: EdgeInsets.zero,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _Pilule(
                        libelle: filtres.quartier == null
                            ? 'Quartier'
                            : 'Quartier : ${filtres.quartier}',
                        active: filtres.quartier != null,
                        onTap: onQuartier,
                      ),
                      const SizedBox(width: 8),
                      _Pilule(
                        libelle: _libelleNote,
                        active: filtres.noteMin != null,
                        onTap: onNote,
                      ),
                      const SizedBox(width: 8),
                      _Pilule(
                        libelle: _libellePrix,
                        active:
                            filtres.prixMin != null || filtres.prixMax != null,
                        onTap: onPrix,
                      ),
                    ],
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

class _Pilule extends StatelessWidget {
  const _Pilule({
    required this.libelle,
    required this.active,
    required this.onTap,
  });

  final String libelle;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: active,
      child: Material(
        color: active ? CouleursSopi.bleu : CouleursSopi.blanc,
        shape: StadiumBorder(
          side: active
              ? BorderSide.none
              : const BorderSide(color: CouleursSopi.bordureForte),
        ),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: onTap,
          child: Container(
            height: 40,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            alignment: Alignment.center,
            child: Text(
              libelle,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                fontSize: 13,
                fontWeight: active ? FontWeight.w700 : FontWeight.w600,
                color: active ? CouleursSopi.blanc : CouleursSopi.marine,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _CorpsSquelette extends StatelessWidget {
  const _CorpsSquelette();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.fromLTRB(16, 30, 16, 0),
      child: Column(
        children: [
          SqueletteCartePrestataire(),
          SizedBox(height: 12),
          SqueletteCartePrestataire(),
          SizedBox(height: 12),
          SqueletteCartePrestataire(),
        ],
      ),
    );
  }
}

class _CorpsListe extends StatelessWidget {
  const _CorpsListe({required this.etat, required this.onSuite});

  final EtatListe etat;
  final VoidCallback onSuite;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Column(
        children: [
          for (var i = 0; i < etat.elements.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Apparition.indexee(
                index: i,
                child: _CartePrestataire(prestataire: etat.elements[i]),
              ),
            ),
          if (etat.chargementSuite)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(strokeWidth: 3),
              ),
            ),
          if (etat.erreurSuite != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                children: [
                  Text(
                    etat.erreurSuite!,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 8),
                  BoutonCompact(
                    libelle: 'Réessayer',
                    ton: TonBoutonCompact.clair,
                    onPressed: onSuite,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _CartePrestataire extends StatelessWidget {
  const _CartePrestataire({required this.prestataire});

  final PrestataireResume prestataire;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    void ouvrir() => context.push(Chemins.fichePrestataire(prestataire.id));
    return Material(
      color: CouleursSopi.blanc,
      borderRadius: BorderRadius.circular(26),
      child: InkWell(
        borderRadius: BorderRadius.circular(26),
        onTap: ouvrir,
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(26),
            border: Border.all(color: CouleursSopi.bordure),
          ),
          child: Column(
            children: [
              Row(
                children: [
                  PastilleMetier(
                    code: prestataire.metierCode,
                    taille: 56,
                    rayon: 18,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          prestataire.nomEntreprise,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(
                              Icons.location_on_outlined,
                              size: 14,
                              color: CouleursSopi.texteDiscret,
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                prestataire.quartier,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  fontSize: 12,
                                  color: CouleursSopi.texteDiscret,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  NotePrestataire(prestataire: prestataire, taille: 14),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Flexible(
                    child: prestataire.prixMin == null
                        ? const SizedBox.shrink()
                        : Text.rich(
                            TextSpan(
                              text: 'À partir de ',
                              children: [
                                TextSpan(
                                  text: formaterArgent(prestataire.prixMin!),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                    color: CouleursSopi.marine,
                                  ),
                                ),
                              ],
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 13,
                              color: CouleursSopi.texteSecondaire,
                            ),
                          ),
                  ),
                  const SizedBox(width: 8),
                  BoutonCompact(libelle: 'Réserver', onPressed: ouvrir),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
