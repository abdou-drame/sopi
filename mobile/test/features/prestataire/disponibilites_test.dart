import 'package:dio/dio.dart' show RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/reservation/depot_reservation.dart'
    show horlogeProvider;

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_prestataire.dart';

final _maintenant = DateTime.utc(2026, 10, 26, 9);

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Map<String, Object?> _creneau(String id, String debut, String statut) => {
  'id': id,
  'dateDebut': '$debut:00+00:00',
  'dateFin': '${debut.substring(0, 11)}${_plusUneHeure(debut)}:00+00:00',
  'statut': statut,
};

String _plusUneHeure(String debut) {
  final h = int.parse(debut.substring(11, 13)) + 1;
  return '${h.toString().padLeft(2, '0')}:${debut.substring(14)}';
}

List<Map<String, Object?>> _creneaux() => [
  _creneau('c-1', '2026-10-26T09:00', 'libre'),
  _creneau('c-2', '2026-10-26T10:00', 'reserve'),
  _creneau('c-3', '2026-10-26T11:00', 'verrouille'),
  _creneau('c-4', '2026-10-27T09:00', 'indisponible'),
  _creneau('c-5', '2026-10-27T10:00', 'libre'),
];

Future<AdaptateurSimule> _ouvrir(
  WidgetTester tester, {
  String chemin = '/prestataire/disponibilites',
  Map<String, Simulee> enPlus = const {},
  List<Map<String, Object?>>? creneaux,
}) async {
  _vue390(tester);
  final serveur = AdaptateurSimule({
    'GET /me': Simulee(200, utilisateurPrestataire()),
    'GET /prestataire/solde': const Simulee(200, {'solde': 42500}),
    'GET /prestataire/reservations': Simulee(200, pageDemandesApi([])),
    'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
    'GET /prestataire/agenda': Simulee(200, {'data': creneaux ?? _creneaux()}),
    ...enPlus,
  });
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: chemin,
    surcharges: [horlogeProvider.overrideWithValue(() => _maintenant)],
  );
  await passerTransition(tester);
  return serveur;
}

List<RequestOptions> _ecritures(AdaptateurSimule s) =>
    s.requetes.where((r) => r.method != 'GET').toList();

Finder get _champs => find.byType(TextField);

void main() {
  setUpAll(initialiserFormats);

  group('Disponibilités', () {
    testWidgets(
      'créneaux groupés par jour, croix sur les seuls créneaux libres',
      (tester) async {
        final serveur = await _ouvrir(tester);
        expect(find.text('Lun. 26 oct.'), findsOneWidget);
        expect(find.text('Mar. 27 oct.'), findsOneWidget);
        expect(find.text('09h00'), findsNWidgets(2));
        expect(find.text('10h00 · réservé'), findsOneWidget);
        expect(find.text('11h00 · verrouillé'), findsOneWidget);
        expect(find.text('10h00'), findsOneWidget);
        // Deux créneaux libres, deux croix ; les autres n'en ont pas.
        expect(find.byIcon(Icons.close_rounded), findsNWidgets(2));
        expect(find.textContaining('Touchez la croix'), findsOneWidget);
        expect(find.text('Ajouter des créneaux'), findsOneWidget);
        expect(find.text('Déclarer une indisponibilité'), findsOneWidget);
        // L'intervalle demandé part d'aujourd'hui et ne dépasse pas 62 jours.
        final lecture = serveur.requetes.firstWhere(
          (r) => r.path == '/prestataire/agenda',
        );
        expect(lecture.queryParameters, {
          'du': '2026-10-26',
          'au': '2026-12-27',
        });
      },
    );

    testWidgets('aucun créneau : état vide', (tester) async {
      await _ouvrir(tester, creneaux: []);
      expect(find.text('Aucun créneau'), findsOneWidget);
    });

    testWidgets('erreur serveur : message affiché', (tester) async {
      await _ouvrir(
        tester,
        enPlus: {
          'GET /prestataire/agenda': const Simulee(503, {
            'message': 'Service indisponible.',
          }),
        },
      );
      expect(find.text('Service indisponible.'), findsOneWidget);
    });

    testWidgets('croix : confirmation, « Non » ne supprime rien', (
      tester,
    ) async {
      final serveur = await _ouvrir(tester);
      await tester.tap(find.byIcon(Icons.close_rounded).first);
      await avancer(tester, 500);
      expect(find.text('Retirer ce créneau ?'), findsOneWidget);
      await tester.tap(find.text('Non'));
      await avancer(tester, 500);
      expect(_ecritures(serveur), isEmpty);
    });

    testWidgets('croix puis « Oui » : DELETE du créneau, liste relue', (
      tester,
    ) async {
      final serveur = await _ouvrir(
        tester,
        enPlus: {
          'DELETE /prestataire/agenda/creneaux/c-1': const Simulee(204, null),
        },
      );
      await tester.tap(find.byIcon(Icons.close_rounded).first);
      await avancer(tester, 500);
      // Le créneau disparaît de l'agenda relu.
      serveur.routes['GET /prestataire/agenda'] = Simulee(200, {
        'data': _creneaux().where((c) => c['id'] != 'c-1').toList(),
      });
      await tester.tap(find.text('Oui, retirer'));
      await avancer(tester, 800);
      final envoi = _ecritures(serveur).single;
      expect(envoi.method, 'DELETE');
      expect(envoi.path, '/prestataire/agenda/creneaux/c-1');
      expect(find.text('Créneau retiré.'), findsOneWidget);
      expect(find.byIcon(Icons.close_rounded), findsNWidgets(1));
    });

    testWidgets('409 : message du serveur affiché', (tester) async {
      await _ouvrir(
        tester,
        enPlus: {
          'DELETE /prestataire/agenda/creneaux/c-1': const Simulee(409, {
            'message': "Ce créneau n'est plus libre.",
          }),
        },
      );
      await tester.tap(find.byIcon(Icons.close_rounded).first);
      await avancer(tester, 500);
      await tester.tap(find.text('Oui, retirer'));
      await avancer(tester, 800);
      expect(find.text("Ce créneau n'est plus libre."), findsOneWidget);
    });

    testWidgets('les boutons ouvrent les deux formulaires', (tester) async {
      await _ouvrir(tester);
      await tester.tap(find.text('Ajouter des créneaux'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.plageHoraire);
    });
  });

  group('Ajouter des créneaux', () {
    Future<AdaptateurSimule> ouvrir(
      WidgetTester tester, {
      Map<String, Simulee> enPlus = const {},
    }) => _ouvrir(tester, chemin: Chemins.plageHoraire, enPlus: enPlus);

    testWidgets('champs, puces, répétition et mention', (tester) async {
      await ouvrir(tester);
      expect(find.text('Ajouter des'), findsOneWidget);
      expect(find.text('Lun. 26 oct. 2026'), findsOneWidget);
      expect(tester.widget<TextField>(_champs.at(0)).controller!.text, '08:00');
      expect(tester.widget<TextField>(_champs.at(1)).controller!.text, '18:00');
      for (final d in ['30 min', '45 min', '1 h', '1 h 30', '2 h']) {
        expect(find.text(d), findsOneWidget);
      }
      expect(find.text('Répéter chaque semaine'), findsOneWidget);
      expect(find.text('Pendant 4 semaines (8 au maximum)'), findsOneWidget);
      expect(
        find.text(
          'Les clients pourront réserver ces créneaux. Les réservations déjà prises ne sont pas modifiées.',
          skipOffstage: false,
        ),
        findsOneWidget,
      );
      expect(find.text('Créer les créneaux'), findsOneWidget);
    });

    testWidgets(
      'création : corps conforme à l\'API, retour aux disponibilités',
      (tester) async {
        final serveur = await ouvrir(
          tester,
          enPlus: {
            'POST /prestataire/agenda/plages': const Simulee(201, {
              'nombreCreneaux': 10,
            }),
          },
        );
        await tester.enterText(_champs.at(0), '0900');
        await tester.enterText(_champs.at(1), '1200');
        await tester.tap(find.text('30 min'));
        await tester.pump();
        await tester.tap(find.text('Créer les créneaux'));
        await passerTransition(tester);

        final envoi = _ecritures(serveur).single;
        expect(envoi.path, '/prestataire/agenda/plages');
        expect(envoi.data, {
          'plages': [
            {
              'date': '2026-10-26',
              'heure_debut': '09:00',
              'heure_fin': '12:00',
              'duree_creneau_minutes': 30,
            },
          ],
        });
        expect(find.text('10 créneaux créés.'), findsOneWidget);
        expect(cheminCourant(tester), Chemins.disponibilites);
      },
    );

    testWidgets('répétition : repeter_semaines, de 1 à 8', (tester) async {
      final serveur = await ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/agenda/plages': const Simulee(201, {
            'nombreCreneaux': 30,
          }),
        },
      );
      await tester.tap(
        find.byType(Switch).evaluate().isEmpty
            ? find.bySemanticsLabel('Répéter chaque semaine')
            : find.byType(Switch),
      );
      await tester.pump();
      await tester.ensureVisible(find.byIcon(Icons.add_rounded));
      await tester.pump();
      for (var i = 0; i < 6; i++) {
        await tester.tap(find.byIcon(Icons.add_rounded));
        await tester.pump();
      }
      // Plafonné à 8.
      expect(find.text('8', skipOffstage: false), findsOneWidget);
      expect(find.text('Pendant 8 semaines (8 au maximum)'), findsOneWidget);
      await tester.tap(find.byIcon(Icons.remove_rounded));
      await tester.pump();
      await tester.tap(find.text('Créer les créneaux'));
      await passerTransition(tester);
      final plage =
          ((_ecritures(serveur).single.data as Map)['plages'] as List).single
              as Map;
      expect(plage['repeter_semaines'], 7);
    });

    testWidgets('heure invalide : message local, rien n\'est envoyé', (
      tester,
    ) async {
      final serveur = await ouvrir(tester);
      await tester.enterText(_champs.at(0), '2560');
      await tester.tap(find.text('Créer les créneaux'));
      await tester.pump();
      expect(find.text('Heure invalide (HH:MM).'), findsOneWidget);
      expect(_ecritures(serveur), isEmpty);
    });

    testWidgets('422 : erreurs du serveur sous les champs concernés', (
      tester,
    ) async {
      await ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/agenda/plages': const Simulee(422, {
            'message': 'Les données sont invalides.',
            'errors': {
              'plages.0.heure_fin': [
                "Plage 1 : l'heure de fin doit être après l'heure de début.",
              ],
              'plages.0.duree_creneau_minutes': [
                'Plage 1 : la plage de 90 minutes doit contenir un nombre entier de créneaux de 60 minutes.',
              ],
            },
          }),
        },
      );
      await tester.tap(find.text('Créer les créneaux'));
      await passerTransition(tester);
      expect(
        find.text("Plage 1 : l'heure de fin doit être après l'heure de début."),
        findsOneWidget,
      );
      expect(
        find.textContaining('doit contenir un nombre entier de créneaux'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.plageHoraire);
    });

    testWidgets('erreur sur la liste de plages : bandeau', (tester) async {
      await ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/agenda/plages': const Simulee(422, {
            'message': 'Trop de créneaux.',
            'errors': {
              'plages': [
                'Cette demande créerait 600 créneaux ; le maximum est de 500 par requête.',
              ],
            },
          }),
        },
      );
      await tester.tap(find.text('Créer les créneaux'));
      await passerTransition(tester);
      expect(
        find.text(
          'Cette demande créerait 600 créneaux ; le maximum est de 500 par requête.',
          skipOffstage: false,
        ),
        findsOneWidget,
      );
    });

    testWidgets('le sélecteur de jour change la date envoyée', (tester) async {
      final serveur = await ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/agenda/plages': const Simulee(201, {
            'nombreCreneaux': 10,
          }),
        },
      );
      await tester.tap(find.text('Lun. 26 oct. 2026'));
      await avancer(tester, 500);
      await tester.tap(find.text('28'));
      await tester.tap(find.text('OK'));
      await avancer(tester, 500);
      expect(find.text('Mer. 28 oct. 2026'), findsOneWidget);
      await tester.tap(find.text('Créer les créneaux'));
      await passerTransition(tester);
      final plage =
          ((_ecritures(serveur).single.data as Map)['plages'] as List).single
              as Map;
      expect(plage['date'], '2026-10-28');
    });
  });

  group('Déclarer une indisponibilité', () {
    Future<AdaptateurSimule> ouvrir(
      WidgetTester tester, {
      Map<String, Simulee> enPlus = const {},
    }) => _ouvrir(tester, chemin: Chemins.indisponibilite, enPlus: enPlus);

    Future<void> remplir(WidgetTester tester) async {
      await tester.enterText(_champs.at(0), '27102026');
      await tester.enterText(_champs.at(1), '0800');
      await tester.enterText(_champs.at(2), '28102026');
      await tester.enterText(_champs.at(3), '1800');
      await tester.pump();
    }

    testWidgets('champs, mise en garde, pas de motif', (tester) async {
      await ouvrir(tester);
      expect(find.text('Déclarer une'), findsOneWidget);
      expect(find.text('Du'), findsOneWidget);
      expect(find.text('Au'), findsOneWidget);
      expect(find.text('Heure'), findsNWidgets(2));
      expect(
        find.text(
          'Attention : les réservations déjà prises sur cette période seront annulées et les clients remboursés.',
        ),
        findsOneWidget,
      );
      expect(find.textContaining('Motif'), findsNothing);
      expect(find.text("Déclarer l'indisponibilité"), findsOneWidget);
      expect(find.text('Annuler'), findsOneWidget);
    });

    testWidgets('dates au format de l\'API après confirmation explicite', (
      tester,
    ) async {
      final serveur = await ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/agenda/indisponibilites': const Simulee(200, {
            'creneauxBloques': 12,
            'creneauxReserves': 2,
            'creneauxVerrouilles': 0,
            'reservationsAnnulees': 2,
          }),
        },
      );
      await remplir(tester);
      expect(
        tester.widget<TextField>(_champs.at(0)).controller!.text,
        '27/10/2026',
      );
      await tester.tap(find.text("Déclarer l'indisponibilité"));
      await avancer(tester, 500);
      // Rien n'est parti avant la confirmation, qui rappelle l'annulation et le remboursement.
      expect(_ecritures(serveur), isEmpty);
      expect(find.text("Déclarer l'indisponibilité ?"), findsOneWidget);
      expect(
        find.textContaining('seront annulées et les clients remboursés'),
        findsNWidgets(2),
      );
      await tester.tap(find.text('Oui, déclarer'));
      await passerTransition(tester);

      final envoi = _ecritures(serveur).single;
      expect(envoi.path, '/prestataire/agenda/indisponibilites');
      expect(envoi.data, {
        'debut': '2026-10-27 08:00',
        'fin': '2026-10-28 18:00',
        'annuler_reservations': true,
      });
      expect(
        find.text(
          'Indisponibilité enregistrée. 2 réservations annulées, clients remboursés.',
        ),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.disponibilites);
    });

    const urlApercu = 'GET /prestataire/agenda/indisponibilites/apercu';
    const urlDeclarer = 'POST /prestataire/agenda/indisponibilites';
    final concernees = Simulee(200, {
      'data': [
        {
          'id': 'r-1',
          'client': 'Awa D.',
          'prestation': 'Vidange',
          'debut': '2026-10-27T09:00:00+00:00',
        },
        {
          'id': 'r-2',
          'client': 'Moussa S.',
          'prestation': 'Freinage',
          'debut': '2026-10-28T10:00:00+00:00',
        },
      ],
    });

    testWidgets('aucune réservation : pas de carte, aperçu demandé', (
      tester,
    ) async {
      final serveur = await ouvrir(
        tester,
        enPlus: {urlApercu: const Simulee(200, {'data': []})},
      );
      await remplir(tester);
      await avancer(tester, 300);
      expect(find.textContaining('Réservations concernées'), findsNothing);
      final apercu = serveur.requetes.where(
        (r) => r.path == '/prestataire/agenda/indisponibilites/apercu',
      );
      expect(apercu.last.queryParameters, {
        'debut': '2026-10-27 08:00',
        'fin': '2026-10-28 18:00',
      });
    });

    testWidgets('réservations : carte, annuler par défaut', (tester) async {
      final serveur = await ouvrir(
        tester,
        enPlus: {
          urlApercu: concernees,
          urlDeclarer: const Simulee(200, {
            'creneauxBloques': 3,
            'creneauxReserves': 2,
            'creneauxVerrouilles': 0,
            'reservationsAnnulees': 2,
          }),
        },
      );
      await remplir(tester);
      await avancer(tester, 300);
      expect(find.text('Réservations concernées (2)'), findsOneWidget);
      expect(find.textContaining('Awa D. · Vidange'), findsOneWidget);
      expect(find.textContaining('Moussa S. · Freinage'), findsOneWidget);
      expect(find.text('Annuler ces réservations'), findsOneWidget);
      expect(find.text('Les clients sont remboursés'), findsOneWidget);
      expect(find.text('Garder ces réservations'), findsOneWidget);
      await tester.tap(find.text("Déclarer l'indisponibilité"));
      await avancer(tester, 500);
      await tester.tap(find.text('Oui, déclarer'));
      await passerTransition(tester);
      expect(_ecritures(serveur).single.data['annuler_reservations'], true);
      expect(
        find.text(
          'Indisponibilité enregistrée. 2 réservations annulées, clients remboursés.',
        ),
        findsOneWidget,
      );
    });

    testWidgets('garder : annuler_reservations false, message adapté', (
      tester,
    ) async {
      final serveur = await ouvrir(
        tester,
        enPlus: {
          urlApercu: concernees,
          urlDeclarer: const Simulee(200, {
            'creneauxBloques': 3,
            'creneauxReserves': 0,
            'creneauxVerrouilles': 0,
            'reservationsAnnulees': 0,
          }),
        },
      );
      await remplir(tester);
      await avancer(tester, 300);
      await tester.ensureVisible(find.text('Garder ces réservations'));
      await tester.pump();
      await tester.tap(find.text('Garder ces réservations'));
      await tester.pump();
      await tester.tap(find.text("Déclarer l'indisponibilité"));
      await avancer(tester, 500);
      expect(find.textContaining('sont conservées'), findsOneWidget);
      await tester.tap(find.text('Oui, déclarer'));
      await passerTransition(tester);
      expect(_ecritures(serveur).single.data['annuler_reservations'], false);
      expect(
        find.text(
          'Indisponibilité enregistrée. Les réservations existantes sont conservées.',
        ),
        findsOneWidget,
      );
    });

    testWidgets('« Non » à la confirmation : rien n\'est envoyé', (
      tester,
    ) async {
      final serveur = await ouvrir(tester);
      await remplir(tester);
      await tester.tap(find.text("Déclarer l'indisponibilité"));
      await avancer(tester, 500);
      await tester.tap(find.text('Non'));
      await avancer(tester, 500);
      expect(_ecritures(serveur), isEmpty);
      expect(cheminCourant(tester), Chemins.indisponibilite);
    });

    testWidgets('date inexistante : message local, pas de confirmation', (
      tester,
    ) async {
      final serveur = await ouvrir(tester);
      await tester.enterText(_champs.at(0), '31022026');
      await tester.tap(find.text("Déclarer l'indisponibilité"));
      await avancer(tester, 500);
      expect(
        find.text('Date ou heure invalide (JJ/MM/AAAA, HH:MM).'),
        findsOneWidget,
      );
      expect(find.text("Déclarer l'indisponibilité ?"), findsNothing);
      expect(_ecritures(serveur), isEmpty);
    });

    testWidgets('409 prestation en cours : message du serveur', (tester) async {
      await ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/agenda/indisponibilites': const Simulee(409, {
            'message': 'Une prestation est en cours sur cette période.',
          }),
        },
      );
      await remplir(tester);
      await tester.tap(find.text("Déclarer l'indisponibilité"));
      await avancer(tester, 500);
      await tester.tap(find.text('Oui, déclarer'));
      await passerTransition(tester);
      expect(
        find.text('Une prestation est en cours sur cette période.'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.indisponibilite);
    });

    testWidgets('422 : erreur du serveur sous la ligne concernée', (
      tester,
    ) async {
      await ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/agenda/indisponibilites': const Simulee(422, {
            'message': 'Les données sont invalides.',
            'errors': {
              'fin': ['La fin doit être après le début.'],
            },
          }),
        },
      );
      await remplir(tester);
      await tester.tap(find.text("Déclarer l'indisponibilité"));
      await avancer(tester, 500);
      await tester.tap(find.text('Oui, déclarer'));
      await passerTransition(tester);
      expect(find.text('La fin doit être après le début.'), findsOneWidget);
    });
  });
}
