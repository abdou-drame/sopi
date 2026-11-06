import 'package:flutter/material.dart';

import '../animation/apparition.dart';
import '../theme/couleurs.dart';

enum TonStatut { info, succes, erreur, neutre, indigo, ardoise }

/// Petite pastille arrondie portant un statut (« Confirmée », « En attente »…).
class PastilleStatut extends StatelessWidget {
  const PastilleStatut({
    super.key,
    required this.libelle,
    this.ton = TonStatut.info,
  });

  final String libelle;
  final TonStatut ton;

  @override
  Widget build(BuildContext context) {
    final (fond, texte) = switch (ton) {
      TonStatut.info => (CouleursSopi.bleuPastel, CouleursSopi.bleu),
      TonStatut.succes => (CouleursSopi.succesFond, CouleursSopi.succesTexte),
      TonStatut.erreur => (CouleursSopi.erreurFond, CouleursSopi.erreurTexte),
      TonStatut.neutre => (
        CouleursSopi.fondSecondaire,
        CouleursSopi.texteSecondaire,
      ),
      TonStatut.indigo => (CouleursSopi.indigoPastel, CouleursSopi.indigoTexte),
      TonStatut.ardoise => (
        CouleursSopi.ardoisePastel,
        CouleursSopi.ardoiseTexte,
      ),
    };
    // Quand le statut change, le fond et le texte fondent d'une couleur à l'autre.
    final duree = animationsActives(context)
        ? const Duration(milliseconds: 400)
        : Duration.zero;
    return AnimatedContainer(
      duration: duree,
      height: 26,
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(13),
      ),
      child: AnimatedDefaultTextStyle(
        duration: duree,
        style: Theme.of(context).textTheme.labelMedium!.copyWith(
          color: texte,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
        child: Text(libelle),
      ),
    );
  }
}
