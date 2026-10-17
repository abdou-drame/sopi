import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import 'demandes_controleur.dart';
import 'depot_prestataire.dart';

/// Relit tout ce qu'une réponse du prestataire change : compteur et cartes du tableau, listes, détail.
/// Les réponses d'action ne contiennent pas les montants : on relit toujours depuis l'API.
void rafraichirDemandes(WidgetRef ref, [String? id]) {
  ref.invalidate(demandesATraiterProvider);
  ref.invalidate(listeDemandesProvider);
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

/// Refuse une demande avec son motif (3 à 500 caractères, déjà vérifiés par l'écran). Renvoie vrai si le serveur l'a refusée.
Future<bool> refuserDemande(
  BuildContext context,
  WidgetRef ref,
  String id,
  String motif,
) async {
  final messager = ScaffoldMessenger.of(context);
  try {
    await ref.read(depotPrestataireProvider).refuser(id, motif);
    rafraichirDemandes(ref, id);
    messager.showSnackBar(const SnackBar(content: Text('Demande refusée.')));
    return true;
  } catch (e) {
    rafraichirDemandes(ref, id);
    messager.showSnackBar(SnackBar(content: Text(ErreurApi.depuis(e).message)));
    return false;
  }
}

/// Annule une réservation acceptée ou confirmée avec son motif (3 à 500 caractères, déjà vérifiés par l'écran).
/// Le client est remboursé par le serveur. Renvoie vrai si le serveur l'a annulée.
Future<bool> annulerReservation(
  BuildContext context,
  WidgetRef ref,
  String id,
  String motif,
) async {
  final messager = ScaffoldMessenger.of(context);
  try {
    await ref.read(depotPrestataireProvider).annuler(id, motif);
    rafraichirDemandes(ref, id);
    messager.showSnackBar(
      const SnackBar(
        content: Text('Réservation annulée. Le client est remboursé.'),
      ),
    );
    return true;
  } catch (e) {
    rafraichirDemandes(ref, id);
    messager.showSnackBar(SnackBar(content: Text(ErreurApi.depuis(e).message)));
    return false;
  }
}
