import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import 'depot_prestataire.dart';

/// Relit tout ce qu'une réponse du prestataire change : compteur et cartes du tableau, listes, détail.
/// Les réponses d'action ne contiennent pas les montants : on relit toujours depuis l'API.
void rafraichirDemandes(WidgetRef ref, [String? id]) {
  ref.invalidate(demandesATraiterProvider);
  if (id != null) ref.invalidate(detailDemandeProvider(id));
}

/// Accepte une demande. Renvoie vrai si le serveur l'a acceptée ; sinon affiche son message (en français) et relit les données.
Future<bool> accepterDemande(
  BuildContext context,
  WidgetRef ref,
  String id,
) async {
  final messager = ScaffoldMessenger.of(context);
  try {
    await ref.read(depotPrestataireProvider).accepter(id);
    rafraichirDemandes(ref, id);
    messager.showSnackBar(const SnackBar(content: Text('Demande acceptée.')));
    return true;
  } catch (e) {
    // Un refus du serveur (409 : déjà traitée, expirée…) change l'état de la demande : on relit.
    rafraichirDemandes(ref, id);
    messager.showSnackBar(SnackBar(content: Text(ErreurApi.depuis(e).message)));
    return false;
  }
}
