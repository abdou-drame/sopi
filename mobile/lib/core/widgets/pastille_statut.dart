import 'package:flutter/material.dart';

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
      TonStatut.neutre => (CouleursSopi.bordure, CouleursSopi.texteSecondaire),
      TonStatut.indigo => (CouleursSopi.indigoPastel, CouleursSopi.indigoTexte),
      TonStatut.ardoise => (
        CouleursSopi.ardoisePastel,
        CouleursSopi.ardoiseTexte,
      ),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        libelle,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: texte,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
