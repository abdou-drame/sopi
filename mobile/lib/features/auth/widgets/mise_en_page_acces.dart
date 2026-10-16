import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/animation/apparition.dart';
import '../../../core/routing/chemins.dart';
import '../../../core/theme/couleurs.dart';
import '../../../core/theme/dimensions.dart';
import '../../../core/widgets/en_tete_degrade.dart';

/// Retour d'un écran d'accès : dépile si possible, sinon va à la connexion.
void retourOuConnexion(BuildContext context) {
  if (context.canPop()) {
    context.pop();
  } else {
    context.go(Chemins.connexion);
  }
}

/// Délai d'une apparition en cascade, après la descente de l'en-tête.
Duration delaiApresEntete(int rang) =>
    DescenteDepuisLeHaut.duree + delaiCascade(rang);

/// Fait apparaître [child] en fondu et glissement, après la descente de l'en-tête, au rang [rang].
class ApparitionAcces extends StatelessWidget {
  const ApparitionAcces({super.key, required this.rang, required this.child});

  final int rang;
  final Widget child;

  @override
  Widget build(BuildContext context) =>
      Apparition(delai: delaiApresEntete(rang), child: child);
}

/// Mise en page commune des écrans d'accès. Les maquettes sont à positions absolues (390 × 844) ; ici
/// l'écran défile, le pied reste collé en bas quand il y a de la place, et le clavier ne cache jamais un champ.
class EcranAcces extends StatelessWidget {
  const EcranAcces({
    super.key,
    required this.entete,
    required this.corps,
    this.pied,
    this.chevauchement = 0,
    this.margePied = 24,
  });

  /// En-tête dégradé (descend du haut).
  final Widget entete;

  /// Éléments sous l'en-tête, avec leurs espacements. Le premier (la carte) remonte de [chevauchement] sur l'en-tête.
  final List<Widget> corps;

  /// Bloc collé en bas de l'écran (ou à la suite du contenu si l'écran est trop petit).
  final Widget? pied;

  /// Recouvrement de la première carte sur l'en-tête, en pixels (le décalage restant est à retirer de l'espace suivant).
  final double chevauchement;

  final double margePied;

  @override
  Widget build(BuildContext context) {
    final bas = MediaQuery.paddingOf(context).bottom;
    return CustomScrollView(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      slivers: [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              entete,
              for (var i = 0; i < corps.length; i++)
                i == 0 && chevauchement != 0
                    ? Transform.translate(
                        offset: Offset(0, -chevauchement),
                        child: corps[i],
                      )
                    : corps[i],
              const Spacer(),
              if (pied != null)
                Padding(
                  padding: EdgeInsets.fromLTRB(
                    DimensionsSopi.margeCarte,
                    16,
                    DimensionsSopi.margeCarte,
                    margePied + bas,
                  ),
                  child: pied,
                ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Titre en deux lignes : la première en 400, la seconde en 800.
class TitreDeuxLignes extends StatelessWidget {
  const TitreDeuxLignes({
    super.key,
    required this.ligne1,
    required this.ligne2,
    required this.taille,
    required this.interligne,
    this.centre = false,
  });

  final String ligne1;
  final String ligne2;
  final double taille;
  final double interligne;
  final bool centre;

  @override
  Widget build(BuildContext context) {
    final base = Theme.of(context).textTheme.headlineMedium!.copyWith(
      fontSize: taille,
      height: interligne / taille,
      color: CouleursSopi.marine,
    );
    return Text.rich(
      TextSpan(
        children: [
          TextSpan(
            text: '$ligne1\n',
            style: const TextStyle(fontWeight: FontWeight.w400),
          ),
          TextSpan(
            text: ligne2,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ],
      ),
      style: base,
      textAlign: centre ? TextAlign.center : TextAlign.start,
    );
  }
}

/// En-tête des écrans à formulaire (Inscription, MotDePasse) : retour, texte à droite facultatif, titre en deux
/// lignes (30/36) et un texte facultatif dessous. [basEntete] est l'espace sous le dernier élément.
class EnteteFormulaire extends StatelessWidget {
  const EnteteFormulaire({
    super.key,
    required this.titre1,
    required this.titre2,
    required this.basEntete,
    this.droite,
    this.sousTitre,
  });

  final String titre1;
  final String titre2;
  final double basEntete;
  final String? droite;
  final String? sousTitre;

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
            padding: EdgeInsets.fromLTRB(
              DimensionsSopi.margeEcran,
              haut + 12,
              DimensionsSopi.margeEcran,
              basEntete,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Apparition(
                  delai: DescenteDepuisLeHaut.duree,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      BoutonRetour(onPressed: () => retourOuConnexion(context)),
                      if (droite != null)
                        Text(
                          droite!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontSize: 13,
                            color: CouleursSopi.texteSecondaire,
                            fontWeight: FontWeight.w400,
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Apparition(
                    delai: delaiApresEntete(1),
                    child: TitreDeuxLignes(
                      ligne1: titre1,
                      ligne2: titre2,
                      taille: 30,
                      interligne: 36,
                    ),
                  ),
                ),
                if (sousTitre != null) ...[
                  const SizedBox(height: 18),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Apparition(
                      delai: delaiApresEntete(2),
                      child: Text(
                        sousTitre!,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 14,
                          height: 20 / 14,
                          fontWeight: FontWeight.w400,
                          color: CouleursSopi.texteSecondaire,
                        ),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Carte blanche du formulaire : coins 30, bordure fine, marge intérieure 22/20/24, 16 px de chaque côté de l'écran.
class CarteFormulaire extends StatelessWidget {
  const CarteFormulaire({super.key, required this.children, this.espace = 14});

  final List<Widget> children;
  final double espace;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: DimensionsSopi.margeCarte,
      ),
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 22, 20, 24),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(
            DimensionsSopi.rayonCarteFormulaire,
          ),
          border: Border.all(color: CouleursSopi.bordure),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            for (var i = 0; i < children.length; i++) ...[
              if (i > 0) SizedBox(height: espace),
              children[i],
            ],
          ],
        ),
      ),
    );
  }
}

/// Lien-texte en gras bleu (« Mot de passe oublié ? », « Créer un compte »…), zone tactile d'au moins 44 px.
class LienTexte extends StatelessWidget {
  const LienTexte({
    super.key,
    required this.texte,
    required this.onTap,
    this.taille = 14,
    this.poids = FontWeight.w700,
  });

  final String texte;
  final VoidCallback? onTap;
  final double taille;
  final FontWeight poids;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 44),
        child: Center(
          widthFactor: 1,
          child: Text(
            texte,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontSize: taille,
              fontWeight: poids,
              color: CouleursSopi.bleu,
            ),
          ),
        ),
      ),
    );
  }
}
