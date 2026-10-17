import 'package:flutter/material.dart';

import '../../../core/api/erreurs.dart';
import '../../../core/theme/couleurs.dart';
import '../../../core/theme/dimensions.dart';

/// Premier message du serveur pour un champ, que la clé soit `debut` ou `plages.0.heure_debut`.
String? messageServeur(ErreurValidation erreur, String champ) {
  for (final entree in erreur.champs.entries) {
    if (entree.key == champ || entree.key.endsWith('.$champ')) {
      if (entree.value.isNotEmpty) return entree.value.first;
    }
  }
  return null;
}

/// Message rouge sous un champ (même style que [ChampFormulaire]).
class MessageChamp extends StatelessWidget {
  const MessageChamp(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(left: 6, top: 6),
    child: Text(
      message,
      style: Theme.of(context).textTheme.bodySmall?.copyWith(
        color: CouleursSopi.erreurTexte,
        fontWeight: FontWeight.w600,
      ),
    ),
  );
}

/// Champ qui s'ouvre sur un sélecteur au toucher (maquette PlageHoraire, « Jour ») : libellé 12/600, hauteur 50, 15 px.
class ChampSelecteur extends StatelessWidget {
  const ChampSelecteur({
    super.key,
    required this.libelle,
    required this.valeur,
    required this.onTap,
    this.erreur,
  });

  final String libelle;
  final String valeur;
  final VoidCallback onTap;
  final String? erreur;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final enErreur = erreur != null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          libelle,
          style: theme.textTheme.labelMedium?.copyWith(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: CouleursSopi.texteSecondaire,
          ),
        ),
        const SizedBox(height: 6),
        Semantics(
          button: true,
          label: '$libelle : $valeur',
          excludeSemantics: true,
          child: Material(
            color: CouleursSopi.champ,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(DimensionsSopi.rayonChamp),
              side: BorderSide(
                color: enErreur
                    ? CouleursSopi.erreurTexte
                    : CouleursSopi.bordureForte,
              ),
            ),
            child: InkWell(
              customBorder: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(DimensionsSopi.rayonChamp),
              ),
              onTap: onTap,
              child: Container(
                height: 50,
                alignment: Alignment.centerLeft,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Text(
                  valeur,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    fontSize: 15,
                    color: CouleursSopi.marine,
                  ),
                ),
              ),
            ),
          ),
        ),
        if (erreur != null) MessageChamp(erreur!),
      ],
    );
  }
}
