import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/compte/litige_page.dart';
import 'package:sopi/features/compte/modeles_litige.dart';
import 'package:sopi/features/compte/selecteur_photo.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

final _debut = DateTime.utc(2026, 10, 24, 10);

final _png = base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
);

PhotoChoisie _photo(String nom, {String type = 'image/png', int? taille}) =>
    PhotoChoisie(
      nom: nom,
      octets: taille == null ? _png : Uint8List(taille),
      typeMime: type,
    );

AdaptateurSimule _serveur({Map<String, Simulee> routes = const {}}) =>
    AdaptateurSimule(
      routesClient({
        'GET /reservations/r-1': Simulee(200, {
          'data': reservationApi(
            'r-1',
            debut: _debut,
            statut: 'confirmee',
            peutAnnuler: false,
          ),
        }),
        'POST /reservations/r-1/litiges': const Simulee(201, {
          'data': {'id': 'l-1', 'statut': 'ouvert'},
        }),
        ...routes,
      }),
    );

Future<void> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur, {
  List<PhotoChoisie> photos = const [],
}) async {
  tester.view.physicalSize = const Size(390, 1500);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final file = [...photos];
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.litige('r-1'),
    surcharges: [
      selecteurPhotoProvider.overrideWithValue(
        (source) async => file.isEmpty ? null : file.removeAt(0),
      ),
    ],
  );
  await passerTransition(tester);
}

Future<void> _ajouterPhoto(WidgetTester tester) async {
  await tester.tap(find.bySemanticsLabel('Ajouter une photo'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Galerie'));
  await tester.pumpAndSettle();
}

Future<void> _remplir(
  WidgetTester tester, {
  String texte = 'Il est venu en retard.',
}) async {
  await tester.tap(find.text('Prestataire absent'));
  await tester.pump();
  await tester.enterText(find.byType(TextField), texte);
  await tester.pump();
}

List<RequestOptions> _envois(AdaptateurSimule s) =>
    s.requetes.where((r) => r.method == 'POST').toList();

void main() {
  setUpAll(initialiserFormats);

  testWidgets('affiche la réservation concernée et les quatre motifs', (
    tester,
  ) async {
    await _ouvrir(tester, _serveur());
    expect(
      find.text('Réservation : Salon Fatou · Coupe femme · 24 oct.'),
      findsOneWidget,
    );
    for (final m in motifsLitige) {
      expect(find.text(m), findsOneWidget);
    }
    expect(find.text('Envoyer le signalement'), findsOneWidget);
  });

  testWidgets('sans motif ni description : refus local, aucun envoi', (
    tester,
  ) async {
    final serveur = _serveur();
    await _ouvrir(tester, serveur);
    await tester.tap(find.text('Envoyer le signalement'));
    await tester.pump();
    expect(find.text("Choisissez ce qui s'est passé."), findsOneWidget);
    expect(
      find.text('Décrivez la situation en quelques mots.'),
      findsOneWidget,
    );
    expect(_envois(serveur), isEmpty);
  });

  testWidgets('description de moins de 10 caractères : refusée', (
    tester,
  ) async {
    final serveur = _serveur();
    await _ouvrir(tester, serveur);
    await _remplir(tester, texte: 'Court');
    await tester.tap(find.text('Envoyer le signalement'));
    await tester.pump();
    expect(
      find.text('La description doit contenir au moins 10 caractères.'),
      findsOneWidget,
    );
    expect(_envois(serveur), isEmpty);
  });

  testWidgets('la description est limitée à 1000 caractères avec compteur', (
    tester,
  ) async {
    await _ouvrir(tester, _serveur());
    await tester.enterText(find.byType(TextField), 'a' * 1200);
    await tester.pump();
    final champ = tester.widget<TextField>(find.byType(TextField));
    expect(champ.controller!.text.length, 1000);
    expect(find.text('1000/1000'), findsOneWidget);
  });

  testWidgets('envoi multipart : motif, description et photos', (tester) async {
    final serveur = _serveur();
    await _ouvrir(
      tester,
      serveur,
      photos: [
        _photo('a.png'),
        _photo('b.jpg', type: 'image/jpeg'),
      ],
    );
    await _remplir(tester);
    await _ajouterPhoto(tester);
    await _ajouterPhoto(tester);
    expect(find.byKey(const ValueKey('retirer-photo-1')), findsOneWidget);
    await tester.tap(find.text('Envoyer le signalement'));
    await tester.pumpAndSettle();

    final envoi = _envois(serveur).single;
    expect(envoi.path, '/reservations/r-1/litiges');
    final formulaire = envoi.data as FormData;
    expect(
      formulaire.fields.map((f) => '${f.key}=${f.value}'),
      containsAll([
        'motif=Prestataire absent',
        'description=Il est venu en retard.',
      ]),
    );
    expect(formulaire.files.map((f) => f.key), ['preuves[]', 'preuves[]']);
    expect(formulaire.files.map((f) => f.value.filename), ['a.png', 'b.jpg']);
  });

  testWidgets('succès : écran de confirmation puis retour au détail', (
    tester,
  ) async {
    await _ouvrir(tester, _serveur());
    await _remplir(tester);
    await tester.tap(find.text('Envoyer le signalement'));
    await tester.pumpAndSettle();
    expect(find.text('Signalement envoyé.'), findsOneWidget);
    expect(
      find.text('Un administrateur examinera votre dossier.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Retour à la réservation'));
    await tester.pumpAndSettle();
    expect(find.text('Retour à la réservation'), findsNothing);
  });

  testWidgets('au plus 3 photos : le bouton d ajout disparaît', (tester) async {
    await _ouvrir(
      tester,
      _serveur(),
      photos: [_photo('1.png'), _photo('2.png'), _photo('3.png')],
    );
    for (var i = 0; i < 3; i++) {
      await _ajouterPhoto(tester);
    }
    expect(find.bySemanticsLabel('Ajouter une photo'), findsNothing);
    expect(find.byKey(const ValueKey('retirer-photo-2')), findsOneWidget);
  });

  testWidgets('retirer une photo la supprime de la sélection', (tester) async {
    await _ouvrir(tester, _serveur(), photos: [_photo('1.png')]);
    await _ajouterPhoto(tester);
    await tester.tap(find.byKey(const ValueKey('retirer-photo-0')));
    await tester.pump();
    expect(find.byKey(const ValueKey('retirer-photo-0')), findsNothing);
  });

  testWidgets('format non accepté : refus avec message', (tester) async {
    await _ouvrir(
      tester,
      _serveur(),
      photos: [_photo('a.gif', type: 'image/gif')],
    );
    await _ajouterPhoto(tester);
    expect(find.text(messageFormatPhoto), findsOneWidget);
    expect(find.byKey(const ValueKey('retirer-photo-0')), findsNothing);
  });

  testWidgets('photo de plus de 5 Mo : refus avec message', (tester) async {
    await _ouvrir(
      tester,
      _serveur(),
      photos: [_photo('gros.png', taille: tailleMaxPreuve + 1)],
    );
    await _ajouterPhoto(tester);
    expect(find.text(messageTaillePhoto), findsOneWidget);
  });

  testWidgets('409 : message du serveur affiché, pas de confirmation', (
    tester,
  ) async {
    await _ouvrir(
      tester,
      _serveur(
        routes: {
          'POST /reservations/r-1/litiges': const Simulee(409, {
            'message': 'Un litige est déjà ouvert pour cette réservation.',
          }),
        },
      ),
    );
    await _remplir(tester);
    await tester.tap(find.text('Envoyer le signalement'));
    await tester.pumpAndSettle();
    expect(
      find.text('Un litige est déjà ouvert pour cette réservation.'),
      findsOneWidget,
    );
    expect(find.text('Signalement envoyé.'), findsNothing);
  });

  testWidgets('422 : erreur sous le champ concerné', (tester) async {
    await _ouvrir(
      tester,
      _serveur(
        routes: {
          'POST /reservations/r-1/litiges': const Simulee(422, {
            'message': 'Données invalides.',
            'errors': {
              'description': ['La description est trop courte.'],
            },
          }),
        },
      ),
    );
    await _remplir(tester);
    await tester.tap(find.text('Envoyer le signalement'));
    await tester.pumpAndSettle();
    expect(find.text('La description est trop courte.'), findsOneWidget);
  });

  testWidgets('429 : pas de confirmation, message affiché', (tester) async {
    await _ouvrir(
      tester,
      _serveur(
        routes: {
          'POST /reservations/r-1/litiges': const Simulee(
            429,
            {'message': 'Trop de requêtes.'},
            {'retry-after': '30'},
          ),
        },
      ),
    );
    await _remplir(tester);
    await tester.tap(find.text('Envoyer le signalement'));
    await tester.pumpAndSettle();
    expect(find.text('Trop de requêtes.'), findsOneWidget);
    expect(find.text('Signalement envoyé.'), findsNothing);
  });
}
