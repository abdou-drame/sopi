/// Toutes les routes de l'application, déclarées en un seul endroit.
abstract final class Chemins {
  // — Technique
  static const demarrage = '/demarrage';
  static const etatApi = '/etat-api';

  // — Authentification (publiques)
  static const connexion = '/connexion';
  static const inscription = '/inscription';
  static const motDePasseOublie = '/mot-de-passe-oublie';
  static const inscriptionPrestataire = '/inscription-prestataire';
  static const inscriptionInformations =
      '/inscription-prestataire/informations';
  static const inscriptionJustificatifs =
      '/inscription-prestataire/justificatifs';
  static const suiviDossier = '/suivi-dossier';
  static const compteRestreint = '/compte-restreint';

  // — Client : onglets
  static const accueil = '/accueil';
  static const rechercher = '/rechercher';
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
  static String demandeEnvoyee(String id) => '/reservations/$id/envoyee';
  static String detailReservation(String id) => '/reservations/$id';
  static String avis(String id) => '/reservations/$id/avis';
  static String litige(String id) => '/reservations/$id/litige';
  static const signalements = '/profil/signalements';
  static String signalement(String id) => '/signalements/$id';
  static const aide = '/profil/aide';

  /// Écrans accessibles au client comme au prestataire.
  static const communs = {
    notifications,
    aide,
    profilModifier,
    motDePasseChanger,
  };
  static const profilModifier = '/profil/modifier';
  static const motDePasseChanger = '/profil/mot-de-passe';

  // — Prestataire : tout vit sous /prestataire
  static const racinePrestataire = '/prestataire';
  static const tableau = '/prestataire/tableau';
  static const agenda = '/prestataire/agenda';
  static const portefeuille = '/prestataire/portefeuille';
  static const profilPrestataire = '/prestataire/profil';
  static const services = '/prestataire/services';
  static const serviceNouveau = '/prestataire/services/nouveau';
  static String serviceModifier(String id) =>
      '/prestataire/services/$id/modifier';
  static const photos = '/prestataire/photos';
  static const disponibilites = '/prestataire/disponibilites';
  static const plageHoraire = '/prestataire/disponibilites/plage';
  static const indisponibilite = '/prestataire/disponibilites/indisponibilite';
  static const retrait = '/prestataire/retrait';
  static const avisRecus = '/prestataire/avis';
  static const litigesRecus = '/prestataire/litiges';
  static String litigeRecu(String id) => '/prestataire/litiges/$id';
  static const reservationsPrestataire = '/prestataire/reservations';
  static String refusReservation(String id) =>
      '/prestataire/reservations/$id/refus';
  static String reservationPrestataire(String id) =>
      '/prestataire/reservations/$id';
  static String annulationPrestataire(String id) =>
      '/prestataire/reservations/$id/annulation';

  /// Routes accessibles sans être connecté.
  static const publiques = <String>{
    demarrage,
    etatApi,
    connexion,
    inscription,
    motDePasseOublie,
    inscriptionPrestataire,
    inscriptionInformations,
    inscriptionJustificatifs,
    suiviDossier,
  };

  /// Routes d'authentification : un utilisateur connecté en est renvoyé vers son accueil.
  static const authentification = <String>{
    demarrage,
    connexion,
    inscription,
    motDePasseOublie,
    inscriptionPrestataire,
    inscriptionInformations,
    inscriptionJustificatifs,
    suiviDossier,
    compteRestreint,
  };
}
