import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/api/client_api.dart';
import 'package:sopi/core/api/stockage_jeton.dart';
import 'package:sopi/features/prestataire/depot_prestataire.dart';
import 'package:sopi/features/prestataire/modeles.dart';
import 'package:sopi/features/reservation/modeles.dart';

import '../../aide_api.dart';
import 'aide_prestataire.dart';

DepotPrestataire _depot(AdaptateurSimule serveur) => DepotPrestataire(
  creerClientApi(
    stockage: StockageJetonMemoire(),
    urlBase: 'http://test/api/v1',
    adaptateur: serveur,
    surSessionExpiree: () {},
  ),
);

void main() {
  group('DemandeReservation.depuisJson', () {
    test('lit une demande en attente avec ses montants et son échéance', () {
      final d = DemandeReservation.depuisJson(demandeApi());
      expect(d.id, 'r-1');
      expect(d.statut, StatutReservation.enAttente);
      expect(d.aTraiter, isTrue);
      expect(d.clientAffiche, 'Aminata D.');
      expect(d.prestationNom, 'Coupe femme');
      expect(d.dureeMinutes, 60);
      expect(d.prix, 5000);
      expect(d.debut, DateTime.utc(2026, 10, 24, 10));
      expect(d.reponseAvant, DateTime.utc(2026, 10, 24, 12));
      expect(d.acompte, 1500);
      expect(d.commission, 500);
      expect(d.montantCredite, 1000);
      expect(d.montantsConnus, isTrue);
    });

    test('une réponse d\'action sans montants les laisse à null', () {
      final d = DemandeReservation.depuisJson(
        demandeApi(statut: 'acceptee', avecMontants: false),
      );
      expect(d.statut, StatutReservation.acceptee);
      expect(d.reponseAvant, isNull);
      expect(d.montantsConnus, isFalse);
    });

    test(
      'ne contient aucun téléphone : le client n\'a que prénom et initiale',
      () {
        final d = DemandeReservation.depuisJson(demandeApi());
        expect(d.clientPrenom, 'Aminata');
        expect(d.clientInitiale, 'D');
      },
    );
  });

  group('DepotPrestataire', () {
    test('solde lit GET /prestataire/solde', () async {
      final serveur = AdaptateurSimule({
        'GET /prestataire/solde': const Simulee(200, {'solde': 42500.0}),
      });
      expect(await _depot(serveur).solde(), 42500);
    });

    test('demandes : À traiter envoie statut=en_attente et la page', () async {
      final serveur = AdaptateurSimule({
        'GET /prestataire/reservations': Simulee(
          200,
          pageDemandesApi([demandeApi()], total: 3),
        ),
      });
      final page = await _depot(serveur).demandes(OngletDemandes.aTraiter);
      expect(page.pagination.total, 3);
      expect(page.elements, hasLength(1));
      final requete = serveur.requetes.single;
      expect(requete.queryParameters['statut'], 'en_attente');
      expect(requete.queryParameters.containsKey('groupe'), isFalse);
      expect(requete.queryParameters['page'], 1);
    });

    test('demandes : les autres onglets envoient groupe=', () async {
      final serveur = AdaptateurSimule({
        'GET /prestataire/reservations': Simulee(200, pageDemandesApi([])),
      });
      final depot = _depot(serveur);
      for (final (onglet, groupe) in [
        (OngletDemandes.aVenir, 'a_venir'),
        (OngletDemandes.terminees, 'terminees'),
        (OngletDemandes.annulees, 'annulees'),
      ]) {
        await depot.demandes(onglet, page: 2);
        final requete = serveur.requetes.last;
        expect(requete.queryParameters['groupe'], groupe);
        expect(requete.queryParameters.containsKey('statut'), isFalse);
        expect(requete.queryParameters['page'], 2);
      }
    });

    test('accepter et refuser appellent les bonnes routes', () async {
      final serveur = AdaptateurSimule({
        'POST /prestataire/reservations/r-1/accepter': Simulee(
          200,
          demandeApi(statut: 'acceptee', avecMontants: false),
        ),
        'POST /prestataire/reservations/r-1/refuser': Simulee(
          200,
          demandeApi(statut: 'refusee', avecMontants: false),
        ),
      });
      final depot = _depot(serveur);
      expect((await depot.accepter('r-1')).statut, StatutReservation.acceptee);
      final refusee = await depot.refuser('r-1', 'Indisponible');
      expect(refusee.statut, StatutReservation.refusee);
      expect(serveur.requetes.last.data, {'motif': 'Indisponible'});
    });
  });
}
