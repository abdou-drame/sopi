import 'package:flutter/material.dart';

import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../auth/widgets/mise_en_page_acces.dart';

/// Mise en page des trois étapes de l'inscription prestataire (maquettes PrestaInscription1, PrestaCompte et
/// PrestaInscription2) : dégradé, retour, « Étape X sur 3 », trois segments de progression, titre en deux
/// lignes, contenu, bouton épinglé en bas.
class MiseEnPageEtape extends StatelessWidget {
  const MiseEnPageEtape({
    super.key,
    required this.etape,
    required this.titreLigne1,
    required this.titreLigne2,
    required this.contenu,
    required this.libelleBouton,
    required this.onContinuer,
    required this.onRetour,
    this.enCours = false,
    this.erreur,
  });

  /// 1, 2 ou 3.
  final int etape;
  final String titreLigne1;
  final String titreLigne2;
  final List<Widget> contenu;
  final String libelleBouton;
  final VoidCallback onContinuer;
  final VoidCallback onRetour;
  final bool enCours;
  final String? erreur;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bas = MediaQuery.viewInsetsOf(context).bottom;
    final haut = MediaQuery.paddingOf(context).top;
    return Scaffold(
      body: Stack(
        children: [
          const Positioned(
            left: 0,
            right: 0,
            top: 0,
            height: 230,
            child: DescenteDepuisLeHaut(
              child: ClipRRect(
                borderRadius: BorderRadius.vertical(
                  bottom: Radius.circular(DimensionsSopi.rayonEntete),
                ),
                child: FondDegrade(child: SizedBox.expand()),
              ),
            ),
          ),
          Column(
            children: [
              Expanded(
                child: ListView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: EdgeInsets.only(top: haut + 24, bottom: 16),
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          BoutonRetour(onPressed: enCours ? null : onRetour),
                          Text(
                            'Étape $etape sur 3',
                            style: theme.textTheme.labelMedium?.copyWith(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: CouleursSopi.texteSecondaire,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: Row(
                        children: [
                          for (var i = 1; i <= 3; i++) ...[
                            if (i > 1) const SizedBox(width: 6),
                            Expanded(
                              child: Container(
                                key: ValueKey('segment-$i'),
                                height: 6,
                                decoration: BoxDecoration(
                                  color: i <= etape
                                      ? CouleursSopi.bleu
                                      : CouleursSopi.enteteTacheIndigo,
                                  borderRadius: BorderRadius.circular(3),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 22),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: TitreDeuxLignes(
                        ligne1: titreLigne1,
                        ligne2: titreLigne2,
                        taille: 28,
                        interligne: 34,
                      ),
                    ),
                    const SizedBox(height: 34),
                    ...contenu,
                    if (erreur != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(
                          DimensionsSopi.margeCarte,
                          14,
                          DimensionsSopi.margeCarte,
                          0,
                        ),
                        child: BandeauErreur(message: erreur!),
                      ),
                  ],
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(
                  DimensionsSopi.margeCarte,
                  8,
                  DimensionsSopi.margeCarte,
                  bas > 0 ? 8 : 24,
                ),
                child: BoutonPrimaire(
                  libelle: libelleBouton,
                  chargement: enCours,
                  onPressed: enCours ? null : onContinuer,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Carte blanche des champs (rayon 30, marge 20, 12 px entre les champs).
class CarteEtape extends StatelessWidget {
  const CarteEtape({super.key, required this.enfants});

  final List<Widget> enfants;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: DimensionsSopi.margeCarte,
      ),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(
            DimensionsSopi.rayonCarteFormulaire,
          ),
          border: Border.all(color: CouleursSopi.bordure),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (var i = 0; i < enfants.length; i++) ...[
              if (i > 0) const SizedBox(height: 12),
              enfants[i],
            ],
          ],
        ),
      ),
    );
  }
}
