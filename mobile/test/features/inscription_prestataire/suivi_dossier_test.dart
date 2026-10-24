import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/inscription_prestataire/stockage_jeton_suivi.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

const _suivi = 'GET /suivi/jeton-abc';

Map<String, Object?> _dossier({String statut = 'en_attente', String? motif}) =>
    {
      'nomEntreprise': 'Salon Awa',
      'metier': 'coiffure',
      'statutCompte': statut,
      'motifRefus': ?motif,
      'dateSoumission': '2026-10-16T09:30:00Z',
      'justificatifs': [
        {'typeDocument': 'cni', 'statut': 'valide'},
        {'typeDocument': 'diplome', 'statut': 'en_attente'},
      ],
    };

Future<StockageJetonSuiviMemoire> _arriver(
  WidgetTester tester,
  Map<String, Simulee> routes, {
  String supportEmail = '',
  AdaptateurSimule? serveur,
}) async {
  tester.view.physicalSize = const Size(390, 1500);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final stockage = StockageJetonSuiviMemoire('jeton-abc');
  await lancerAcces(
    tester,
    serveur ??
        AdaptateurSimule({
          'GET /metiers': const Simulee(200, metiersApi),
          ...routes,
        }),
    chemin: Chemins.suiviDossier,
    stockageSuivi: stockage,
    supportEmail: supportEmail,
  );
  await avancer(tester, 300);
  return stockage;
}

void main() {
  setUpAll(initialiserFormats);

  group('Suivi du dossier', () {
    testWidgets('en cours : entreprise, métier, date et statut par document', (
      tester,
    ) async {
      await _arriver(tester, {_suivi: Simulee(200, _dossier())});
      expect(titre("en cours d'examen"), findsOneWidget);
      expect(find.text('Salon Awa'), findsOneWidget);
      expect(find.text('Coiffure'), findsOneWidget);
      expect(find.text('16 oct. 2026'), findsOneWidget);
      expect(find.text('Validé'), findsOneWidget);
      expect(find.text('En attente'), findsOneWidget);
      expect(find.text('Actualiser'), findsOneWidget);
      expect(find.text('Retour à la connexion'), findsOneWidget);
    });

    testWidgets(
      'lien de suivi : affiché sous les détails, Copier le place dans le presse-papiers',
      (tester) async {
        String? copie;
        tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          SystemChannels.platform,
          (appel) async {
            if (appel.method == 'Clipboard.setData') {
              copie = (appel.arguments as Map)['text'] as String?;
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
        await _arriver(tester, {_suivi: Simulee(200, _dossier())});
        expect(find.text('Votre lien de suivi personnel'), findsOneWidget);
        expect(find.textContaining('/suivi/jeton-abc'), findsOneWidget);
        await toucher(tester, find.text('Copier'));
        await avancer(tester, 300);
        expect(copie, endsWith('/suivi/jeton-abc'));
        expect(find.text('Lien copié'), findsOneWidget);
      },
    );

    testWidgets('Actualiser relit le dossier', (tester) async {
      final serveur = AdaptateurSimule({
        'GET /metiers': const Simulee(200, metiersApi),
        _suivi: Simulee(200, _dossier()),
      });
      await _arriver(tester, const {}, serveur: serveur);
      int lectures() => serveur.requetes
          .where((r) => '${r.method} ${r.path}' == _suivi)
          .length;
      expect(lectures(), 1);
      await toucher(tester, find.text('Actualiser'));
      await avancer(tester, 300);
      expect(lectures(), 2);
    });

    testWidgets('refusé : motif et support si configuré', (tester) async {
      await _arriver(tester, {
        _suivi: Simulee(
          200,
          _dossier(statut: 'refuse', motif: 'Pièce illisible.'),
        ),
      }, supportEmail: 'support@sopi.sn');
      expect(titre('non retenu'), findsOneWidget);
      expect(find.text('Pièce illisible.'), findsOneWidget);
      expect(find.text('Contacter le support'), findsOneWidget);
      expect(find.text('Actualiser'), findsNothing);
    });

    testWidgets(
      'refusé : motif propre à chaque document rejeté, motif global conservé',
      (tester) async {
        await _arriver(tester, {
          _suivi: Simulee(200, {
            ..._dossier(statut: 'refuse', motif: 'Dossier incomplet.'),
            'justificatifs': [
              {'typeDocument': 'cni', 'statut': 'valide', 'motifRejet': null},
              {
                'typeDocument': 'diplome',
                'statut': 'rejete',
                'motifRejet': 'Photo floue.',
              },
            ],
          }),
        });
        expect(find.text('Dossier incomplet.'), findsOneWidget);
        expect(find.text('Photo floue.'), findsOneWidget);
        expect(
          find.byKey(const ValueKey('motif-document-diplome')),
          findsOneWidget,
        );
        expect(find.byKey(const ValueKey('motif-document-cni')), findsNothing);
      },
    );

    testWidgets('refusé sans SUPPORT_EMAIL : pas de bouton support', (
      tester,
    ) async {
      await _arriver(tester, {
        _suivi: Simulee(
          200,
          _dossier(statut: 'refuse', motif: 'Non conforme.'),
        ),
      });
      expect(find.text('Non conforme.'), findsOneWidget);
      expect(find.text('Contacter le support'), findsNothing);
      expect(find.text('Retour à la connexion'), findsOneWidget);
    });

    testWidgets('accepté : message de succès, retour efface le jeton', (
      tester,
    ) async {
      final stockage = await _arriver(tester, {
        _suivi: Simulee(200, _dossier(statut: 'actif')),
      });
      expect(titre('Dossier\naccepté'), findsOneWidget);
      await toucher(tester, find.text('Retour à la connexion'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.connexion);
      expect(stockage.valeur, isNull);
    });

    testWidgets('jeton inconnu (404) : introuvable, jeton oublié', (
      tester,
    ) async {
      final stockage = await _arriver(tester, {
        _suivi: const Simulee(404, {'message': 'Dossier introuvable.'}),
      });
      expect(titre('Dossier\nintrouvable'), findsOneWidget);
      await toucher(tester, find.text('Retour à la connexion'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.connexion);
      expect(stockage.valeur, isNull);
    });

    testWidgets('réseau coupé : Réessayer, jeton conservé', (tester) async {
      final serveur = AdaptateurSimule({
        'GET /metiers': const Simulee(200, metiersApi),
        _suivi: Simulee(200, _dossier()),
      })..reseauCoupe = true;
      final stockage = await _arriver(tester, const {}, serveur: serveur);
      expect(find.text('Réessayer'), findsOneWidget);
      serveur.reseauCoupe = false;
      await toucher(tester, find.text('Réessayer'));
      await avancer(tester, 300);
      expect(titre("en cours d'examen"), findsOneWidget);
      expect(stockage.valeur, 'jeton-abc');
    });
  });

  group('Connexion et dossier', () {
    Future<void> lancer(
      WidgetTester tester,
      String statut, {
      String? jeton,
    }) async {
      await lancerAcces(
        tester,
        AdaptateurSimule({
          'GET /metiers': const Simulee(200, metiersApi),
          'POST /auth/connexion': Simulee(403, {
            'message': 'Accès refusé.',
            'statutCompte': statut,
          }),
          'POST /auth/deconnexion': const Simulee(204, null),
          _suivi: Simulee(
            200,
            _dossier(statut: statut == 'refuse' ? 'refuse' : 'en_attente'),
          ),
        }),
        stockageSuivi: StockageJetonSuiviMemoire(jeton),
      );
      await avancer(tester, 200);
    }

    Future<void> seConnecter(WidgetTester tester) async {
      await saisir(tester, 0, 'awa@exemple.sn');
      await saisir(tester, 1, 'secret');
      await toucher(tester, find.widgetWithText(FilledButton, 'Se connecter'));
      await passerTransition(tester);
    }

    testWidgets('pas de lien « Suivre mon dossier » sans jeton', (
      tester,
    ) async {
      await lancer(tester, 'en_attente');
      expect(find.text('Suivre mon dossier'), findsNothing);
    });

    testWidgets('lien « Suivre mon dossier » mène au suivi', (tester) async {
      await lancer(tester, 'en_attente', jeton: 'jeton-abc');
      await toucher(tester, find.text('Suivre mon dossier'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.suiviDossier);
    });

    for (final statut in ['en_attente', 'refuse']) {
      testWidgets('403 $statut avec jeton : suivi du dossier', (tester) async {
        await lancer(tester, statut, jeton: 'jeton-abc');
        await seConnecter(tester);
        expect(cheminCourant(tester), Chemins.suiviDossier);
      });

      testWidgets('403 $statut sans jeton : compte restreint', (tester) async {
        await lancer(tester, statut);
        await seConnecter(tester);
        expect(cheminCourant(tester), Chemins.compteRestreint);
      });
    }

    for (final statut in ['suspendu', 'banni']) {
      testWidgets('403 $statut avec jeton : compte restreint', (tester) async {
        await lancer(tester, statut, jeton: 'jeton-abc');
        await seConnecter(tester);
        expect(cheminCourant(tester), Chemins.compteRestreint);
      });
    }
  });
}

/// Le titre en deux lignes est un texte riche : on le cherche par son contenu.
Finder titre(String texte) => find.textContaining(texte, findRichText: true);
