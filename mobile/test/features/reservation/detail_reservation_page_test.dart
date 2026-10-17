import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

final _debut = DateTime.utc(2026, 10, 24, 10);
const _telephone = '+221771234567';

Map<String, Object?> _res({
  String statut = 'en_attente',
  bool peutAnnuler = false,
  bool peutPayer = false,
  bool peutNoter = false,
  String? code,
  Map<String, Object?>? paiement,
  Map<String, Object?>? avis,
  String? motif,
}) => reservationApi(
  'r-1',
  debut: _debut,
  statut: statut,
  peutAnnuler: peutAnnuler,
  peutPayer: peutPayer,
  peutNoter: peutNoter,
  code: code,
  paiement: paiement,
  avis: avis,
  motifRefus: motif,
  telephone:
      statut == 'confirmee' || statut == 'en_cours' || statut == 'terminee'
      ? _telephone
      : null,
);

const _paye = {'statut': 'reussi', 'montantAcompte': 1500};

AdaptateurSimule _serveur(
  Map<String, Object?> reservation, {
  Map<String, Simulee> routes = const {},
}) => AdaptateurSimule(
  routesClient({
    'GET /reservations/r-1': Simulee(200, {'data': reservation}),
    'GET /prestataires/p-1': Simulee(200, profilApi()),
    ...routes,
  }),
);

int _lectures(AdaptateurSimule serveur) => serveur.requetes
    .where((r) => r.method == 'GET' && r.path == '/reservations/r-1')
    .length;

Future<void> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur, {
  List<Uri>? urls,
}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.detailReservation('r-1'),
    urlsOuvertes: urls,
  );
  await passerTransition(tester);
}

void main() {
  setUpAll(initialiserFormats);

  group('Détail : présentations selon le statut', () {
    testWidgets('en attente : bandeau des 12 h et « Annuler la demande »', (
      tester,
    ) async {
      await _ouvrir(tester, _serveur(_res(peutAnnuler: true)));
      expect(find.text('En attente'), findsOneWidget);
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(
        find.text('Coupe femme · ${formaterDate(_debut)}'),
        findsOneWidget,
      );
      expect(find.textContaining('délai limité pour répondre'), findsOneWidget);
      expect(find.text('Annuler la demande'), findsOneWidget);
      expect(find.textContaining('Payer'), findsNothing);
      expect(find.text('Code de démarrage'), findsNothing);
      expect(find.text('Suivi'), findsOneWidget);
    });

    testWidgets(
      'acceptée à payer : bandeau bleu, délai de 2 h, bouton de paiement',
      (tester) async {
        await _ouvrir(
          tester,
          _serveur(
            _res(statut: 'acceptee', peutAnnuler: true, peutPayer: true),
          ),
        );
        expect(find.text('Acceptée · à payer'), findsOneWidget);
        expect(find.text('Le prestataire a accepté'), findsOneWidget);
        // L'API ne donne pas l'échéance : pas de compte à rebours, seulement le rappel du délai.
        expect(find.textContaining('Il reste'), findsNothing);
        expect(
          find.textContaining('Vous avez un délai limité'),
          findsOneWidget,
        );
        expect(
          find.text('Payer l\'acompte · ${formaterArgent(1500)}'),
          findsOneWidget,
        );
        expect(find.text('Annuler la demande'), findsOneWidget);
        expect(find.text('Acompte à payer (30 %)'), findsOneWidget);
        await toucher(tester, find.textContaining('Payer l\'acompte'));
        await passerTransition(tester);
        expect(cheminCourant(tester), Chemins.paiement('r-1'));
      },
    );

    testWidgets('confirmée : code, appel, annulation et signalement', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        _serveur(
          _res(
            statut: 'confirmee',
            peutAnnuler: true,
            code: 'K7MQ4Z',
            paiement: _paye,
          ),
        ),
      );
      expect(find.text('Confirmée'), findsOneWidget);
      expect(find.text('Code de démarrage'), findsOneWidget);
      for (final c in ['K', '7', 'M', 'Q', '4', 'Z']) {
        expect(find.text(c), findsOneWidget);
      }
      expect(
        find.text('Donnez ce code au prestataire à son arrivée.'),
        findsOneWidget,
      );
      expect(find.text('Acompte payé (30 %)'), findsOneWidget);
      expect(find.text(formaterArgent(5000)), findsOneWidget);
      expect(find.text(formaterArgent(3500)), findsOneWidget);
      expect(find.text('Contacter le prestataire'), findsOneWidget);
      expect(find.text('Annuler'), findsOneWidget);
      expect(find.text('Signaler un problème'), findsOneWidget);
    });

    testWidgets('en cours : le code reste visible', (tester) async {
      await _ouvrir(
        tester,
        _serveur(_res(statut: 'en_cours', code: 'K7MQ4Z', paiement: _paye)),
      );
      expect(find.text('En cours'), findsOneWidget);
      expect(find.text('Code de démarrage'), findsOneWidget);
      expect(find.text('Prestation en cours'), findsOneWidget);
      expect(find.text('Annuler'), findsNothing);
    });

    testWidgets('terminée : pas de code, avis à laisser', (tester) async {
      await _ouvrir(
        tester,
        _serveur(
          _res(
            statut: 'terminee',
            peutNoter: true,
            code: 'K7MQ4Z',
            paiement: _paye,
          ),
        ),
      );
      expect(find.text('Terminée'), findsOneWidget);
      expect(find.text('Code de démarrage'), findsNothing);
      expect(find.text('Laisser un avis'), findsOneWidget);
      expect(find.text('Signaler un problème'), findsOneWidget);
      await toucher(tester, find.text('Laisser un avis'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.avis('r-1'));
    });

    testWidgets('terminée avec avis donné : l\'avis remplace le bouton', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        _serveur(
          _res(
            statut: 'terminee',
            paiement: _paye,
            avis: {'note': 4, 'commentaire': 'Très bien'},
          ),
        ),
      );
      expect(find.text('Votre avis : 4/5'), findsOneWidget);
      expect(find.text('Très bien'), findsOneWidget);
      expect(find.text('Laisser un avis'), findsNothing);
    });

    testWidgets('annulée : pastille et message, aucune action', (tester) async {
      await _ouvrir(tester, _serveur(_res(statut: 'annulee')));
      expect(find.text('Annulée'), findsOneWidget);
      expect(find.text('Cette réservation a été annulée.'), findsOneWidget);
      expect(find.text('Suivi'), findsNothing);
      expect(find.byType(FilledButton), findsNothing);
      expect(find.text('Annuler'), findsNothing);
    });

    testWidgets('refusée : motif du prestataire', (tester) async {
      await _ouvrir(
        tester,
        _serveur(_res(statut: 'refusee', motif: 'Je suis indisponible.')),
      );
      expect(find.text('Refusée'), findsOneWidget);
      expect(find.text('Je suis indisponible.'), findsOneWidget);
      expect(find.text('Signaler un problème'), findsNothing);
    });

    testWidgets('sans peutAnnuler, pas de bouton d\'annulation', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        _serveur(_res(statut: 'confirmee', code: 'K7MQ4Z', paiement: _paye)),
      );
      expect(find.text('Annuler'), findsNothing);
      expect(find.text('Signaler un problème'), findsOneWidget);
    });

    testWidgets('« Signaler un problème » ouvre le litige', (tester) async {
      await _ouvrir(
        tester,
        _serveur(_res(statut: 'confirmee', code: 'K7MQ4Z', paiement: _paye)),
      );
      await toucher(tester, find.text('Signaler un problème'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.litige('r-1'));
    });
  });

  group('Détail : code et appel', () {
    testWidgets(
      '« Copier » met le code dans le presse-papiers et le confirme',
      (tester) async {
        final copies = <String>[];
        tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          SystemChannels.platform,
          (appel) async {
            if (appel.method == 'Clipboard.setData') {
              copies.add((appel.arguments as Map)['text'] as String);
            }
            return null;
          },
        );
        addTearDown(
          () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
            SystemChannels.platform,
            null,
          ),
        );
        await _ouvrir(
          tester,
          _serveur(_res(statut: 'confirmee', code: 'K7MQ4Z', paiement: _paye)),
        );
        expect(find.text('Copier'), findsOneWidget);
        await toucher(tester, find.text('Copier'));
        expect(copies, ['K7MQ4Z']);
        expect(find.text('Copié'), findsOneWidget);
        await avancer(tester, 2200);
        expect(find.text('Copier'), findsOneWidget);
      },
    );

    testWidgets('« Contacter le prestataire » lance un appel tel:', (
      tester,
    ) async {
      final urls = <Uri>[];
      await _ouvrir(
        tester,
        _serveur(_res(statut: 'confirmee', code: 'K7MQ4Z', paiement: _paye)),
        urls: urls,
      );
      await toucher(tester, find.text('Contacter le prestataire'));
      expect(urls, hasLength(1));
      expect(urls.single.scheme, 'tel');
      expect(urls.single.path, _telephone);
      expect(urls.single.toString(), 'tel:$_telephone');
    });

    testWidgets('pas de téléphone renvoyé : pas de bouton d\'appel', (
      tester,
    ) async {
      final r = reservationApi(
        'r-1',
        debut: _debut,
        statut: 'confirmee',
        code: 'K7MQ4Z',
        peutAnnuler: false,
        paiement: _paye,
        telephone: null,
      );
      await _ouvrir(tester, _serveur(r));
      expect(find.text('Contacter le prestataire'), findsNothing);
    });
  });

  group('Détail : actualisation', () {
    testWidgets('en attente : relecture automatique toutes les 30 s', (
      tester,
    ) async {
      final serveur = _serveur(_res(peutAnnuler: true));
      await _ouvrir(tester, serveur);
      final avant = _lectures(serveur);
      await avancer(tester, 31000);
      expect(_lectures(serveur), greaterThan(avant));
    });

    testWidgets('confirmée : aucune relecture automatique', (tester) async {
      final serveur = _serveur(
        _res(statut: 'confirmee', code: 'K7MQ4Z', paiement: _paye),
      );
      await _ouvrir(tester, serveur);
      final avant = _lectures(serveur);
      await avancer(tester, 61000);
      expect(_lectures(serveur), avant);
    });

    testWidgets('tirer pour actualiser relit la réservation', (tester) async {
      final serveur = _serveur(_res(peutAnnuler: true));
      await _ouvrir(tester, serveur);
      final avant = _lectures(serveur);
      await tester.fling(
        find.byType(Scrollable).first,
        const Offset(0, 400),
        1000,
      );
      await avancer(tester, 1000);
      expect(_lectures(serveur), greaterThan(avant));
    });

    testWidgets('erreur du serveur : message et « Réessayer »', (tester) async {
      final serveur = AdaptateurSimule(
        routesClient({
          'GET /reservations/r-1': const Simulee(404, {
            'message': 'Réservation introuvable.',
          }),
        }),
      );
      await _ouvrir(tester, serveur);
      expect(find.text('Réservation introuvable.'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('petit écran 320×568 : pas de débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await lancerAcces(
        tester,
        _serveur(
          _res(
            statut: 'confirmee',
            peutAnnuler: true,
            code: 'K7MQ4Z',
            paiement: _paye,
          ),
        ),
        jeton: 'jeton',
        chemin: Chemins.detailReservation('r-1'),
      );
      await passerTransition(tester);
      expect(tester.takeException(), isNull);
      expect(find.text('Code de démarrage'), findsOneWidget);
    });
  });

  group('Annulation', () {
    Simulee ok() => const Simulee(200, {'message': 'Réservation annulée.'});

    testWidgets('acompte payé : le message dit qu\'il n\'est pas remboursé', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        _serveur(
          _res(
            statut: 'confirmee',
            peutAnnuler: true,
            code: 'K7MQ4Z',
            paiement: _paye,
          ),
        ),
      );
      await toucher(tester, find.text('Annuler'));
      await passerTransition(tester);
      expect(find.text('Annuler cette réservation ?'), findsOneWidget);
      expect(
        find.text('Salon Fatou · ${formaterDate(_debut)}'),
        findsOneWidget,
      );
      expect(
        find.textContaining(
          'L\'acompte de ${formaterArgent(1500)} ne sera pas remboursé.',
        ),
        findsOneWidget,
      );
      expect(find.byType(TextField), findsNothing);
      expect(find.text('Garder ma réservation'), findsOneWidget);
      expect(find.text('Oui, annuler'), findsOneWidget);
    });

    testWidgets('sans acompte payé : annulation sans frais', (tester) async {
      await _ouvrir(tester, _serveur(_res(peutAnnuler: true)));
      await toucher(tester, find.text('Annuler la demande'));
      await passerTransition(tester);
      expect(find.textContaining('Aucun paiement'), findsOneWidget);
      expect(find.textContaining('ne sera pas remboursé'), findsNothing);
    });

    testWidgets('« Garder ma réservation » ferme sans rien envoyer', (
      tester,
    ) async {
      final serveur = _serveur(_res(peutAnnuler: true));
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Annuler la demande'));
      await passerTransition(tester);
      await toucher(tester, find.text('Garder ma réservation'));
      await passerTransition(tester);
      expect(find.text('Annuler cette réservation ?'), findsNothing);
      expect(serveur.requetes.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets(
      '« Oui, annuler » : appel, fermeture, confirmation et relecture',
      (tester) async {
        final serveur = _serveur(
          _res(peutAnnuler: true),
          routes: {'POST /reservations/r-1/annuler': ok()},
        );
        await _ouvrir(tester, serveur);
        await toucher(tester, find.text('Annuler la demande'));
        await passerTransition(tester);
        final avant = _lectures(serveur);
        serveur.routes['GET /reservations/r-1'] = Simulee(200, {
          'data': _res(statut: 'annulee'),
        });
        await toucher(tester, find.text('Oui, annuler'));
        await passerTransition(tester);
        expect(
          serveur.requetes.where(
            (r) => r.method == 'POST' && r.path == '/reservations/r-1/annuler',
          ),
          hasLength(1),
        );
        expect(find.text('Annuler cette réservation ?'), findsNothing);
        expect(find.text('Votre réservation a été annulée.'), findsOneWidget);
        expect(_lectures(serveur), greaterThan(avant));
        expect(find.text('Annulée'), findsOneWidget);
      },
    );

    testWidgets('409 : le message du serveur s\'affiche dans la feuille', (
      tester,
    ) async {
      final serveur = _serveur(
        _res(peutAnnuler: true),
        routes: {
          'POST /reservations/r-1/annuler': const Simulee(409, {
            'message': 'Cette réservation ne peut plus être annulée.',
          }),
        },
      );
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Annuler la demande'));
      await passerTransition(tester);
      await toucher(tester, find.text('Oui, annuler'));
      await passerTransition(tester);
      expect(
        find.text('Cette réservation ne peut plus être annulée.'),
        findsOneWidget,
      );
      expect(find.text('Annuler cette réservation ?'), findsOneWidget);
      expect(find.text('Oui, annuler'), findsOneWidget);
    });

    testWidgets('429 : le message du serveur s\'affiche', (tester) async {
      final serveur = _serveur(
        _res(peutAnnuler: true),
        routes: {
          'POST /reservations/r-1/annuler': const Simulee(429, {
            'message': 'Trop de tentatives. Réessayez dans 30 secondes.',
          }),
        },
      );
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Annuler la demande'));
      await passerTransition(tester);
      await toucher(tester, find.text('Oui, annuler'));
      await passerTransition(tester);
      expect(
        find.text('Trop de tentatives. Réessayez dans 30 secondes.'),
        findsOneWidget,
      );
    });
  });
}
