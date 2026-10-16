/// Toutes les routes de l'application, déclarées en un seul endroit.
/// Les écrans « futurs » sont branchés sur un écran provisoire (voir `routeur.dart`) jusqu'à leur développement.
abstract final class Chemins {
  // — Technique
  static const demarrage = '/demarrage';
  static const etatApi = '/etat-api';

  // — Authentification (publiques)
  static const connexion = '/connexion';
  static const inscription = '/inscription';
  static const motDePasseOublie = '/mot-de-passe-oublie';
  static const reinitialisation = '/reinitialisation';
  static const inscriptionPrestataire = '/inscription-prestataire';
  static const suiviDossier = '/suivi-dossier';
  static const compteRestreint = '/compte-restreint';

  // — Client : onglets
  static const accueil = '/accueil';
  static const reservations = '/reservations';
  static const notifications = '/notifications';
  static const profil = '/profil';

  // — Client : parcours
  static const prestataires = '/prestataires';
  static String fichePrestataire(String id) => '/prestataires/$id';
  static String avisPrestataire(String id) => '/prestataires/$id/avis';
  static String creneau(String prestataireId) =>
      '/prestataires/$prestataireId/creneau';
  static String paiement(String reservationId) =>
      '/reservations/$reservationId/paiement';
  static const retourPaiement = '/paiement/retour';
  static String demandeEnvoyee(String id) => '/reservations/$id/envoyee';
  static String detailReservation(String id) => '/reservations/$id';
  static String annulation(String id) => '/reservations/$id/annulation';
  static String avis(String id) => '/reservations/$id/avis';
  static String litige(String id) => '/reservations/$id/litige';
  static const profilModifier = '/profil/modifier';
  static const motDePasseChanger = '/profil/mot-de-passe';

  // — Prestataire : tout vit sous /prestataire
  static const racinePrestataire = '/prestataire';
  static const tableau = '/prestataire/tableau';
  static const agenda = '/prestataire/agenda';
  static const portefeuille = '/prestataire/portefeuille';
  static const profilPrestataire = '/prestataire/profil';
  static const services = '/prestataire/services';
  static const disponibilites = '/prestataire/disponibilites';
  static const retrait = '/prestataire/retrait';
  static const historiqueRetraits = '/prestataire/retraits';
  static String demandeReservation(String id) => '/prestataire/demandes/$id';
  static String reservationPrestataire(String id) =>
      '/prestataire/reservations/$id';
  static String demarrerPrestation(String id) =>
      '/prestataire/reservations/$id/demarrer';

  /// Routes accessibles sans être connecté.
  static const publiques = <String>{
    demarrage,
    etatApi,
    connexion,
    inscription,
    motDePasseOublie,
    reinitialisation,
    inscriptionPrestataire,
    suiviDossier,
  };

  /// Routes d'authentification : un utilisateur connecté en est renvoyé vers son accueil.
  static const authentification = <String>{
    demarrage,
    connexion,
    inscription,
    motDePasseOublie,
    reinitialisation,
    inscriptionPrestataire,
    suiviDossier,
    compteRestreint,
  };
}
