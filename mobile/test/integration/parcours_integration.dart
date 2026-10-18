// Tests d'intégration : le VRAI client HTTP et les VRAIS dépôts de l'application contre un backend Laravel local jetable.
//
// Ce fichier n'est PAS dans la suite normale (son nom ne finit pas par `_test.dart`). Pour le lancer :
//   bash test/integration/lancer_backend_local.sh 8765        (dans un premier terminal)
//   flutter test test/integration/parcours_integration.dart --dart-define=API_URL=http://127.0.0.1:8765/api/v1 -r expanded
//
// Les parcours s'enchaînent (le suivant réutilise les comptes et réservations du précédent) : un échec en amont fait
// échouer les suivants, ce qui est signalé dans le rapport.
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/api/erreurs.dart';
import 'package:sopi/core/api/client_api.dart';
import 'package:sopi/features/auth/session.dart';
import 'package:sopi/features/compte/depot_compte.dart';
import 'package:sopi/features/compte/modeles_litige.dart';
import 'package:sopi/features/inscription_prestataire/depot_inscription.dart';
import 'package:sopi/features/inscription_prestataire/modeles.dart';
import 'package:sopi/features/prestataire/depot_prestataire.dart';
import 'package:sopi/features/prestataire/modeles.dart';
import 'package:sopi/features/prestataire/modeles_portefeuille.dart';
import 'package:sopi/features/reservation/depot_reservation.dart';
import 'package:sopi/features/reservation/modeles.dart';

import 'harnais.dart';

const motDePasseClient = 'MotDePasse-Client1';
const motDePasseAtelier = 'MotDePasse-Atelier1';

late Acteur client;
late Acteur atelier; // prestataire dont le dossier est accepté
late ClientBrut admin;

late String idPrestataire;
late String idPrestation;
late String jetonSuivi;
late String jetonSuiviRefuse;

/// Réservations du scénario, une par issue.
late String resTerminee;
late String resAnnuleeClient;
late String resAnnuleePresta;
late String resRefusee;

late String codeDemarrage;

DepotReservation get depotClient => client.lire(depotReservationProvider);
DepotPrestataire get depotAtelier => atelier.lire(depotPrestataireProvider);
DepotCompte get compteClient => client.lire(depotCompteProvider);

/// Crée un créneau d'une heure à [jours] jours, à l'heure [heure], et renvoie son identifiant côté client.
Future<String> creerCreneau(int jours, int heure) async {
  final h = heure.toString().padLeft(2, '0');
  final jour = jourDans(jours);
  await depotAtelier.creerPlage(
    PlageHoraire(
      jour: jour,
      heureDebut: '$h:00',
      heureFin: '${(heure + 1).toString().padLeft(2, '0')}:00',
      dureeCreneauMinutes: 60,
    ),
  );

  final libres = await depotClient.agenda(idPrestataire, du: jour, au: jour);
  final voulu = libres.firstWhere(
    (c) => c.debut.toUtc().hour == heure,
    orElse: () =>
        throw StateError('Créneau $jour $h:00 introuvable dans l’agenda.'),
  );
  return voulu.id;
}

/// Réserve un créneau jusqu'à l'état EN_ATTENTE et renvoie l'identifiant de la réservation.
Future<String> demander(int jours, int heure) async {
  final creneau = await creerCreneau(jours, heure);
  await depotClient.verrouiller(creneau);
  final r = await depotClient.demander(
    creneauId: creneau,
    prestationId: idPrestation,
  );
  expect(r.statut, 'en_attente');
  return r.id;
}

/// Le prestataire accepte, le client « paie » : le faux webhook signé confirme le paiement. Renvoie le code.
Future<String?> accepterEtPayer(String idRes) async {
  final acceptee = await depotAtelier.accepter(idRes);
  expect(acceptee.statut, StatutReservation.acceptee);
  final session = await depotClient.payer(idRes);
  await confirmerPaiementLocal(
    reference: referenceDepuisUrl(session.urlPaiement),
    montant: session.montantAcompte,
  );
  final confirmee = await depotClient.reservation(idRes);
  expect(confirmee.statut, StatutReservation.confirmee);
  return confirmee.codeReservation;
}

/// Statut brut renvoyé par le serveur, pour détecter un statut que le mobile ne reconnaît pas.
Future<String> statutBrut(Dio dio, String chemin) async {
  final r = await dio.get<Object?>(chemin);
  final corps = r.data as Map;
  final j = corps['data'] is Map ? corps['data'] as Map : corps;
  return '${j['statut']}';
}

void main() {
  if (!integrationActive) {
    test('Intégration ignorée : API_URL (127.0.0.1) non fournie', () {
      markTestSkipped(
        'Lancez lancer_backend_local.sh puis --dart-define=API_URL=http://127.0.0.1:8765/api/v1',
      );
    });
    return;
  }

  setUpAll(() async {
    client = Acteur();
    atelier = Acteur();
    await viderLimites();
    admin = await ClientBrut.admin();
    // Laisse la restauration de session initiale se terminer.
    await Future<void>.delayed(const Duration(milliseconds: 200));
  });

  tearDownAll(() {
    client.fermer();
    atelier.fermer();
  });

  group('P01 Référentiels publics', () {
    test('P01a métiers', () async {
      final metiers = await depotClient.metiers();
      expect(
        metiers.map((m) => m.code),
        containsAll(['coiffure', 'mecanique', 'pressing']),
      );
    });

    test('P01b quartiers : liste de recherche puis liste complète', () async {
      final recherche = await depotClient.quartiers();
      final tous = await depotClient.quartiers(tous: true);
      expect(recherche, isEmpty, reason: 'aucun prestataire actif encore');
      expect(tous, containsAll(['Keur Massar Nord', 'Keur Massar Sud']));
    });
  });

  group('P02 Compte client', () {
    test('P02a inscription client (session ouverte)', () async {
      await client.session.inscrire({
        'nom': 'Ndiaye',
        'prenom': 'Awa',
        'telephone': telephoneUnique(1),
        'email': 'awa.client@sopi.invalid',
        'password': motDePasseClient,
      });
      expect(client.etat, isA<Connecte>());
      expect((client.etat as Connecte).utilisateur.prenom, 'Awa');
    });

    test(
      'P02b inscription : doublon de téléphone refusé par champ (422)',
      () async {
        final autre = Acteur();
        addTearDown(autre.fermer);
        final e = await erreurDe(
          () => autre.session.inscrire({
            'nom': 'Ndiaye',
            'prenom': 'Awa',
            'telephone': telephoneUnique(1),
            'password': motDePasseClient,
          }),
        );
        expect(e, isA<ErreurValidation>());
        expect((e as ErreurValidation).premierMessage('telephone'), isNotNull);
      },
    );

    test('P02c déconnexion puis connexion par e-mail', () async {
      await client.session.deconnecter();
      expect(client.etat, isA<NonConnecte>());
      expect(await client.stockage.lire(), isNull);

      await client.session.connecter(
        'awa.client@sopi.invalid',
        motDePasseClient,
      );
      expect(client.etat, isA<Connecte>());
    });

    test(
      'P02d connexion : mauvais mot de passe (message français, pas de session)',
      () async {
        final autre = Acteur();
        addTearDown(autre.fermer);
        final e = await erreurDe(
          () => autre.session.connecter(
            'awa.client@sopi.invalid',
            'mauvais-mot-de-passe',
          ),
        );
        expect(e.message, isNotEmpty);
        expect(autre.etat, isNot(isA<Connecte>()));
      },
    );

    test('P02e mot de passe oublié : envoi de la demande', () async {
      final r = await client.dio.post<Object?>(
        cheminMotDePasseOublie,
        data: {'email': 'awa.client@sopi.invalid'},
      );
      expect(r.statusCode, 200);
      expect((r.data as Map)['message'], isA<String>());
    });
  });

  group('P03 Inscription prestataire et suivi du dossier', () {
    DossierInscription dossier(String nom, String email, int n) =>
        DossierInscription(
          nomEntreprise: nom,
          metierCode: 'coiffure',
          quartier: 'Keur Massar Nord',
          adresse: 'Rue 10, près du marché',
          prenom: 'Fatou',
          nom: 'Diop',
          telephone: telephoneUnique(n),
          email: email,
          motDePasse: motDePasseAtelier,
          confirmation: motDePasseAtelier,
          fichiers: {
            TypeJustificatif.cni: FichierJustificatif(
              nom: 'cni.png',
              octets: petitPng,
              typeMime: 'image/png',
            ),
            TypeJustificatif.diplome: FichierJustificatif(
              nom: 'diplome.pdf',
              octets: petitPdf,
              typeMime: 'application/pdf',
            ),
          },
        );

    test(
      'P03a inscription avec justificatifs puis suivi « en attente »',
      () async {
        final depot = Acteur().lire(depotInscriptionProvider);
        jetonSuivi = await depot.inscrire(
          dossier('Salon Awa', 'salon.awa@sopi.invalid', 2),
        );
        final suivi = await depot.suivi(jetonSuivi);
        expect(suivi.statutCompte, 'en_attente');
        expect(suivi.nomEntreprise, 'Salon Awa');
        expect(suivi.justificatifs, hasLength(2));
      },
    );

    test('P03b inscription sans justificatif : 422 par champ', () async {
      final depot = Acteur().lire(depotInscriptionProvider);
      final sans = DossierInscription(
        nomEntreprise: 'Salon Vide',
        metierCode: 'coiffure',
        quartier: 'Keur Massar Nord',
        adresse: 'Rue 1',
        prenom: 'A',
        nom: 'B',
        telephone: telephoneUnique(3),
        email: 'vide@sopi.invalid',
        motDePasse: motDePasseAtelier,
        confirmation: motDePasseAtelier,
      );
      final e = await erreurDe(() => depot.inscrire(sans));
      expect(e, isA<ErreurValidation>());
    });

    test(
      'P03c dossier accepté par l’administrateur local : suivi « actif »',
      () async {
        final liste = await admin.dio.get<Object?>(
          '/admin/prestataires/demandes',
        );
        final demande = ((liste.data as Map)['data'] as List)
            .cast<Map>()
            .firstWhere((d) => d['nomEntreprise'] == 'Salon Awa');
        idPrestataire = '${demande['id']}';
        await admin.dio.post<Object?>(
          '/admin/prestataires/$idPrestataire/accepter',
        );

        final suivi = await Acteur()
            .lire(depotInscriptionProvider)
            .suivi(jetonSuivi);
        expect(suivi.statutCompte, 'actif');
      },
    );

    test('P03d dossier refusé avec motif : suivi « refusé » + motif', () async {
      final depot = Acteur().lire(depotInscriptionProvider);
      jetonSuiviRefuse = await depot.inscrire(
        dossier('Salon Refusé', 'refuse@sopi.invalid', 4),
      );
      final liste = await admin.dio.get<Object?>(
        '/admin/prestataires/demandes',
      );
      final demande = ((liste.data as Map)['data'] as List)
          .cast<Map>()
          .firstWhere((d) => d['nomEntreprise'] == 'Salon Refusé');
      await admin.dio.post<Object?>(
        '/admin/prestataires/${demande['id']}/refuser',
        data: {'motif': 'Pièce d’identité illisible, merci de la renvoyer.'},
      );
      final suivi = await depot.suivi(jetonSuiviRefuse);
      expect(suivi.statutCompte, 'refuse');
      expect(suivi.motifRefus, contains('illisible'));
    });

    test('P03e suivi : jeton inconnu = 404', () async {
      final e = await erreurDe(
        () => Acteur().lire(depotInscriptionProvider).suivi('jeton-inconnu'),
      );
      expect(e, _serveur(404));
    });
  });

  group('P04 Espace prestataire', () {
    test('P04a connexion du prestataire accepté', () async {
      await atelier.session.connecter(
        'salon.awa@sopi.invalid',
        motDePasseAtelier,
      );
      expect(atelier.etat, isA<Connecte>());
    });

    test('P04b services : ajout, modification, désactivation', () async {
      final cree = await depotAtelier.creerPrestation(
        nom: 'Coupe homme',
        dureeMinutes: 60,
        prix: 5000,
      );
      idPrestation = cree.id;
      expect(cree.prix, 5000);

      final modif = await depotAtelier.modifierPrestation(
        cree.id,
        prix: 6000,
        nom: 'Coupe homme soignée',
      );
      expect(modif.prix, 6000);

      final autre = await depotAtelier.creerPrestation(
        nom: 'Tresses',
        dureeMinutes: 120,
        prix: 15000,
      );
      final desactivee = await depotAtelier.modifierPrestation(
        autre.id,
        actif: false,
      );
      expect(desactivee.actif, isFalse);

      final toutes = await depotAtelier.prestations();
      expect(
        toutes.map((p) => p.nom),
        containsAll(['Coupe homme soignée', 'Tresses']),
      );
    });

    test('P04c service : prix invalide = 422 par champ', () async {
      final e = await erreurDe(
        () => depotAtelier.creerPrestation(nom: 'X', dureeMinutes: 60, prix: 0),
      );
      expect(e, isA<ErreurValidation>());
    });

    test('P04d photos : ajout, lecture des octets, suppression', () async {
      await depotAtelier.ajouterPhoto(
        nom: 'atelier.png',
        octets: petitPng,
        typeMime: 'image/png',
      );
      final ids = await depotAtelier.photos();
      expect(ids, hasLength(1));
      final octets = await depotAtelier.photo(ids.first);
      expect(octets, isNotEmpty);
      await depotAtelier.supprimerPhoto(ids.first);
      expect(await depotAtelier.photos(), isEmpty);
    });

    test('P04e créneaux : ajout puis retrait d’un créneau libre', () async {
      final jour = jourDans(20);
      final n = await depotAtelier.creerPlage(
        PlageHoraire(
          jour: jour,
          heureDebut: '14:00',
          heureFin: '16:00',
          dureeCreneauMinutes: 60,
        ),
      );
      expect(n, 2);
      final agenda = await depotAtelier.agenda(jour, jour);
      expect(agenda, hasLength(2));
      await depotAtelier.supprimerCreneau(agenda.first.id);
      expect(await depotAtelier.agenda(jour, jour), hasLength(1));
    });

    test('P04f indisponibilité : bilan du serveur', () async {
      final jour = jourDans(25);
      await depotAtelier.creerPlage(
        PlageHoraire(
          jour: jour,
          heureDebut: '09:00',
          heureFin: '10:00',
          dureeCreneauMinutes: 60,
        ),
      );
      final bilan = await depotAtelier.declarerIndisponibilite(
        debut: '$jour 08:00',
        fin: '$jour 18:00',
      );
      expect(bilan.creneauxBloques, greaterThanOrEqualTo(1));
      expect(bilan.reservationsAnnulees, 0);
    });
  });

  group('P05 Découverte côté client', () {
    test('P05a recherche : filtres, tri et pagination', () async {
      final base = await depotClient.prestataires(metier: 'coiffure');
      expect(base.elements.map((p) => p.id), contains(idPrestataire));
      expect(base.pagination.page, 1);

      final quartier = await depotClient.prestataires(
        metier: 'coiffure',
        filtres: const FiltresPrestataires(
          quartier: 'Keur Massar Nord',
          tri: TriPrestataires.prixAsc,
        ),
      );
      expect(quartier.elements, isNotEmpty);

      final texte = await depotClient.prestataires(
        filtres: const FiltresPrestataires(recherche: 'Salon Awa'),
      );
      expect(texte.elements.map((p) => p.id), contains(idPrestataire));

      final vide = await depotClient.prestataires(
        filtres: const FiltresPrestataires(recherche: 'zzzintrouvablezzz'),
      );
      expect(vide.elements, isEmpty);

      final prix = await depotClient.prestataires(
        filtres: const FiltresPrestataires(prixMin: 1, prixMax: 100000),
      );
      expect(prix.elements, isNotEmpty);

      // Aucun avis encore : un filtre de note minimale exclut le prestataire.
      final note = await depotClient.prestataires(
        filtres: const FiltresPrestataires(noteMin: 4),
      );
      expect(note.elements, isEmpty);

      final page2 = await depotClient.prestataires(page: 2);
      expect(page2.pagination.page, 2);
    });

    test(
      'P05b quartiers de recherche : le quartier du prestataire actif apparaît',
      () async {
        expect(await depotClient.quartiers(), contains('Keur Massar Nord'));
      },
    );

    test('P05c fiche prestataire et acompte donné par l’API', () async {
      final profil = await depotClient.profil(idPrestataire);
      expect(profil.nomEntreprise, 'Salon Awa');
      expect(profil.prestations.map((p) => p.id), contains(idPrestation));
      final acompte = await depotClient.acomptePrestation(
        idPrestataire,
        idPrestation,
      );
      expect(acompte, isNotNull);
      expect(
        acompte!.montant,
        1800,
        reason: '30 % de 6 000 (taux vient du serveur)',
      );
    });

    test('P05d avis d’un prestataire (aucun encore)', () async {
      final avis = await depotClient.avis(idPrestataire);
      expect(avis.elements, isEmpty);
    });

    test('P05e fiche d’un prestataire inconnu = 404', () async {
      final e = await erreurDe(
        () => depotClient.profil('00000000-0000-0000-0000-000000000000'),
      );
      expect(e, _serveur(404));
    });
  });

  group('P06 Réservation jusqu’au démarrage', () {
    test('P06a verrou puis libération, puis nouveau verrou', () async {
      final creneau = await creerCreneau(3, 8);
      final verrou = await depotClient.verrouiller(creneau);
      expect(verrou.creneauId, creneau);
      expect(verrou.expireA.isAfter(DateTime.now().toUtc()), isTrue);
      await depotClient.liberer(creneau);
      await depotClient.verrouiller(creneau);
      await depotClient.liberer(creneau);
    });

    test(
      'P06b réservation → acceptation → paiement (faux webhook) → code',
      () async {
        resTerminee = await demander(3, 9);
        final enAttente = await depotClient.reservation(resTerminee);
        expect(enAttente.statut, StatutReservation.enAttente);
        expect(
          await statutBrut(client.dio, '/reservations/$resTerminee'),
          'en_attente',
        );

        final demandes = await depotAtelier.demandes(OngletDemandes.aTraiter);
        expect(demandes.elements.map((d) => d.id), contains(resTerminee));
        final detail = await depotAtelier.demande(resTerminee);
        expect(detail.clientInitiale, isNotEmpty);

        codeDemarrage = (await accepterEtPayer(resTerminee))!;
        expect(codeDemarrage, matches(RegExp(r'^[A-Z0-9]{6}$')));
      },
    );

    test('P06c doublon de réservation sur un créneau pris = 409/422', () async {
      final creneau = await creerCreneau(3, 10);
      await depotClient.verrouiller(creneau);
      await depotClient.demander(
        creneauId: creneau,
        prestationId: idPrestation,
      );
      final e = await erreurDe(
        () => depotClient.demander(
          creneauId: creneau,
          prestationId: idPrestation,
        ),
      );
      expect(e.message, isNotEmpty);
    });

    test('P06d démarrage : mauvais code = 422', () async {
      await viderLimites();
      final e = await erreurDe(
        () => depotAtelier.demarrer(resTerminee, 'ZZZZZZ'),
      );
      expect(e, isA<ErreurValidation>());
    });

    test('P06e démarrage : trop d’essais = 429', () async {
      await viderLimites();
      ErreurApi? derniere;
      for (var i = 0; i < 8; i++) {
        derniere = await erreurDe(
          () => depotAtelier.demarrer(resTerminee, 'ZZZZZ$i'),
        );
        if (derniere is ErreurTropDeRequetes) break;
      }
      expect(derniere, isA<ErreurTropDeRequetes>());
    });

    test('P06f démarrage avec le bon code', () async {
      await viderLimites();
      final d = await depotAtelier.demarrer(resTerminee, codeDemarrage);
      expect(d.statut, StatutReservation.enCours);
    });
  });

  group('P07 Terminer, portefeuille, retrait', () {
    test(
      'P07a terminer : crédit = acompte − commission (montants du serveur)',
      () async {
        final credit = await depotAtelier.terminer(resTerminee);
        expect(credit.reservation.statut, StatutReservation.terminee);
        expect(credit.montantTotal, 6000);
        expect(credit.commission, 600);
        expect(credit.montantCredite, 1200);
      },
    );

    test('P07b solde et mouvements', () async {
      final solde = await depotAtelier.solde();
      expect(solde.solde, 1200);
      expect(solde.minimum, 1000);
      expect(solde.maximum, 500000);
      final mouv = await depotAtelier.mouvements();
      expect(mouv.elements, isNotEmpty);
    });

    test(
      'P07c retrait trop bas = 422 ; retrait valide (faux versement local)',
      () async {
        await viderLimites();
        final trop = await erreurDe(
          () => depotAtelier.demanderRetrait(
            montant: 500,
            telephone: '+221771234567',
            operateur: OperateurRetrait.wave,
          ),
        );
        expect(trop, isA<ErreurValidation>());

        final retrait = await depotAtelier.demanderRetrait(
          montant: 1000,
          telephone: '+221771234567',
          operateur: OperateurRetrait.wave,
        );
        expect(retrait.montant, 1000);
        expect(
          retrait.statut,
          anyOf(StatutRetrait.enCours, StatutRetrait.reussi),
        );
        expect((await depotAtelier.solde()).solde, lessThanOrEqualTo(200));
      },
    );

    test('P07d retrait supérieur au solde = 422', () async {
      final e = await erreurDe(
        () => depotAtelier.demanderRetrait(
          montant: 400000,
          telephone: '+221771234567',
          operateur: OperateurRetrait.orangeMoney,
        ),
      );
      expect(e, isA<ErreurValidation>());
    });
  });

  group('P08 Après la prestation', () {
    test('P08a avis du client, puis double avis refusé', () async {
      await depotClient.publierAvis(
        resTerminee,
        note: 5,
        commentaire: 'Très bon accueil et travail soigné.',
      );
      final e = await erreurDe(
        () => depotClient.publierAvis(resTerminee, note: 4),
      );
      expect(e.message, isNotEmpty);
    });

    test('P08b avis visibles côté client et côté prestataire', () async {
      final publics = await depotClient.avis(idPrestataire);
      expect(publics.elements, hasLength(1));
      final recus = await depotAtelier.avisRecus();
      expect(recus.elements, hasLength(1));
      expect(recus.noteMoyenne, 5);
    });

    test('P08c litige avec photo, puis réponse du prestataire', () async {
      await viderLimites();
      await compteClient.ouvrirLitige(
        resTerminee,
        motif: 'Prestation non conforme à la demande',
        description:
            'La coupe ne correspondait pas du tout à ce qui avait été convenu avant le rendez-vous.',
        preuves: [
          PhotoChoisie(
            nom: 'preuve.png',
            octets: petitPng,
            typeMime: 'image/png',
          ),
        ],
      );
      final liste = await compteClient.litiges(1);
      expect(liste.elements, hasLength(1));
      final litigeId = liste.elements.first.id;
      final detail = await compteClient.litige(litigeId);
      expect(detail.id, litigeId);

      final recus = await depotAtelier.litiges();
      expect(recus.elements, hasLength(1));
      await depotAtelier.repondreLitige(
        recus.elements.first.id,
        'La coupe réalisée correspondait à la demande faite sur place.',
      );
      final e = await erreurDe(
        () => depotAtelier.repondreLitige(
          recus.elements.first.id,
          'Seconde réponse qui doit être refusée par le serveur.',
        ),
      );
      expect(e, _serveur(409));
    });
  });

  group('P09 Annulations et refus', () {
    test('P09a annulation par le client (acompte non remboursé)', () async {
      resAnnuleeClient = await demander(4, 9);
      await accepterEtPayer(resAnnuleeClient);
      final avant = await depotClient.reservation(resAnnuleeClient);
      expect(avant.peutAnnuler, isTrue);
      await depotClient.annuler(resAnnuleeClient);
      final apres = await depotClient.reservation(resAnnuleeClient);
      expect(apres.statut, StatutReservation.annulee);
    });

    test(
      'P09b annulation par le prestataire avec motif (remboursement)',
      () async {
        resAnnuleePresta = await demander(4, 10);
        await accepterEtPayer(resAnnuleePresta);
        final d = await depotAtelier.annuler(
          resAnnuleePresta,
          'Empêchement de dernière minute.',
        );
        expect(d.statut, StatutReservation.annulee);
        final cote = await depotClient.reservation(resAnnuleePresta);
        expect(cote.statut, StatutReservation.annulee);
      },
    );

    test('P09c annulation par le prestataire sans motif = 422', () async {
      final e = await erreurDe(
        () => depotAtelier.annuler(resAnnuleePresta, ''),
      );
      expect(e, anyOf(isA<ErreurValidation>(), _serveur(409)));
    });

    test('P09d refus avec motif', () async {
      resRefusee = await demander(4, 11);
      final d = await depotAtelier.refuser(
        resRefusee,
        'Je ne suis pas disponible à cette heure-là.',
      );
      expect(d.statut, StatutReservation.refusee);
      final cote = await depotClient.reservation(resRefusee);
      expect(cote.statut, StatutReservation.refusee);
      expect(cote.motifRefus, isNotEmpty);
    });

    test('P09e listes « Mes réservations » par groupe', () async {
      final terminees = await depotClient.reservations('terminees');
      expect(terminees.elements.map((r) => r.id), contains(resTerminee));
      final annulees = await depotClient.reservations('annulees');
      expect(
        annulees.elements.map((r) => r.id),
        containsAll([resAnnuleeClient, resAnnuleePresta]),
      );
      final aVenir = await depotClient.reservations('a_venir');
      expect(aVenir.pagination.page, 1);
      final prochaine = await depotClient.prochaineReservation();
      expect(prochaine == null || prochaine.id.isNotEmpty, isTrue);
    });

    test('P09f listes côté prestataire', () async {
      final terminees = await depotAtelier.demandes(OngletDemandes.terminees);
      expect(terminees.elements.map((d) => d.id), contains(resTerminee));
      final annulees = await depotAtelier.demandes(OngletDemandes.annulees);
      expect(annulees.elements, isNotEmpty);
    });
  });

  group('P10 Notifications', () {
    test('P10a client : liste, compteur, lecture, tout lire', () async {
      final page = await compteClient.notifications(1);
      expect(page.elements, isNotEmpty);
      final avant = await depotClient.notificationsNonLues();
      expect(avant, greaterThan(0));
      await compteClient.marquerLue(page.elements.first.id);
      expect(await depotClient.notificationsNonLues(), avant - 1);
      await compteClient.toutMarquerLu();
      expect(await depotClient.notificationsNonLues(), 0);
    });

    test('P10b prestataire : liste, compteur, tout lire', () async {
      final c = atelier.lire(depotCompteProvider);
      final page = await c.notifications(1);
      expect(page.elements, isNotEmpty);
      expect(
        await atelier.lire(depotReservationProvider).notificationsNonLues(),
        greaterThan(0),
      );
      await c.toutMarquerLu();
      expect(
        await atelier.lire(depotReservationProvider).notificationsNonLues(),
        0,
      );
    });
  });

  group('P11 Profil', () {
    test('P11a modification du profil', () async {
      final u = await compteClient.modifierProfil(
        prenom: 'Awa',
        nom: 'Ndiaye-Sow',
        telephone: telephoneUnique(1),
      );
      expect(u.nom, 'Ndiaye-Sow');
    });

    test(
      'P11b changement de mot de passe : mauvais actuel puis succès',
      () async {
        await viderLimites();
        final e = await erreurDe(
          () => compteClient.changerMotDePasse(
            actuel: 'incorrect-incorrect',
            nouveau: 'NouveauMotDePasse-2',
            confirmation: 'NouveauMotDePasse-2',
          ),
        );
        expect(e, isA<ErreurValidation>());

        await compteClient.changerMotDePasse(
          actuel: motDePasseClient,
          nouveau: 'NouveauMotDePasse-2',
          confirmation: 'NouveauMotDePasse-2',
        );
        await client.session.deconnecter();
        await client.session.connecter(
          'awa.client@sopi.invalid',
          'NouveauMotDePasse-2',
        );
        expect(client.etat, isA<Connecte>());
      },
    );

    test(
      'P11c session expirée : un jeton révoqué ramène à « non connecté »',
      () async {
        final autre = Acteur();
        addTearDown(autre.fermer);
        await autre.session.connecter(
          'awa.client@sopi.invalid',
          'NouveauMotDePasse-2',
        );
        await client.dio.post<Object?>('/auth/deconnexion');
        final e = await erreurDe(
          () => autre.dio.get<Object?>('/me').then((_) => null).then((_) async {
            // Révoque le jeton de « autre » depuis un troisième client, puis réessaie.
            await autre.dio.post<Object?>('/auth/deconnexion');
            return autre.dio.get<Object?>('/me');
          }),
        );
        expect(e, isA<ErreurSession>());
        expect(autre.etat, isA<NonConnecte>());
      },
    );
  });
}

Matcher _serveur(int statut) =>
    isA<ErreurServeur>().having((e) => e.statut, 'statut', statut);
