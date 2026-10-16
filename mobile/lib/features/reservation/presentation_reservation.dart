import '../../core/widgets/pastille_statut.dart';
import 'modeles.dart';

/// Teinte de la pastille de statut d'une réservation (maquettes Reservations et DetailReservation).
TonStatut tonDuStatut(StatutReservation statut) => switch (statut) {
  StatutReservation.enAttente => TonStatut.ardoise,
  StatutReservation.acceptee => TonStatut.indigo,
  StatutReservation.confirmee => TonStatut.info,
  StatutReservation.enCours => TonStatut.info,
  StatutReservation.terminee => TonStatut.succes,
  StatutReservation.annulee => TonStatut.erreur,
  StatutReservation.refusee => TonStatut.erreur,
};

/// Montant de l'acompte connu par la réservation elle-même (paiement créé), ou null.
num? acompteConnu(ReservationClient r) => r.paiement?.montantAcompte;
