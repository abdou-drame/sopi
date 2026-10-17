import 'package:flutter/material.dart';

import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';

/// Mise en page commune des formulaires du profil : dégradé, retour, titre en deux lignes, carte blanche
/// et bouton « Enregistrer » épinglé en bas (maquettes ModifierProfil et ChangerMotDePasse).
class MiseEnPageFormulaire extends StatelessWidget {
  const MiseEnPageFormulaire({
    super.key,
    required this.titreLigne1,
    required this.titreLigne2,
    required this.champs,
    required this.onEnregistrer,
    this.enCours = false,
    this.erreur,
    this.succes,
    this.libelleBouton = 'Enregistrer',
  });

  final String titreLigne1;
  final String titreLigne2;
  final List<Widget> champs;
  final VoidCallback onEnregistrer;
  final bool enCours;
  final String? erreur;
  final String? succes;
  final String libelleBouton;

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
                  padding: EdgeInsets.fromLTRB(
                    DimensionsSopi.margeCarte,
                    haut + 24,
                    DimensionsSopi.margeCarte,
                    DimensionsSopi.espace,
                  ),
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: BoutonRetour(
                          onPressed: enCours
                              ? null
                              : () => Navigator.of(context).maybePop(),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      child: Text.rich(
                        TextSpan(
                          style: theme.textTheme.headlineMedium?.copyWith(
                            fontSize: 30,
                            height: 36 / 30,
                            fontWeight: FontWeight.w400,
                          ),
                          children: [
                            TextSpan(text: '$titreLigne1\n'),
                            TextSpan(
                              text: titreLigne2,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: CouleursSopi.blanc,
                        borderRadius: BorderRadius.circular(30),
                        border: Border.all(color: CouleursSopi.bordure),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          for (var i = 0; i < champs.length; i++) ...[
                            if (i > 0) const SizedBox(height: 14),
                            champs[i],
                          ],
                        ],
                      ),
                    ),
                    if (erreur != null) ...[
                      const SizedBox(height: 14),
                      BandeauErreur(message: erreur!),
                    ],
                    if (succes != null) ...[
                      const SizedBox(height: 14),
                      BandeauSucces(message: succes!),
                    ],
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
                  onPressed: enCours ? null : onEnregistrer,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
