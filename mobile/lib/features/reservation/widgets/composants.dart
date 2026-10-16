import 'package:flutter/material.dart';

import '../../../core/animation/apparition.dart';
import '../../../core/animation/chargement.dart';
import '../../../core/theme/couleurs.dart';
import '../../../core/widgets/boutons.dart';
import '../../../core/widgets/en_tete_degrade.dart';
import '../formats.dart';
import '../modeles.dart';

/// Icône d'un métier selon son code (le code vient de l'API ; un métier ajouté plus tard reçoit l'icône par défaut).
IconData iconeMetier(String code) => switch (code) {
  'mecanique' => Icons.build_rounded,
  'coiffure' => Icons.content_cut_rounded,
  'pressing' => Icons.checkroom_rounded,
  _ => Icons.storefront_rounded,
};

Color fondIconeMetier(String code) => switch (code) {
  'coiffure' => CouleursSopi.indigoPastel,
  'pressing' => CouleursSopi.cyanPastel,
  _ => CouleursSopi.bleuPastel,
};

/// Carré arrondi portant l'icône du métier (accueil, listes).
class PastilleMetier extends StatelessWidget {
  const PastilleMetier({
    super.key,
    required this.code,
    this.taille = 52,
    this.rayon = 16,
  });

  final String code;
  final double taille;
  final double rayon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: taille,
      height: taille,
      decoration: BoxDecoration(
        color: fondIconeMetier(code),
        borderRadius: BorderRadius.circular(rayon),
      ),
      child: Icon(iconeMetier(code), size: 24, color: CouleursSopi.bleu),
    );
  }
}

/// Étoile bleue + note (« 4,8 »), ou « Nouveau » tant qu'il n'y a aucun avis.
class NotePrestataire extends StatelessWidget {
  const NotePrestataire({
    super.key,
    required this.prestataire,
    this.taille = 13,
  });

  final PrestataireResume prestataire;
  final double taille;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.labelLarge?.copyWith(
      fontSize: taille,
      fontWeight: FontWeight.w700,
    );
    if (prestataire.estNouveau) {
      return Text('Nouveau', style: style?.copyWith(color: CouleursSopi.bleu));
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.star_rounded, size: taille + 1, color: CouleursSopi.bleu),
        const SizedBox(width: 4),
        Text(formaterNote(prestataire.noteMoyenne!), style: style),
      ],
    );
  }
}

/// Délai d'une apparition après la descente de l'en-tête, au rang [rang] de la cascade.
Duration apresEntete(int rang) =>
    DescenteDepuisLeHaut.duree + delaiCascade(rang);

/// Fait apparaître [child] après la descente de l'en-tête.
class ApparitionApresEntete extends StatelessWidget {
  const ApparitionApresEntete({
    super.key,
    required this.rang,
    required this.child,
  });

  final int rang;
  final Widget child;

  @override
  Widget build(BuildContext context) =>
      Apparition(delai: apresEntete(rang), child: child);
}

/// Espace à laisser sous une liste pour ne pas passer sous la barre d'onglets flottante.
double margeBarreOnglets(BuildContext context) =>
    MediaQuery.paddingOf(context).bottom + 68 + 12 + 24;

/// Titre de section (17 / 700) avec, à droite, un lien optionnel (« Tout voir »).
class TitreSection extends StatelessWidget {
  const TitreSection({super.key, required this.titre, this.lien, this.onLien});

  final String titre;
  final String? lien;
  final VoidCallback? onLien;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Text(
            titre,
            style: theme.textTheme.titleMedium?.copyWith(fontSize: 17),
          ),
        ),
        if (lien != null)
          InkWell(
            onTap: onLien,
            borderRadius: BorderRadius.circular(8),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
              child: Text(
                lien!,
                style: theme.textTheme.labelLarge?.copyWith(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: CouleursSopi.bleu,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

/// Squelette gris d'une carte de prestataire (liste ou accueil).
class SqueletteCartePrestataire extends StatelessWidget {
  const SqueletteCartePrestataire({super.key, this.compacte = false});

  final bool compacte;

  @override
  Widget build(BuildContext context) {
    final rayon = compacte ? 22.0 : 26.0;
    final pastille = compacte ? 52.0 : 56.0;
    return Container(
      padding: EdgeInsets.all(compacte ? 12 : 16),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(rayon),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Squelette(largeur: pastille, hauteur: pastille, rayon: 16),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Squelette(largeur: 140, hauteur: 14),
                    SizedBox(height: 8),
                    Squelette(largeur: 90, hauteur: 11),
                  ],
                ),
              ),
              const Squelette(largeur: 34, hauteur: 14),
            ],
          ),
          if (!compacte) ...[
            const SizedBox(height: 14),
            const Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Squelette(largeur: 130, hauteur: 13),
                Squelette(largeur: 86, hauteur: 40, rayon: 20),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Barre bleue du bas (64 px, coins 32) : petit libellé + valeur à gauche, bouton blanc à droite.
/// Sans [etiquette] ni [valeur], c'est [texteVide] qui occupe la gauche.
class BarreBasBleue extends StatelessWidget {
  const BarreBasBleue({
    super.key,
    required this.libelleBouton,
    required this.onPressed,
    this.etiquette,
    this.valeur,
    this.texteVide = '',
    this.chargement = false,
  });

  final String? etiquette;
  final String? valeur;
  final String texteVide;
  final String libelleBouton;
  final VoidCallback? onPressed;
  final bool chargement;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      height: 64,
      padding: const EdgeInsets.fromLTRB(22, 0, 10, 0),
      decoration: BoxDecoration(
        color: CouleursSopi.bleu,
        borderRadius: BorderRadius.circular(32),
      ),
      child: Row(
        children: [
          Expanded(
            child: valeur == null
                ? Text(
                    texteVide,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: CouleursSopi.blanc,
                    ),
                  )
                : Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (etiquette != null)
                        Text(
                          etiquette!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontSize: 11,
                            color: CouleursSopi.blanc.withValues(alpha: 0.85),
                          ),
                        ),
                      Text(
                        valeur!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: CouleursSopi.blanc,
                        ),
                      ),
                    ],
                  ),
          ),
          const SizedBox(width: 8),
          Flexible(
            flex: 2,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: BoutonCompact(
                libelle: libelleBouton,
                ton: TonBoutonCompact.clair,
                hauteur: 46,
                taille: 15,
                margeHorizontale: 24,
                chargement: chargement,
                onPressed: onPressed,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
