import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/widgets/en_tete_ecran.dart';
import 'package:sopi/features/inscription_prestataire/formulaire_inscription.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

Future<AdaptateurSimule> _lancer(
  WidgetTester tester, {
  String chemin = Chemins.inscriptionPrestataire,
  Map<String, Simulee> routes = const {},
}) async {
  tester.view.physicalSize = const Size(390, 1500);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final s = AdaptateurSimule({
    'GET /metiers': const Simulee(200, metiersApi),
    'GET /quartiers': const Simulee(200, quartiersApi),
    ...routes,
  });
  conteneurCourant = await lancerAcces(tester, s, chemin: chemin);
  return s;
}

/// Conteneur Riverpod du dernier [_lancer] (pour lire l'état du formulaire).
late ProviderContainer conteneurCourant;

Future<void> _remplirEtape1(WidgetTester tester) async {
  await saisir(tester, 0, 'Salon Awa');
  await toucher(tester, find.text('Coiffure'));
  await toucher(tester, find.byKey(const ValueKey('champ-quartier')));
  await passerTransition(tester);
  await toucher(tester, find.text('Malika'));
  await passerTransition(tester);
  await saisir(tester, 1, 'Rue 10, Malika');
}

Future<void> _remplirEtape2(WidgetTester tester) async {
  await saisir(tester, 0, 'Awa');
  await saisir(tester, 1, 'Ndiaye');
  await saisir(tester, 2, '77 123 45 67');
  await saisir(tester, 3, 'awa@exemple.sn');
  await saisir(tester, 4, 'motdepasse');
  await saisir(tester, 5, 'motdepasse');
}

Finder _continuer() => find.widgetWithText(FilledButton, 'Continuer');

Color? _couleurSegment(WidgetTester tester, int i) {
  final boite = tester.widget<Container>(find.byKey(ValueKey('segment-$i')));
  return (boite.decoration as BoxDecoration).color;
}

void main() {
  setUpAll(initialiserFormats);

  group('Étape 1 — Votre activité', () {
    testWidgets('affiche la progression et les métiers venant de l\'API', (
      tester,
    ) async {
      await _lancer(
        tester,
        routes: {
          'GET /metiers': const Simulee(200, {
            'data': [
              {'id': 'x', 'code': 'plomberie', 'nom': 'Plomberie'},
            ],
          }),
        },
      );
      expect(find.text('Étape 1 sur 3'), findsOneWidget);
      expect(find.textContaining('activité'), findsOneWidget);
      expect(find.text("Nom de l'entreprise"), findsOneWidget);
      expect(find.text('Plomberie'), findsOneWidget);
      expect(find.text('Coiffure'), findsNothing);
      expect(find.text('Adresse'), findsOneWidget);
      expect(_couleurSegment(tester, 1), isNot(_couleurSegment(tester, 2)));
      expect(_couleurSegment(tester, 2), _couleurSegment(tester, 3));
    });

    testWidgets('champs vides : erreurs en français, on reste sur place', (
      tester,
    ) async {
      await _lancer(tester);
      await toucher(tester, _continuer());
      expect(
        find.text("Le nom de l'entreprise est obligatoire."),
        findsOneWidget,
      );
      expect(find.text('Choisissez votre métier.'), findsOneWidget);
      expect(find.text('Choisissez votre quartier.'), findsOneWidget);
      expect(find.text("L'adresse est obligatoire."), findsOneWidget);
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
    });

    testWidgets('l\'erreur disparaît dès que le champ est corrigé', (
      tester,
    ) async {
      await _lancer(tester);
      await toucher(tester, _continuer());
      await saisir(tester, 0, 'Salon Awa');
      expect(
        find.text("Le nom de l'entreprise est obligatoire."),
        findsNothing,
      );
      await toucher(tester, find.text('Coiffure'));
      expect(find.text('Choisissez votre métier.'), findsNothing);
    });

    testWidgets('nom d\'entreprise trop long refusé', (tester) async {
      await _lancer(tester);
      await _remplirEtape1(tester);
      await saisir(tester, 0, 'x' * 151);
      await toucher(tester, _continuer());
      expect(
        find.text(
          "Le nom de l'entreprise ne doit pas dépasser 150 caractères.",
        ),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
    });

    testWidgets('étape complète : passe à l\'étape 2', (tester) async {
      await _lancer(tester);
      await _remplirEtape1(tester);
      await toucher(tester, _continuer());
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionInformations);
      expect(find.text('Étape 2 sur 3'), findsOneWidget);
    });

    testWidgets('le retour de l\'étape 1 ramène à la connexion', (
      tester,
    ) async {
      await _lancer(tester);
      await toucher(tester, find.byType(BoutonRetour));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.connexion);
    });
  });

  group('Étape 2 — Vos informations', () {
    testWidgets('champs vides : toutes les erreurs', (tester) async {
      await _lancer(tester, chemin: Chemins.inscriptionInformations);
      expect(find.text('Étape 2 sur 3'), findsOneWidget);
      await toucher(tester, _continuer());
      expect(find.text('Le prénom est obligatoire.'), findsOneWidget);
      expect(find.text('Le nom est obligatoire.'), findsOneWidget);
      expect(
        find.text('Le numéro de téléphone est obligatoire.'),
        findsOneWidget,
      );
      expect(find.text("L'adresse e-mail est obligatoire."), findsOneWidget);
      expect(find.text('Le mot de passe est obligatoire.'), findsOneWidget);
      expect(find.text('Confirmez votre mot de passe.'), findsOneWidget);
      expect(cheminCourant(tester), Chemins.inscriptionInformations);
    });

    testWidgets('téléphone, e-mail, mot de passe et confirmation invalides', (
      tester,
    ) async {
      await _lancer(tester, chemin: Chemins.inscriptionInformations);
      await _remplirEtape2(tester);
      await saisir(tester, 2, '12 345');
      await saisir(tester, 3, 'pas-un-email');
      await saisir(tester, 4, 'court');
      await saisir(tester, 5, 'autre');
      await toucher(tester, _continuer());
      expect(find.textContaining('mobile sénégalais valide'), findsOneWidget);
      expect(find.text("L'adresse e-mail n'est pas valide."), findsOneWidget);
      expect(
        find.text('Le mot de passe doit contenir au moins 8 caractères.'),
        findsOneWidget,
      );
      expect(
        find.text('Les deux mots de passe ne sont pas identiques.'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.inscriptionInformations);
    });

    testWidgets('l\'e-mail est obligatoire (pas de « facultatif »)', (
      tester,
    ) async {
      await _lancer(tester, chemin: Chemins.inscriptionInformations);
      expect(
        find.text('E-mail (pour le suivi de votre dossier)'),
        findsOneWidget,
      );
      expect(find.textContaining('facultatif'), findsNothing);
    });

    testWidgets('étape complète : passe à l\'étape 3', (tester) async {
      await _lancer(tester, chemin: Chemins.inscriptionInformations);
      await _remplirEtape2(tester);
      await toucher(tester, _continuer());
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionJustificatifs);
    });
  });

  group('Navigation entre étapes', () {
    testWidgets('1 → 2 → retour : rien n\'est perdu', (tester) async {
      await _lancer(tester);
      await _remplirEtape1(tester);
      await toucher(tester, _continuer());
      await passerTransition(tester);
      await _remplirEtape2(tester);

      await toucher(tester, find.byType(BoutonRetour));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
      expect(find.text('Salon Awa'), findsOneWidget);
      expect(find.text('Rue 10, Malika'), findsOneWidget);
      expect(find.text('Malika'), findsOneWidget);
      final d = conteneurCourant.read(formulaireInscriptionProvider);
      expect(d.metierCode, 'coiffure');
      expect(d.quartier, 'Malika');

      // On repart vers l'étape 2 : les champs saisis y sont toujours.
      await toucher(tester, _continuer());
      await passerTransition(tester);
      expect(find.text('Awa'), findsOneWidget);
      expect(find.text('Ndiaye'), findsOneWidget);
      expect(find.text('77 123 45 67'), findsOneWidget);
      expect(find.text('awa@exemple.sn'), findsOneWidget);
    });
  });
}
