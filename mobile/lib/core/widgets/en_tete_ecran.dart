import 'package:flutter/material.dart';

import '../animation/apparition.dart';
import '../theme/couleurs.dart';
import '../theme/dimensions.dart';
import '../theme/disposition.dart';

/// Bouton rond du haut (maquettes) : 44×44, blanc, ombre légère, icône #12172b.
class BoutonRond extends StatelessWidget {
  const BoutonRond({
    super.key,
    required this.icone,
    required this.libelle,
    this.onPressed,
    this.fond = CouleursSopi.blanc,
    this.couleurIcone = CouleursSopi.texte,
    this.avecOmbre = true,
  });

  final IconData icone;

  /// Lu par les lecteurs d'écran.
  final String libelle;
  final VoidCallback? onPressed;
  final Color fond;
  final Color couleurIcone;
  final bool avecOmbre;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: libelle,
      excludeSemantics: true,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onPressed,
        child: Container(
          width: DimensionsSopi.tailleBoutonRond,
          height: DimensionsSopi.tailleBoutonRond,
          decoration: BoxDecoration(
            color: fond,
            shape: BoxShape.circle,
            boxShadow: avecOmbre ? OmbresSopi.boutonRond : null,
          ),
          child: Icon(icone, size: 22, color: couleurIcone),
        ),
      ),
    );
  }
}

/// Bouton retour : bouton rond du haut avec chevron.
class BoutonRetour extends StatelessWidget {
  const BoutonRetour({super.key, this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => BoutonRond(
    icone: Icons.chevron_left_rounded,
    libelle: 'Retour',
    onPressed: onPressed ?? () => Navigator.of(context).maybePop(),
  );
}

/// Fond d'écran uni #f5f6fa (aucun dégradé).
class FondDegrade extends StatelessWidget {
  const FondDegrade({super.key, this.child, this.tacheBasse = false});

  final Widget? child;
  final bool tacheBasse;

  @override
  Widget build(BuildContext context) =>
      ColoredBox(color: CouleursSopi.fond, child: child);
}

/// Ancien effet de descente de l'en-tête, supprimé : les maquettes sont statiques.
class DescenteDepuisLeHaut extends StatelessWidget {
  const DescenteDepuisLeHaut({super.key, required this.child});

  final Widget child;

  static const duree = Duration.zero;

  @override
  Widget build(BuildContext context) => child;
}

/// En-tête d'écran sur fond uni : bouton retour rond, titre en deux lignes (400 puis 800), contenu optionnel dessous.
class EnTeteEcran extends StatelessWidget {
  const EnTeteEcran({
    super.key,
    required this.titreLigne1,
    this.titreLigne2,
    this.avecRetour = true,
    this.onRetour,
    this.action,
    this.centre,
    this.enfant,
    this.ecartEnfant = 18,
    this.margeBasse = 24,
    this.tailleTitre = DimensionsSopi.tailleTitreEcran,
    this.interligneTitre = DimensionsSopi.interligneTitreEcran,
  });

  final String titreLigne1;
  final String? titreLigne2;
  final bool avecRetour;
  final VoidCallback? onRetour;

  /// Widget à droite du bouton retour (par exemple une pastille).
  final Widget? action;

  /// Texte centré entre le bouton retour et le bord droit (maquettes Creneau, Prestataires…).
  final Widget? centre;

  /// Contenu sous le titre (champ de recherche, onglets…).
  final Widget? enfant;

  /// Espace entre le titre et [enfant], puis sous l'en-tête.
  final double ecartEnfant;
  final double margeBasse;

  final double tailleTitre;
  final double interligneTitre;

  static const dureeDescente = Duration.zero;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        0,
        // Sans bouton retour, le titre monte à 40 dp (maquettes Reservations, Profil…).
        avecRetour || action != null || centre != null
            ? hautEcran(context)
            : hautEcran(context) + 16,
        0,
        margeBasse,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (avecRetour || action != null || centre != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  if (avecRetour)
                    BoutonRetour(onPressed: onRetour)
                  else
                    const SizedBox.shrink(),
                  if (centre != null) ...[
                    Expanded(child: Center(child: centre)),
                    const SizedBox(width: 64),
                  ],
                  ?action,
                ],
              ),
            ),
          if (avecRetour || action != null || centre != null)
            const SizedBox(height: 24),
          Apparition(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    titreLigne1,
                    style: theme.textTheme.headlineMedium?.copyWith(
                      fontSize: tailleTitre,
                      fontWeight: FontWeight.w400,
                      height: interligneTitre / tailleTitre,
                    ),
                  ),
                  if (titreLigne2 != null)
                    Text(
                      titreLigne2!,
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontSize: tailleTitre,
                        fontWeight: FontWeight.w800,
                        height: interligneTitre / tailleTitre,
                      ),
                    ),
                ],
              ),
            ),
          ),
          if (enfant != null) ...[
            SizedBox(height: ecartEnfant),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: enfant!,
            ),
          ],
        ],
      ),
    );
  }
}
