import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import 'depot_reservation.dart';
import 'modeles.dart';

/// Les trois onglets de « Mes réservations » et le groupe que l'API attend pour chacun.
enum GroupeReservations {
  aVenir('a_venir', 'À venir'),
  terminees('terminees', 'Terminées'),
  annulees('annulees', 'Annulées');

  const GroupeReservations(this.code, this.libelle);

  final String code;
  final String libelle;
}

/// État d'un onglet de « Mes réservations » : éléments déjà chargés et pagination.
class EtatReservations {
  const EtatReservations({
    this.elements = const [],
    this.pagination,
    this.chargement = true,
    this.chargementSuite = false,
    this.erreur,
    this.erreurSuite,
  });

  final List<ReservationClient> elements;
  final Pagination? pagination;

  /// Première page en cours de chargement et rien à montrer encore (squelettes).
  final bool chargement;
  final bool chargementSuite;
  final String? erreur;
  final String? erreurSuite;

  bool get encore => pagination?.encore ?? false;
}

/// Contrôleur d'un onglet. Garde les données tant que l'application tourne : changer d'onglet ne recharge pas.
class ReservationsControleur extends Notifier<EtatReservations> {
  ReservationsControleur(this.groupe);

  final GroupeReservations groupe;
  int _generation = 0;

  @override
  EtatReservations build() {
    Future.microtask(charger);
    return const EtatReservations();
  }

  DepotReservation get _depot => ref.read(depotReservationProvider);

  /// Recharge la première page. Si des éléments sont déjà affichés ils restent à l'écran pendant le rechargement.
  Future<void> charger() async {
    final numero = ++_generation;
    final avait = state.elements.isNotEmpty;
    state = avait
        ? EtatReservations(
            elements: state.elements,
            pagination: state.pagination,
            chargement: false,
          )
        : const EtatReservations();
    try {
      final page = await _depot.reservations(groupe.code);
      if (!ref.mounted || numero != _generation) return;
      state = EtatReservations(
        elements: page.elements,
        pagination: page.pagination,
        chargement: false,
      );
    } catch (e) {
      if (!ref.mounted || numero != _generation) return;
      state = EtatReservations(
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
    state = EtatReservations(
      elements: state.elements,
      pagination: pagination,
      chargement: false,
      chargementSuite: true,
    );
    try {
      final page = await _depot.reservations(
        groupe.code,
        page: pagination.page + 1,
      );
      if (!ref.mounted || numero != _generation) return;
      final connus = {for (final r in state.elements) r.id};
      state = EtatReservations(
        elements: [
          ...state.elements,
          ...page.elements.where((r) => !connus.contains(r.id)),
        ],
        pagination: page.pagination,
        chargement: false,
      );
    } catch (e) {
      if (!ref.mounted || numero != _generation) return;
      state = EtatReservations(
        elements: state.elements,
        pagination: pagination,
        chargement: false,
        erreurSuite: ErreurApi.depuis(e).message,
      );
    }
  }
}

final reservationsProvider =
    NotifierProvider.family<
      ReservationsControleur,
      EtatReservations,
      GroupeReservations
    >(ReservationsControleur.new);

/// Relit les trois onglets (après une annulation ou un paiement).
void rafraichirReservations(WidgetRef ref) {
  for (final g in GroupeReservations.values) {
    ref.invalidate(reservationsProvider(g));
  }
}
