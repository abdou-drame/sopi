import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import 'depot_reservation.dart';
import 'modeles.dart';

/// État de la liste des prestataires : filtres, éléments déjà chargés et pagination.
class EtatListe {
  const EtatListe({
    this.filtres = const FiltresPrestataires(),
    this.elements = const [],
    this.pagination,
    this.chargement = true,
    this.chargementSuite = false,
    this.erreur,
    this.erreurSuite,
  });

  final FiltresPrestataires filtres;
  final List<PrestataireResume> elements;
  final Pagination? pagination;

  /// Chargement de la première page (squelettes).
  final bool chargement;

  /// Chargement d'une page suivante (indicateur en bas de liste).
  final bool chargementSuite;

  /// Message d'erreur de la première page, ou null.
  final String? erreur;
  final String? erreurSuite;

  int get total => pagination?.total ?? elements.length;
  bool get encore => pagination?.encore ?? false;
}

/// Contrôleur de la liste, un par métier ('' = tous). Garde les filtres tant que l'écran est ouvert,
/// y compris quand on revient de la fiche d'un prestataire.
class ListePrestatairesControleur extends Notifier<EtatListe> {
  ListePrestatairesControleur(this.metier);

  /// Code du métier filtré, ou '' pour tous.
  final String metier;

  /// Numéro de la dernière requête lancée : les réponses plus anciennes sont ignorées.
  int _generation = 0;

  @override
  EtatListe build() {
    Future.microtask(charger);
    return const EtatListe();
  }

  DepotReservation get _depot => ref.read(depotReservationProvider);

  Future<void> charger() async {
    final numero = ++_generation;
    state = EtatListe(filtres: state.filtres);
    try {
      final page = await _depot.prestataires(
        metier: metier.isEmpty ? null : metier,
        filtres: state.filtres,
      );
      if (!ref.mounted || numero != _generation) return;
      state = EtatListe(
        filtres: state.filtres,
        elements: page.elements,
        pagination: page.pagination,
        chargement: false,
      );
    } catch (e) {
      if (!ref.mounted || numero != _generation) return;
      state = EtatListe(
        filtres: state.filtres,
        chargement: false,
        erreur: ErreurApi.depuis(e).message,
      );
    }
  }

  Future<void> chargerSuite() async {
    final pagination = state.pagination;
    if (state.chargement ||
        state.chargementSuite ||
        state.erreur != null ||
        pagination == null ||
        !pagination.encore) {
      return;
    }
    final numero = _generation;
    state = EtatListe(
      filtres: state.filtres,
      elements: state.elements,
      pagination: pagination,
      chargement: false,
      chargementSuite: true,
    );
    try {
      final page = await _depot.prestataires(
        metier: metier.isEmpty ? null : metier,
        filtres: state.filtres,
        page: pagination.page + 1,
      );
      if (!ref.mounted || numero != _generation) return;
      final connus = {for (final p in state.elements) p.id};
      state = EtatListe(
        filtres: state.filtres,
        elements: [
          ...state.elements,
          ...page.elements.where((p) => !connus.contains(p.id)),
        ],
        pagination: page.pagination,
        chargement: false,
      );
    } catch (e) {
      if (!ref.mounted || numero != _generation) return;
      state = EtatListe(
        filtres: state.filtres,
        elements: state.elements,
        pagination: pagination,
        chargement: false,
        erreurSuite: ErreurApi.depuis(e).message,
      );
    }
  }

  /// Applique de nouveaux filtres et recharge la première page.
  Future<void> appliquer(FiltresPrestataires filtres) {
    state = EtatListe(filtres: filtres);
    return charger();
  }

  Future<void> rechercher(String texte) {
    if (texte.trim() == state.filtres.recherche.trim()) return Future.value();
    return appliquer(state.filtres.copie(recherche: texte));
  }

  Future<void> effacerFiltres() => appliquer(state.filtres.sansFiltres());
}

final listePrestatairesProvider = NotifierProvider.autoDispose
    .family<ListePrestatairesControleur, EtatListe, String>(
      ListePrestatairesControleur.new,
    );
