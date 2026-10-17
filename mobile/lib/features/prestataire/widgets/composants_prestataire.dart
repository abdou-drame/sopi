import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/formateurs.dart';
import '../../../core/routing/chemins.dart';
import '../../../core/theme/couleurs.dart';
import '../../../core/theme/dimensions.dart';
import '../../../core/widgets/boutons.dart';
import '../../reservation/depot_reservation.dart' show horlogeProvider;
import '../actions_demande.dart';
import '../formats_prestataire.dart';
import '../modeles.dart';

/// Bouton marine de l'en-tête (menu), maquette Tableau : 64 × 44, icône blanche.
class PilulePleine extends StatelessWidget {
  const PilulePleine({
    super.key,
    required this.libelle,
    required this.icone,
    required this.onTap,
  });

  final String libelle;
  final IconData icone;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: libelle,
      child: Material(
        color: CouleursSopi.marine,
        borderRadius: BorderRadius.circular(22),
        child: InkWell(
          borderRadius: BorderRadius.circular(22),
          onTap: onTap,
          child: SizedBox(
            width: DimensionsSopi.largeurRetour,
            height: DimensionsSopi.hauteurRetour,
            child: Icon(icone, size: 22, color: CouleursSopi.blanc),
          ),
        ),
      ),
    );
  }
}

/// Rond blanc de l'en-tête (cloche) avec, si besoin, le petit point bleu des notifications non lues.
class RondBlanc extends StatelessWidget {
  const RondBlanc({
    super.key,
    required this.libelle,
    required this.icone,
    required this.onTap,
    this.pastille = false,
  });

  final String libelle;
  final IconData icone;
  final VoidCallback onTap;
  final bool pastille;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: libelle,
      child: Material(
        color: CouleursSopi.blanc,
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: SizedBox(
            width: 44,
            height: 44,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Icon(icone, size: 20, color: CouleursSopi.marine),
                if (pastille)
                  Positioned(
                    top: 11,
                    right: 12,
                    child: Container(
                      key: const ValueKey('pastille-notifications'),
                      width: 9,
                      height: 9,
                      decoration: BoxDecoration(
                        color: CouleursSopi.bleu,
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: CouleursSopi.blanc,
                          width: 1.5,
                        ),
                      ),
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

/// « Aminata D. » + « Sam. 24 oct., 10h00 » puis « Coupe femme · 5 000 FCFA ».
class LignesDemande extends StatelessWidget {
  const LignesDemande({super.key, required this.demande});

  final DemandeReservation demande;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                demande.clientAffiche,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Text(
              formaterDate(demande.debut),
              style: theme.textTheme.bodySmall?.copyWith(
                fontSize: 12,
                color: CouleursSopi.texteDiscret,
              ),
            ),
          ],
        ),
        const SizedBox(height: 2),
        Text(
          '${demande.prestationNom} · ${formaterArgent(demande.prix)}',
          style: theme.textTheme.bodyMedium?.copyWith(
            fontSize: 13,
            color: CouleursSopi.texteDiscret,
          ),
        ),
      ],
    );
  }
}

/// Accepter / Refuser (hauteur 42). Accepter appelle l'API ; Refuser ouvre l'écran du motif.
class BoutonsReponse extends ConsumerStatefulWidget {
  const BoutonsReponse({
    super.key,
    required this.demande,
    this.compact = false,
  });

  final DemandeReservation demande;

  /// Variante de la liste (maquette PrestaReservations) : boutons de 38 px à leur taille, Refuser avant Accepter.
  final bool compact;

  @override
  ConsumerState<BoutonsReponse> createState() => _BoutonsReponseState();
}

class _BoutonsReponseState extends ConsumerState<BoutonsReponse> {
  bool _enCours = false;

  void _refuser() => context.push(Chemins.refusReservation(widget.demande.id));

  Future<void> _accepter() async {
    setState(() => _enCours = true);
    await accepterDemande(context, ref, widget.demande.id);
    if (mounted) setState(() => _enCours = false);
  }

  @override
  Widget build(BuildContext context) {
    if (widget.compact) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          BoutonCompact(
            libelle: 'Refuser',
            ton: TonBoutonCompact.contour,
            hauteur: 38,
            margeHorizontale: 16,
            onPressed: _enCours ? null : _refuser,
          ),
          const SizedBox(width: 8),
          BoutonCompact(
            libelle: 'Accepter',
            hauteur: 38,
            margeHorizontale: 16,
            chargement: _enCours,
            onPressed: _enCours ? null : _accepter,
          ),
        ],
      );
    }
    return Row(
      children: [
        Expanded(
          child: BoutonCompact(
            libelle: 'Accepter',
            hauteur: 42,
            pleineLargeur: true,
            chargement: _enCours,
            onPressed: _enCours ? null : _accepter,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: BoutonCompact(
            libelle: 'Refuser',
            ton: TonBoutonCompact.contour,
            hauteur: 42,
            pleineLargeur: true,
            onPressed: _enCours ? null : _refuser,
          ),
        ),
      ],
    );
  }
}

/// Carte d'une nouvelle demande du tableau : toute la carte ouvre le détail, les boutons répondent.
class CarteNouvelleDemande extends StatelessWidget {
  const CarteNouvelleDemande({super.key, required this.demande});

  final DemandeReservation demande;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(DimensionsSopi.rayonCarte),
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(DimensionsSopi.rayonCarte),
        onTap: () => context.push(Chemins.reservationPrestataire(demande.id)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
          child: Column(
            children: [
              LignesDemande(demande: demande),
              const SizedBox(height: 12),
              BoutonsReponse(demande: demande),
            ],
          ),
        ),
      ),
    );
  }
}

/// « Il reste 11 h 20 » (ambre) : échéance de réponse moins l'heure du téléphone, relue chaque 30 secondes.
class ResteAvantReponse extends ConsumerStatefulWidget {
  const ResteAvantReponse({
    super.key,
    required this.echeance,
    this.taille = 12,
  });

  final DateTime echeance;
  final double taille;

  @override
  ConsumerState<ResteAvantReponse> createState() => _ResteAvantReponseState();
}

class _ResteAvantReponseState extends ConsumerState<ResteAvantReponse> {
  Timer? _minuteur;

  @override
  void initState() {
    super.initState();
    _minuteur = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final maintenant = ref.watch(horlogeProvider)();
    return Text(
      formaterReste(widget.echeance.difference(maintenant)),
      style: Theme.of(context).textTheme.labelMedium?.copyWith(
        fontSize: widget.taille,
        fontWeight: FontWeight.w600,
        color: CouleursSopi.ambre,
      ),
    );
  }
}
