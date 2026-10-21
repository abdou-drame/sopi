import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/widgets/en_tete_ecran.dart';
import 'package:sopi/features/inscription_prestataire/formulaire_inscription.dart';
import 'package:sopi/features/inscription_prestataire/modeles.dart';
import 'package:sopi/features/inscription_prestataire/selecteur_justificatif.dart';
import 'package:sopi/features/inscription_prestataire/stockage_jeton_suivi.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

FichierJustificatif _pdf(String nom) => FichierJustificatif(
  nom: nom,
  octets: Uint8List(1000),
  typeMime: 'application/pdf',
);

void main() {
  setUpAll(initialiserFormats);

  testWidgets(
    'parcours complet : connexion → 3 étapes → retours sans perte → envoi → suivi',
    (tester) async {
      tester.view.physicalSize = const Size(390, 1500);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      final serveur = AdaptateurSimule({
        'GET /metiers': const Simulee(200, metiersApi),
        'GET /quartiers': const Simulee(200, quartiersApi),
        'POST /prestataires/inscription': const Simulee(201, {
          'jeton_suivi': 'jeton-abc',
        }),
        'GET /suivi/jeton-abc': const Simulee(200, {
          'nomEntreprise': 'Salon Awa',
          'metier': 'coiffure',
          'statutCompte': 'en_attente',
          'dateSoumission': '2026-10-16T09:30:00Z',
          'justificatifs': [
            {'typeDocument': 'cni', 'statut': 'en_attente'},
            {'typeDocument': 'diplome', 'statut': 'en_attente'},
          ],
        }),
      });
      final stockage = StockageJetonSuiviMemoire();
      final fichiers = [_pdf('cni.pdf'), _pdf('diplome.pdf')];
      final conteneur = await lancerAcces(
        tester,
        serveur,
        stockageSuivi: stockage,
        surcharges: [
          selecteurJustificatifProvider.overrideWithValue(
            (source) async => fichiers.removeAt(0),
          ),
        ],
      );
      await avancer(tester, 200);

      conteneur
          .read(formulaireInscriptionProvider.notifier)
          .mettreAJour(
            (d) => d.copie(
              nomEntreprise: 'Salon Awa',
              metierCode: 'coiffure',
              quartier: 'Malika',
              adresse: 'Rue 10',
              prenom: 'Awa',
              nom: 'Ndiaye',
              telephone: '77 123 45 67',
              email: 'awa@exemple.sn',
              motDePasse: 'motdepasse',
              confirmation: 'motdepasse',
            ),
          );
      await avancer(tester, 100);

      // 1. Depuis la connexion.
      await toucher(tester, find.bySemanticsLabel('Devenir prestataire'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
      Finder continuer(String t) => find.widgetWithText(FilledButton, t);

      // 1 → 2 → 3.
      await toucher(tester, continuer('Continuer'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionInformations);
      await toucher(tester, continuer('Continuer'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionJustificatifs);

      // Ajout des deux pièces obligatoires.
      for (final type in [TypeJustificatif.cni, TypeJustificatif.diplome]) {
        await toucher(
          tester,
          find.descendant(
            of: find.byKey(ValueKey('ligne-${type.valeurApi}')),
            matching: find.byType(TextButton),
          ),
        );
        await passerTransition(tester);
        await toucher(tester, find.text('Fichier'));
        await passerTransition(tester);
      }
      expect(find.text('Changer'), findsNWidgets(2));

      // 3 → 2 → 1 → 2 → 3 : fichiers et saisies restent.
      await toucher(tester, find.byType(BoutonRetour));
      await passerTransition(tester);
      await toucher(tester, find.byType(BoutonRetour));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
      expect(find.text('Salon Awa'), findsOneWidget);
      await toucher(tester, continuer('Continuer'));
      await passerTransition(tester);
      expect(find.text('awa@exemple.sn'), findsOneWidget);
      await toucher(tester, continuer('Continuer'));
      await passerTransition(tester);
      expect(find.text('Changer'), findsNWidgets(2));

      // Envoi : jeton rangé, suivi affiché, formulaire vidé.
      await toucher(tester, continuer('Envoyer mon dossier'));
      await avancer(tester, 600);
      expect(stockage.valeur, 'jeton-abc');
      expect(cheminCourant(tester), Chemins.suiviDossier);
      expect(find.text('Salon Awa'), findsOneWidget);
      expect(conteneur.read(formulaireInscriptionProvider).motDePasse, isEmpty);
      expect(conteneur.read(formulaireInscriptionProvider).fichiers, isEmpty);
    },
  );
}
