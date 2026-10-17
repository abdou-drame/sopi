import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'modeles.dart';

/// État du formulaire d'inscription sur 3 étapes. Il vit tant que l'application tourne (revenir en arrière ne
/// fait rien perdre) et n'est jamais écrit sur le disque, mot de passe compris.
class FormulaireInscription extends Notifier<DossierInscription> {
  @override
  DossierInscription build() => const DossierInscription();

  void mettreAJour(DossierInscription Function(DossierInscription) f) =>
      state = f(state);

  void definirFichier(TypeJustificatif type, FichierJustificatif fichier) =>
      state = state.copie(fichiers: {...state.fichiers, type: fichier});

  void retirerFichier(TypeJustificatif type) =>
      state = state.copie(fichiers: {...state.fichiers}..remove(type));

  /// Après un envoi réussi : plus rien ne doit rester en mémoire, surtout le mot de passe.
  void reinitialiser() => state = const DossierInscription();
}

final formulaireInscriptionProvider =
    NotifierProvider<FormulaireInscription, DossierInscription>(
      FormulaireInscription.new,
    );
