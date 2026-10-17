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

/// Erreurs renvoyées par le serveur (422), par champ de l'API, en attente d'être affichées sous le bon champ
/// de l'étape concernée. Chaque page prend celles qui la concernent à son ouverture.
class ErreursServeurInscription extends Notifier<Map<String, String>> {
  @override
  Map<String, String> build() => const {};

  void definir(Map<String, String> erreurs) => state = erreurs;

  /// Retire et renvoie les erreurs dont la clé est dans [cles].
  Map<String, String> prendre(Set<String> cles) {
    final prises = {
      for (final e in state.entries)
        if (cles.contains(e.key)) e.key: e.value,
    };
    if (prises.isNotEmpty) {
      state = {
        for (final e in state.entries)
          if (!cles.contains(e.key)) e.key: e.value,
      };
    }
    return prises;
  }
}

final erreursServeurInscriptionProvider =
    NotifierProvider<ErreursServeurInscription, Map<String, String>>(
      ErreursServeurInscription.new,
    );

final formulaireInscriptionProvider =
    NotifierProvider<FormulaireInscription, DossierInscription>(
      FormulaireInscription.new,
    );
