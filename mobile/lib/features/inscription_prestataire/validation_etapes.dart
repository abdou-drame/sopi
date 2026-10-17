import '../auth/validateurs.dart';
import 'modeles.dart';

/// Validation locale des étapes 1 et 2 (le serveur revalide tout). Une clé absente = champ valide.
abstract final class ValidationEtapes {
  /// [saisieLibre] : la liste des quartiers est indisponible, le quartier est donc forcément saisi à la main.
  static Map<String, String> etape1(
    DossierInscription d, {
    bool saisieLibre = false,
  }) {
    final erreurs = <String, String>{};
    final entreprise = d.nomEntreprise.trim();
    if (entreprise.isEmpty) {
      erreurs['nom_entreprise'] = "Le nom de l'entreprise est obligatoire.";
    } else if (entreprise.length > 150) {
      erreurs['nom_entreprise'] =
          "Le nom de l'entreprise ne doit pas dépasser 150 caractères.";
    }
    if (d.metierCode == null) {
      erreurs['metier'] = 'Choisissez votre métier.';
    }
    final quartier = d.quartierNettoye;
    if (d.quartierAutre || saisieLibre) {
      if (quartier.isEmpty) {
        erreurs['quartier'] = 'Indiquez votre quartier.';
      } else if (quartier.length < 2) {
        erreurs['quartier'] =
            'Le quartier doit contenir au moins 2 caractères.';
      } else if (quartier.length > 100) {
        erreurs['quartier'] =
            'Le quartier ne doit pas dépasser 100 caractères.';
      }
    } else if (quartier.isEmpty) {
      erreurs['quartier'] = 'Choisissez votre quartier.';
    }
    final adresse = d.adresse.trim();
    if (adresse.isEmpty) {
      erreurs['adresse'] = "L'adresse est obligatoire.";
    } else if (adresse.length > 255) {
      erreurs['adresse'] = "L'adresse ne doit pas dépasser 255 caractères.";
    }
    return erreurs;
  }

  static Map<String, String> etape2(DossierInscription d) {
    final erreurs = <String, String>{};
    void ajouter(String cle, String? message) {
      if (message != null) erreurs[cle] = message;
    }

    ajouter('prenom', ValidateursAcces.nom(d.prenom, 'Le prénom'));
    ajouter('nom', ValidateursAcces.nom(d.nom, 'Le nom'));
    ajouter('telephone', ValidateursAcces.telephone(d.telephone));
    ajouter('email', ValidateursAcces.emailObligatoire(d.email));
    ajouter('mot_de_passe', ValidateursAcces.motDePasse(d.motDePasse));
    ajouter(
      'mot_de_passe_confirmation',
      ValidateursAcces.confirmation(d.motDePasse, d.confirmation),
    );
    return erreurs;
  }
}
