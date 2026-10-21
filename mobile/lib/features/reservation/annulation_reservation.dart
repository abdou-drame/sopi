import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import 'depot_reservation.dart';
import 'modeles.dart';
import 'reservations_controleur.dart';

/// Message affiché une fois la réservation annulée.
const messageReservationAnnulee = 'Votre réservation a été annulée.';

/// Ouvre la feuille « Annuler cette réservation ? » (maquette Annulation, sans champ de motif : l'API n'en
/// prend pas). Si le client confirme et que l'API accepte, les listes et le détail sont relus et une
/// confirmation s'affiche. Renvoie vrai si la réservation a été annulée.
Future<bool> ouvrirAnnulation(
  BuildContext context,
  WidgetRef ref,
  ReservationClient reservation,
) async {
  if (!reservation.peutAnnuler) return false;
  final annulee = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    barrierColor: CouleursSopi.texte.withValues(alpha: .6),
    builder: (_) => _FeuilleAnnulation(reservation: reservation),
  );
  if (annulee != true) return false;
  rafraichirReservations(ref);
  ref.invalidate(detailReservationProvider(reservation.id));
  if (context.mounted) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text(messageReservationAnnulee)));
  }
  return true;
}

class _FeuilleAnnulation extends ConsumerStatefulWidget {
  const _FeuilleAnnulation({required this.reservation});

  final ReservationClient reservation;

  @override
  ConsumerState<_FeuilleAnnulation> createState() => _EtatFeuille();
}

class _EtatFeuille extends ConsumerState<_FeuilleAnnulation> {
  bool _enCours = false;
  String? _erreur;

  Future<void> _annuler() async {
    if (_enCours) return;
    setState(() {
      _enCours = true;
      _erreur = null;
    });
    try {
      await ref.read(depotReservationProvider).annuler(widget.reservation.id);
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _enCours = false;
        _erreur = ErreurApi.depuis(e).message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.reservation;
    final theme = Theme.of(context);
    final base = theme.textTheme.bodyMedium?.copyWith(
      fontSize: 13,
      height: 19 / 13,
      color: CouleursSopi.bleuProfond,
    );
    final acompte = r.paiement?.reussi == true
        ? r.paiement!.montantAcompte
        : null;
    return PopScope(
      canPop: !_enCours,
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          12,
          0,
          12,
          12 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SingleChildScrollView(
          child: Container(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
            decoration: BoxDecoration(
              color: CouleursSopi.blanc,
              borderRadius: BorderRadius.circular(36),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(
                      color: const Color(0xFFD5DDF0),
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  'Annuler cette réservation ?',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    height: 28 / 22,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${r.prestataireNom} · ${formaterDate(r.debut)}',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 14,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
                const SizedBox(height: 18),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                  decoration: BoxDecoration(
                    color: CouleursSopi.bleuPastel,
                    borderRadius: BorderRadius.circular(22),
                  ),
                  child: acompte == null
                      ? Text(
                          'Aucun paiement n\'a encore été effectué : l\'annulation est sans frais.',
                          style: base,
                        )
                      : Text.rich(
                          TextSpan(
                            style: base,
                            children: [
                              const TextSpan(
                                text:
                                    'Vous pouvez annuler tant que le délai d\'annulation n\'est pas dépassé. ',
                              ),
                              TextSpan(
                                text:
                                    'L\'acompte de ${formaterArgent(acompte)} ne sera pas remboursé.',
                                style: base?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const TextSpan(
                                text:
                                    ' Il l\'est seulement si c\'est le prestataire qui annule.',
                              ),
                            ],
                          ),
                        ),
                ),
                if (_erreur != null) ...[
                  const SizedBox(height: 14),
                  BandeauErreur(message: _erreur!),
                ],
                const SizedBox(height: 22),
                BoutonPrimaire(
                  libelle: 'Garder ma réservation',
                  onPressed: _enCours
                      ? null
                      : () => Navigator.of(context).pop(false),
                ),
                const SizedBox(height: 10),
                BoutonCompact(
                  libelle: 'Oui, annuler',
                  onPressed: _enCours ? null : _annuler,
                  ton: TonBoutonCompact.contour,
                  hauteur: 50,
                  taille: 15,
                  pleineLargeur: true,
                  chargement: _enCours,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
