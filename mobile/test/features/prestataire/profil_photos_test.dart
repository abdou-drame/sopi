import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/compte/modeles_litige.dart';
import 'package:sopi/features/compte/selecteur_photo.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_prestataire.dart';

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Map<String, Simulee> _base(Map<String, Simulee> plus) => {
  'GET /me': Simulee(200, utilisateurPrestataire()),
  'GET /prestataire/solde': const Simulee(200, {'solde': 0}),
  'GET /prestataire/reservations': Simulee(200, pageDemandesApi([])),
  'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
  ...plus,
};

Future<void> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur,
  String chemin, {
  List<PhotoChoisie> photos = const [],
}) async {
  _vue390(tester);
  final file = [...photos];
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: chemin,
    surcharges: [
      selecteurPhotoProvider.overrideWithValue(
        (source) async => file.isEmpty ? null : file.removeAt(0),
      ),
    ],
  );
  await passerTransition(tester);
}

PhotoChoisie _photo({String type = 'image/jpeg', String nom = 'salon.jpg'}) =>
    PhotoChoisie(
      nom: nom,
      octets: Uint8List.fromList([1, 2, 3]),
      typeMime: type,
    );

Map<String, Object?> _liste(List<String> ids) => {
  'data': [
    for (final id in ids) {'id': id, 'url': '/api/v1/prestataire/photos/$id'},
  ],
};

void main() {
  setUpAll(initialiserFormats);

  group('Profil prestataire', () {
    testWidgets('identité lue dans /me, pastille et note', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/avis': const Simulee(200, {
            'data': [],
            'meta': {'current_page': 1, 'last_page': 1},
            'noteMoyenne': 4.9,
            'nombreAvis': 12,
          }),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.profilPrestataire);
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('Coiffure · Keur Massar Nord'), findsOneWidget);
      expect(find.text('Compte actif'), findsOneWidget);
      expect(find.text('4,9'), findsOneWidget);
      // Pas de pastille de nombre sur les signalements.
      expect(find.text('Signalements reçus'), findsOneWidget);
    });

    testWidgets('sans note disponible, la ligne Avis reçus reste affichée', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        AdaptateurSimule(_base({})),
        Chemins.profilPrestataire,
      );
      expect(find.text('Avis reçus'), findsOneWidget);
    });

    for (final (libelle, chemin) in [
      ('Mes services et tarifs', Chemins.services),
      ('Mes disponibilités', Chemins.disponibilites),
      ("Photos de l'entreprise", Chemins.photos),
      ('Avis reçus', Chemins.avisRecus),
      ('Signalements reçus', Chemins.litigesRecus),
      ('Modifier mon profil', Chemins.profilModifier),
      ('Changer le mot de passe', Chemins.motDePasseChanger),
      ('Notifications', Chemins.notifications),
      ('Aide et support', Chemins.aide),
    ]) {
      testWidgets('« $libelle » ouvre $chemin', (tester) async {
        await _ouvrir(
          tester,
          AdaptateurSimule(
            _base({
              'GET /prestataire/prestations': const Simulee(200, {'data': []}),
              'GET /prestataire/agenda': const Simulee(200, {'data': []}),
              'GET /prestataire/photos': const Simulee(200, {'data': []}),
              'GET /prestataire/avis': const Simulee(200, {'data': []}),
              'GET /prestataire/litiges': const Simulee(200, {'data': []}),
            }),
          ),
          Chemins.profilPrestataire,
        );
        await tester.ensureVisible(find.text(libelle));
        await tester.tap(find.text(libelle));
        await passerTransition(tester);
        expect(cheminCourant(tester), chemin);
      });
    }

    testWidgets('Se déconnecter demande une confirmation', (tester) async {
      await _ouvrir(
        tester,
        AdaptateurSimule(_base({})),
        Chemins.profilPrestataire,
      );
      await tester.ensureVisible(find.text('Se déconnecter'));
      await tester.tap(find.text('Se déconnecter'));
      await avancer(tester, 300);
      expect(find.text('Se déconnecter ?'), findsOneWidget);
      await tester.tap(find.text('Annuler'));
      await avancer(tester, 300);
      expect(find.text('Se déconnecter ?'), findsNothing);
    });
  });

  group('Photos de l’entreprise', () {
    testWidgets('grille des photos et tuile d’ajout', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/photos': Simulee(200, _liste(['a', 'b'])),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.photos);
      expect(find.byKey(const ValueKey('photo-a')), findsOneWidget);
      expect(find.byKey(const ValueKey('photo-b')), findsOneWidget);
      expect(find.byKey(const ValueKey('ajouter-photo')), findsOneWidget);
      expect(find.textContaining('JPEG, PNG ou WebP'), findsOneWidget);
    });

    testWidgets('ajout : envoi multipart du champ photo', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/photos': Simulee(200, _liste([])),
          'POST /prestataire/photos': const Simulee(201, {'id': 'n'}),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.photos, photos: [_photo()]);
      serveur.routes['GET /prestataire/photos'] = Simulee(200, _liste(['n']));
      await tester.tap(find.byKey(const ValueKey('ajouter-photo')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Galerie'));
      await avancer(tester, 300);
      final envoi = serveur.requetes.singleWhere((r) => r.method == 'POST');
      expect(envoi.path, '/prestataire/photos');
      final formulaire = envoi.data as FormData;
      expect(formulaire.files.single.key, 'photo');
      expect(formulaire.files.single.value.filename, 'salon.jpg');
      expect(find.byKey(const ValueKey('photo-n')), findsOneWidget);
    });

    testWidgets('refus du serveur : son message est affiché', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/photos': Simulee(200, _liste([])),
          'POST /prestataire/photos': const Simulee(422, {
            'message': 'Vous ne pouvez pas ajouter plus de 6 photos.',
            'errors': {
              'photo': ['Vous ne pouvez pas ajouter plus de 6 photos.'],
            },
          }),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.photos, photos: [_photo()]);
      await tester.tap(find.byKey(const ValueKey('ajouter-photo')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Galerie'));
      await avancer(tester, 300);
      expect(
        find.text('Vous ne pouvez pas ajouter plus de 6 photos.'),
        findsOneWidget,
      );
    });

    testWidgets('suppression : confirmation puis DELETE', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/photos': Simulee(200, _liste(['a', 'b'])),
          'DELETE /prestataire/photos/a': const Simulee(204),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.photos);
      serveur.routes['GET /prestataire/photos'] = Simulee(200, _liste(['b']));
      await tester.tap(find.byKey(const ValueKey('supprimer-a')));
      await avancer(tester, 300);
      expect(find.text('Supprimer cette photo ?'), findsOneWidget);
      // Annuler n'envoie rien.
      await tester.tap(find.text('Annuler'));
      await avancer(tester, 300);
      expect(serveur.requetes.where((r) => r.method == 'DELETE'), isEmpty);

      await tester.tap(find.byKey(const ValueKey('supprimer-a')));
      await avancer(tester, 300);
      await tester.tap(find.text('Supprimer'));
      await avancer(tester, 300);
      expect(
        serveur.requetes.where((r) => r.method == 'DELETE').single.path,
        '/prestataire/photos/a',
      );
      expect(find.byKey(const ValueKey('photo-a')), findsNothing);
      expect(find.byKey(const ValueKey('photo-b')), findsOneWidget);
    });

    testWidgets('erreur de chargement : bouton Réessayer', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/photos': const Simulee(500, {
            'message': 'Erreur interne.',
          }),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.photos);
      expect(find.text('Réessayer'), findsOneWidget);
    });
  });
}
