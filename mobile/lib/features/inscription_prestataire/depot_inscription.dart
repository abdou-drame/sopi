import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import '../../core/api_client.dart';
import '../auth/validateurs.dart';
import 'modeles.dart';

/// Requêtes publiques de l'inscription prestataire (aucune règle métier côté mobile : le serveur revalide tout).
class DepotInscriptionPrestataire {
  DepotInscriptionPrestataire(this._dio);

  final Dio _dio;

  /// POST /prestataires/inscription en multipart. Renvoie le jeton de suivi (donné une seule fois par le serveur).
  Future<String> inscrire(DossierInscription d) async {
    final formulaire = FormData();
    void champ(String cle, String valeur) =>
        formulaire.fields.add(MapEntry(cle, valeur));

    champ('nom', d.nom.trim());
    champ('prenom', d.prenom.trim());
    champ(
      'telephone',
      ValidateursAcces.telephoneApi(d.telephone) ?? d.telephone.trim(),
    );
    champ('email', d.email.trim());
    champ('mot_de_passe', d.motDePasse);
    champ('mot_de_passe_confirmation', d.confirmation);
    champ('nom_entreprise', d.nomEntreprise.trim());
    champ('metier', d.metierCode ?? '');
    champ('quartier', d.quartierNettoye);
    champ('adresse', d.adresse.trim());

    var i = 0;
    for (final type in TypeJustificatif.values) {
      final fichier = d.fichiers[type];
      if (fichier == null) continue;
      champ('justificatifs[$i][type_document]', type.valeurApi);
      formulaire.files.add(
        MapEntry(
          'justificatifs[$i][fichier]',
          MultipartFile.fromBytes(
            fichier.octets,
            filename: fichier.nom,
            contentType: DioMediaType.parse(fichier.typeMime),
          ),
        ),
      );
      i++;
    }

    final reponse = await _dio.post<Object?>(
      '/prestataires/inscription',
      data: formulaire,
    );
    final corps = reponse.data is Map ? reponse.data as Map : const {};
    final jeton = corps['jeton_suivi'];
    if (jeton is! String || jeton.isEmpty) throw const ErreurInconnue();
    return jeton;
  }

  /// GET /suivi/{jeton} (public).
  Future<SuiviDossier> suivi(String jeton) async {
    final reponse = await _dio.get<Object?>(
      '/suivi/${Uri.encodeComponent(jeton)}',
    );
    return SuiviDossier.depuisJson(reponse.data);
  }
}

final depotInscriptionProvider = Provider<DepotInscriptionPrestataire>(
  (ref) => DepotInscriptionPrestataire(ref.watch(dioProvider)),
);
