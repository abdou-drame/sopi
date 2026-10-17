import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/inscription_prestataire/depot_inscription.dart';
import 'package:sopi/features/inscription_prestataire/formulaire_inscription.dart';
import 'package:sopi/features/inscription_prestataire/modeles.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

late ProviderContainer _conteneur;
late AdaptateurSimule _serveur;

void _agrandirEcran(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 1500);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

Future<void> _lancer(
  WidgetTester tester, {
  Simulee quartiers = const Simulee(200, quartiersApi),
}) async {
  _agrandirEcran(tester);
  _serveur = AdaptateurSimule({
    'GET /metiers': const Simulee(200, metiersApi),
    'GET /quartiers': quartiers,
  });
  _conteneur = await lancerAcces(
    tester,
    _serveur,
    chemin: Chemins.inscriptionPrestataire,
  );
}

DossierInscription _dossier() => _conteneur.read(formulaireInscriptionProvider);

Finder _selecteur() => find.byKey(const ValueKey('champ-quartier'));

Finder _champAutre() => find.descendant(
  of: find.byKey(const ValueKey('champ-autre-quartier')),
  matching: find.byType(TextField),
);

Finder _continuer() => find.widgetWithText(FilledButton, 'Continuer');

Future<void> _ouvrirFeuille(WidgetTester tester) async {
  await toucher(tester, _selecteur());
  await passerTransition(tester);
}

Future<void> _choisirAutre(WidgetTester tester) async {
  await _ouvrirFeuille(tester);
  await toucher(tester, find.text('Autre quartier'));
  await passerTransition(tester);
}

Future<void> _saisirAutre(WidgetTester tester, String texte) async {
  await tester.ensureVisible(_champAutre());
  await tester.enterText(_champAutre(), texte);
  await tester.pump();
}

/// Remplit l'entreprise, le métier et l'adresse (le dernier champ de saisie).
Future<void> _remplirAutresChamps(WidgetTester tester) async {
  await saisir(tester, 0, 'Salon Awa');
  await toucher(tester, find.text('Coiffure'));
  await saisir(tester, find.byType(TextField).evaluate().length - 1, 'Rue 10');
}

void main() {
  setUpAll(initialiserFormats);

  group('Quartier : liste reçue', () {
    testWidgets(
      'demande la liste complète (?tous=1) et propose « Autre quartier » en dernier',
      (tester) async {
        await _lancer(tester);

        final appels = _serveur.requetes.where((r) => r.path == '/quartiers');
        expect(appels, hasLength(1));
        expect(appels.single.queryParameters, {'tous': '1'});

        await _ouvrirFeuille(tester);
        final titres = tester
            .widgetList<ListTile>(find.byType(ListTile))
            .map((t) => (t.title as Text).data)
            .toList();
        expect(titres, [
          'Jaxaay',
          'Keur Massar Nord',
          'Malika',
          'Autre quartier',
        ]);
      },
    );

    testWidgets('choisir un quartier de la liste le met dans le dossier', (
      tester,
    ) async {
      await _lancer(tester);
      await _ouvrirFeuille(tester);
      await toucher(tester, find.text('Malika'));
      await passerTransition(tester);

      expect(_dossier().quartier, 'Malika');
      expect(_dossier().quartierAutre, isFalse);
      expect(find.text('Malika'), findsOneWidget);
      expect(_champAutre(), findsNothing);
    });
  });

  group('Quartier : « Autre quartier »', () {
    testWidgets(
      'fait apparaître un champ texte, la valeur envoyée est la saisie nettoyée',
      (tester) async {
        await _lancer(tester);
        expect(_champAutre(), findsNothing);

        await _choisirAutre(tester);
        expect(find.text('Autre quartier'), findsOneWidget);
        expect(_champAutre(), findsOneWidget);

        await _saisirAutre(tester, '  Zone   Test  ');
        expect(_dossier().quartierAutre, isTrue);
        expect(_dossier().quartierNettoye, 'Zone Test');

        await _remplirAutresChamps(tester);
        await toucher(tester, _continuer());
        expect(cheminCourant(tester), Chemins.inscriptionInformations);
      },
    );

    testWidgets('champ vide : erreur, on reste sur l\'étape', (tester) async {
      await _lancer(tester);
      await _choisirAutre(tester);
      await _remplirAutresChamps(tester);
      await toucher(tester, _continuer());

      expect(find.text('Indiquez votre quartier.'), findsOneWidget);
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
    });

    testWidgets('espaces seuls = vide ; 1 caractère = trop court', (
      tester,
    ) async {
      await _lancer(tester);
      await _choisirAutre(tester);
      await _remplirAutresChamps(tester);
      await _saisirAutre(tester, '    ');
      await toucher(tester, _continuer());
      expect(find.text('Indiquez votre quartier.'), findsOneWidget);

      await _saisirAutre(tester, ' A ');
      expect(
        find.text('Le quartier doit contenir au moins 2 caractères.'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
    });

    testWidgets('plus de 100 caractères : refusé ; 100 : accepté', (
      tester,
    ) async {
      await _lancer(tester);
      await _choisirAutre(tester);
      await _remplirAutresChamps(tester);
      await _saisirAutre(tester, 'x' * 101);
      await toucher(tester, _continuer());
      expect(
        find.text('Le quartier ne doit pas dépasser 100 caractères.'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);

      await _saisirAutre(tester, 'x' * 100);
      await toucher(tester, _continuer());
      expect(cheminCourant(tester), Chemins.inscriptionInformations);
    });
  });

  group('Quartier : liste indisponible', () {
    for (final cas in {
      'liste vide': const Simulee(200, {'data': <String>[]}),
      'erreur serveur': const Simulee(500, {'message': 'Erreur.'}),
    }.entries) {
      testWidgets(
        '${cas.key} : champ texte direct, message discret, inscription possible',
        (tester) async {
          await _lancer(tester, quartiers: cas.value);

          expect(_selecteur(), findsNothing);
          expect(_champAutre(), findsOneWidget);
          expect(
            find.byKey(const ValueKey('liste-quartiers-indisponible')),
            findsOneWidget,
          );

          await toucher(tester, _continuer());
          expect(find.text('Indiquez votre quartier.'), findsOneWidget);

          await _saisirAutre(tester, ' Zone  Libre ');
          await _remplirAutresChamps(tester);
          await toucher(tester, _continuer());
          expect(_dossier().quartierNettoye, 'Zone Libre');
          expect(cheminCourant(tester), Chemins.inscriptionInformations);
        },
      );
    }

    testWidgets('réseau coupé : le prestataire n\'est pas bloqué', (
      tester,
    ) async {
      _agrandirEcran(tester);
      _serveur = AdaptateurSimule({
        'GET /metiers': const Simulee(200, metiersApi),
      })..reseauCoupe = true;
      _conteneur = await lancerAcces(
        tester,
        _serveur,
        chemin: Chemins.inscriptionPrestataire,
      );

      expect(_champAutre(), findsOneWidget);
      expect(
        find.byKey(const ValueKey('liste-quartiers-indisponible')),
        findsOneWidget,
      );
    });
  });

  group('Quartier : envoi', () {
    testWidgets(
      'POST /prestataires/inscription reçoit le quartier choisi ou saisi, sans espaces en trop',
      (tester) async {
        await _lancer(tester);
        _serveur.routes['POST /prestataires/inscription'] = const Simulee(201, {
          'jeton_suivi': 'jeton-abc',
        });
        const base = DossierInscription(
          nomEntreprise: 'Salon Awa',
          metierCode: 'coiffure',
          adresse: 'Rue 10',
          prenom: 'Awa',
          nom: 'Ndiaye',
          telephone: '77 123 45 67',
          email: 'awa@exemple.sn',
          motDePasse: 'motdepasse',
          confirmation: 'motdepasse',
        );
        final depot = _conteneur.read(depotInscriptionProvider);

        await tester.runAsync(() async {
          await depot.inscrire(base.copie(quartier: 'Malika'));
          await depot.inscrire(
            base.copie(quartier: '  Zone   Libre ', quartierAutre: true),
          );
        });

        final envois = _serveur.requetes
            .where((r) => r.path == '/prestataires/inscription')
            .map((r) => (r.data as FormData).fields)
            .map((f) => {for (final e in f) e.key: e.value})
            .toList();
        expect(envois.map((c) => c['quartier']), ['Malika', 'Zone Libre']);
      },
    );
  });
}
