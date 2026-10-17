import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import '../reservation/modeles.dart';
import 'depot_prestataire.dart';
import 'modeles.dart';

/// État d'un onglet de la liste du prestataire : demandes déjà chargées et pagination (20 par page).
class EtatDemandes {
  const EtatDemandes({
    this.elements = const [],
    this.pagination,
    this.chargement = true,
    this.chargementSuite = false,
    this.erreur,
    this.erreurSuite,
  });

  final List<DemandeReservation> elements;
  final Pagination? pagination;

  /// Première page en cours de chargement et rien à montrer encore (squelettes).
  final bool chargement;
  final bool chargementSuite;
  final String? erreur;
  final String? erreurSuite;

  bool get encore => pagination?.encore ?? false;
}

/// Contrôleur d'un onglet. Garde les données tant que l'application tourne : changer d'onglet ne recharge pas.
class DemandesControleur extends Notifier<EtatDemandes> {
  DemandesControleur(this.onglet);

  final OngletDemandes onglet;
  int _generation = 0;

  @override
  EtatDemandes build() {
    Future.microtask(charger);
    return const EtatDemandes();
  }

  DepotPrestataire get _depot => ref.read(depotPrestataireProvider);

  /// Recharge la première page. Si des demandes sont déjà affichées elles restent à l'écran pendant le rechargement.
  Future<void> charger() async {
    final numero = ++_generation;
    final avait = state.elements.isNotEmpty;
    state = avait
        ? EtatDemandes(
            elements: state.elements,
            pagination: state.pagination,
            chargement: false,
          )
        : const EtatDemandes();
    try {
      final page = await _depot.demandes(onglet);
      if (!ref.mounted || numero != _generation) return;
      state = EtatDemandes(
        elements: page.elements,
        pagination: page.pagination,
        chargement: false,
      );
    } catch (e) {
      if (!ref.mounted || numero != _generation) return;
      state = EtatDemandes(
        elements: state.elements,
        pagination: state.pagination,
        chargement: false,
        erreur: avait ? null : ErreurApi.depuis(e).message,
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
    state = EtatDemandes(
      elements: state.elements,
      pagination: pagination,
      chargement: false,
      chargementSuite: true,
    );
    try {
      final page = await _depot.demandes(onglet, page: pagination.page + 1);
      if (!ref.mounted || numero != _generation) return;
      final connues = {for (final d in state.elements) d.id};
      state = EtatDemandes(
        elements: [
          ...state.elements,
          ...page.elements.where((d) => !connues.contains(d.id)),
        ],
        pagination: page.pagination,
        chargement: false,
      );
    } catch (e) {
      if (!ref.mounted || numero != _generation) return;
      state = EtatDemandes(
        elements: state.elements,
        pagination: pagination,
        chargement: false,
        erreurSuite: ErreurApi.depuis(e).message,
      );
    }
  }
}

final listeDemandesProvider =
    NotifierProvider.family<DemandesControleur, EtatDemandes, OngletDemandes>(
      DemandesControleur.new,
    );
