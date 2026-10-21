import 'package:flutter/material.dart';

import '../../../core/animation/chargement.dart';
import '../../../core/format/formateurs.dart';
import '../../../core/theme/couleurs.dart';
import '../../../core/theme/disposition.dart';
import '../../../core/widgets/en_tete_ecran.dart';
import '../modeles.dart';

/// En-tête des écrans Prestation et PrestaTerminer : titre centré, pastille de statut, client, prestation et date.
class EnTetePrestation extends StatelessWidget {
  const EnTetePrestation({
    super.key,
    required this.titre,
    required this.demande,
    required this.pastille,
    required this.onRetour,
  });

  final String titre;
  final DemandeReservation? demande;
  final Widget pastille;
  final VoidCallback onRetour;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final d = demande;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, hautEcran(context), 20, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              BoutonRetour(onPressed: onRetour),
              Expanded(
                child: Text(
                  titre,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 64),
            ],
          ),
          const SizedBox(height: 24),
          if (d != null) ...[
            Align(alignment: Alignment.centerLeft, child: pastille),
            const SizedBox(height: 10),
            Text(
              d.clientAffiche,
              style: theme.textTheme.headlineMedium?.copyWith(
                fontSize: 28,
                height: 34 / 28,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${d.prestationNom} · ${formaterDate(d.debut)}',
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 14,
                color: CouleursSopi.texteSecondaire,
              ),
            ),
          ] else
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Squelette(largeur: 200, hauteur: 28),
            ),
        ],
      ),
    );
  }
}

/// Pastille pleine « Confirmée » (maquette Prestation : fond bleu, texte blanc).
class PastilleConfirmee extends StatelessWidget {
  const PastilleConfirmee({super.key});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
    decoration: BoxDecoration(
      color: CouleursSopi.bleu,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Text(
      'Confirmée',
      style: Theme.of(context).textTheme.labelMedium?.copyWith(
        color: CouleursSopi.blanc,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

/// Carte des montants : tout vient de l'API. Seul « Reste à encaisser » est une soustraction d'affichage
/// (tarif − acompte), comme dans le détail d'une demande.
class CarteMontantsPrestation extends StatelessWidget {
  const CarteMontantsPrestation({
    super.key,
    required this.demande,
    required this.libelleCredit,
  });

  final DemandeReservation demande;
  final String libelleCredit;

  @override
  Widget build(BuildContext context) {
    final d = demande;
    final theme = Theme.of(context);
    Widget ligne(
      String libelle,
      String valeur, {
      Color? couleur,
      FontWeight poids = FontWeight.w700,
    }) => Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            libelle,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Text(
          valeur,
          textAlign: TextAlign.right,
          style: theme.textTheme.bodyMedium?.copyWith(
            fontSize: 14,
            fontWeight: poids,
            color: couleur ?? CouleursSopi.texte,
          ),
        ),
      ],
    );

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(26),
        boxShadow: OmbresSopi.carte,
      ),
      child: Column(
        children: [
          ligne('Tarif du service', formaterArgent(d.prix)),
          const SizedBox(height: 10),
          ligne(
            'Acompte reçu',
            formaterArgent(d.acompte!),
            couleur: CouleursSopi.bleu,
          ),
          const SizedBox(height: 10),
          ligne(
            'Reste à encaisser sur place',
            formaterArgent(d.prix - d.acompte!),
          ),
          const SizedBox(height: 10),
          Container(height: 1, color: CouleursSopi.separateur),
          const SizedBox(height: 10),
          ligne('Commission Sopi', '− ${formaterArgent(d.commission!)}'),
          const SizedBox(height: 10),
          ligne(
            libelleCredit,
            formaterArgent(d.montantCredite!),
            poids: FontWeight.w800,
          ),
        ],
      ),
    );
  }
}
