import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/api/erreurs.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/inscription_prestataire/formulaire_inscription.dart';
import 'package:sopi/features/inscription_prestataire/modeles.dart';
import 'package:sopi/features/inscription_prestataire/selecteur_justificatif.dart';
import 'package:sopi/features/inscription_prestataire/stockage_jeton_suivi.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

const _inscription = 'POST /prestataires/inscription';

FichierJustificatif _fichier(
  String nom, {
  String mime = 'application/pdf',
  int taille = 1000,
}) => FichierJustificatif(nom: nom, octets: Uint8List(taille), typeMime: mime);

const _dossierComplet = DossierInscription(
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
);

class _Contexte {
  _Contexte(this.serveur, this.conteneur, this.stockage);
  final AdaptateurSimule serveur;
  final ProviderContainer conteneur;
  final StockageJetonSuiviMemoire stockage;

  List<RequestOptions> get envois => serveur.requetes
      .where((r) => '${r.method} ${r.path}' == _inscription)
      .toList();
}

/// Arrive sur l'étape 3 avec un dossier prérempli ; [choix] est ce que renverra le sélecteur de fichiers.
Future<_Contexte> _lancer(
  WidgetTester tester, {
  Map<String, Simulee> routes = const {},
  AdaptateurSimule? serveur,
  FichierJustificatif? Function()? choix,
  bool avecFichiers = false,
}) async {
  tester.view.physicalSize = const Size(390, 1500);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final s =
      serveur ??
      AdaptateurSimule({
        'GET /metiers': const Simulee(200, metiersApi),
        'GET /quartiers': const Simulee(200, quartiersApi),
        ...routes,
      });
  final stockage = StockageJetonSuiviMemoire();
  final conteneur = await lancerAcces(
    tester,
    s,
    chemin: Chemins.inscriptionJustificatifs,
    stockageSuivi: stockage,
    surcharges: [
      selecteurJustificatifProvider.overrideWithValue(
        (source) async => choix?.call(),
      ),
    ],
  );
  conteneur
      .read(formulaireInscriptionProvider.notifier)
      .mettreAJour(
        (_) => avecFichiers
            ? _dossierComplet.copie(
                fichiers: {
                  TypeJustificatif.cni: _fichier('cni.pdf'),
                  TypeJustificatif.diplome: _fichier(
                    'diplome.png',
                    mime: 'image/png',
                  ),
                },
              )
            : _dossierComplet,
      );
  await tester.pump();
  return _Contexte(s, conteneur, stockage);
}

Finder _ligne(TypeJustificatif t) =>
    find.byKey(ValueKey('ligne-${t.valeurApi}'));

Finder _boutonLigne(TypeJustificatif t) =>
    find.descendant(of: _ligne(t), matching: find.byType(OutlinedButton));

Finder _envoyer() => find.widgetWithText(FilledButton, 'Envoyer mon dossier');

Future<void> _ajouter(WidgetTester tester, TypeJustificatif t) async {
  await toucher(tester, _boutonLigne(t));
  await passerTransition(tester);
  await toucher(tester, find.text('Fichier'));
  await passerTransition(tester);
}

void main() {
  setUpAll(initialiserFormats);

  group('Étape 3 — Vos justificatifs', () {
    testWidgets('affiche les trois lignes et la progression', (tester) async {
      await _lancer(tester);
      expect(find.text('Étape 3 sur 3'), findsOneWidget);
      expect(find.textContaining('justificatifs'), findsOneWidget);
      expect(find.text("Carte d'identité"), findsOneWidget);
      expect(find.text('Diplôme ou attestation'), findsOneWidget);
      expect(find.text("Justificatif d'activité"), findsOneWidget);
      expect(
        find.text('Photo de votre local ou registre (facultatif)'),
        findsOneWidget,
      );
      expect(_envoyer(), findsOneWidget);
    });

    testWidgets('le choix de source propose Galerie, Appareil photo, Fichier', (
      tester,
    ) async {
      await _lancer(tester);
      await toucher(tester, _boutonLigne(TypeJustificatif.cni));
      await passerTransition(tester);
      expect(find.text('Galerie'), findsOneWidget);
      expect(find.text('Appareil photo'), findsOneWidget);
      expect(find.text('Fichier'), findsOneWidget);
    });

    testWidgets('un fichier ajouté s\'affiche, le bouton devient « Changer »', (
      tester,
    ) async {
      await _lancer(tester, choix: () => _fichier('ma-cni.pdf'));
      await _ajouter(tester, TypeJustificatif.cni);
      expect(find.text('ma-cni.pdf · ajouté'), findsOneWidget);
      expect(
        find.descendant(
          of: _ligne(TypeJustificatif.cni),
          matching: find.text('Changer'),
        ),
        findsOneWidget,
      );
    });

    testWidgets('format non accepté : message clair, rien d\'ajouté', (
      tester,
    ) async {
      await _lancer(
        tester,
        choix: () => _fichier('image.gif', mime: 'image/gif'),
      );
      await _ajouter(tester, TypeJustificatif.cni);
      expect(find.text(messageFormatJustificatif), findsOneWidget);
      expect(find.textContaining('· ajouté'), findsNothing);
    });

    testWidgets('fichier de plus de 5 Mo refusé', (tester) async {
      await _lancer(
        tester,
        choix: () => _fichier('gros.pdf', taille: tailleMaxJustificatif + 1),
      );
      await _ajouter(tester, TypeJustificatif.cni);
      expect(find.text(messageTailleJustificatif), findsOneWidget);
    });

    testWidgets('exactement 5 Mo est accepté', (tester) async {
      await _lancer(
        tester,
        choix: () => _fichier('limite.pdf', taille: tailleMaxJustificatif),
      );
      await _ajouter(tester, TypeJustificatif.cni);
      expect(find.text('limite.pdf · ajouté'), findsOneWidget);
    });

    testWidgets('annuler le choix ne change rien', (tester) async {
      await _lancer(tester, choix: () => null);
      await _ajouter(tester, TypeJustificatif.cni);
      expect(find.textContaining('· ajouté'), findsNothing);
    });

    testWidgets('pièces obligatoires manquantes : aucun envoi', (tester) async {
      final c = await _lancer(tester);
      await toucher(tester, _envoyer());
      expect(
        find.text('Ajoutez ce document pour envoyer votre dossier.'),
        findsNWidgets(2),
      );
      expect(c.envois, isEmpty);
    });
  });

  group('Envoi du dossier', () {
    testWidgets(
      'succès : multipart, jeton stocké, suivi, mot de passe effacé',
      (tester) async {
        final c = await _lancer(
          tester,
          avecFichiers: true,
          routes: {
            _inscription: const Simulee(201, {
              'id': 'p-1',
              'jeton_suivi': 'jeton-abc',
              'statutCompte': 'en_attente',
            }),
          },
        );
        await toucher(tester, _envoyer());
        await passerTransition(tester);

        expect(c.envois, hasLength(1));
        final donnees = c.envois.single.data as FormData;
        final champs = {for (final e in donnees.fields) e.key: e.value};
        expect(champs['metier'], 'coiffure');
        expect(champs['telephone'], '+221771234567');
        expect(champs['email'], 'awa@exemple.sn');
        expect(champs['mot_de_passe_confirmation'], 'motdepasse');
        expect(champs['justificatifs[0][type_document]'], 'cni');
        expect(champs['justificatifs[1][type_document]'], 'diplome');
        expect(donnees.files.map((f) => f.key), [
          'justificatifs[0][fichier]',
          'justificatifs[1][fichier]',
        ]);
        expect(c.stockage.valeur, 'jeton-abc');
        expect(cheminCourant(tester), Chemins.suiviDossier);
        expect(
          c.conteneur.read(formulaireInscriptionProvider).motDePasse,
          isEmpty,
        );
      },
    );

    testWidgets('pendant l\'envoi : bouton désactivé, un seul envoi', (
      tester,
    ) async {
      final serveur = AdaptateurLent({
        'GET /metiers': const Simulee(200, metiersApi),
        'GET /quartiers': const Simulee(200, quartiersApi),
        _inscription: const Simulee(201, {'jeton_suivi': 'j'}),
      });
      final c = await _lancer(tester, serveur: serveur, avecFichiers: true);
      // Les requêtes sont toutes en attente : on ne peut pas charger les métiers, sans importance ici.
      await toucher(tester, _envoyer());
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
        reason: 'le bouton doit être désactivé',
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await tester.tap(find.byType(FilledButton), warnIfMissed: false);
      await avancer(tester, 100);
      serveur.liberer();
      await passerTransition(tester);
      expect(c.envois, hasLength(1));
    });

    testWidgets(
      '429 : message du serveur, dossier conservé, nouvel essai possible',
      (tester) async {
        final c = await _lancer(
          tester,
          avecFichiers: true,
          routes: {
            _inscription: const Simulee(429, {
              'message': 'Trop de tentatives. Réessayez plus tard.',
            }),
          },
        );
        await toucher(tester, _envoyer());
        await avancer(tester, 200);
        expect(
          find.text('Trop de tentatives. Réessayez plus tard.'),
          findsOneWidget,
        );
        expect(cheminCourant(tester), Chemins.inscriptionJustificatifs);
        expect(tester.widget<FilledButton>(_envoyer()).onPressed, isNotNull);
        expect(
          c.conteneur.read(formulaireInscriptionProvider).fichiers,
          hasLength(2),
        );
        expect(c.stockage.valeur, isNull);
      },
    );

    testWidgets('réseau coupé : message réseau, rien n\'est perdu', (
      tester,
    ) async {
      final serveur = AdaptateurSimule({
        'GET /metiers': const Simulee(200, metiersApi),
        'GET /quartiers': const Simulee(200, quartiersApi),
      });
      final c = await _lancer(tester, serveur: serveur, avecFichiers: true);
      serveur.reseauCoupe = true;
      await toucher(tester, _envoyer());
      await avancer(tester, 200);
      expect(find.text(messageReseau), findsOneWidget);
      expect(
        c.conteneur.read(formulaireInscriptionProvider).fichiers,
        hasLength(2),
      );
      expect(c.stockage.valeur, isNull);
    });

    testWidgets('422 sur le dossier (justificatifs) : message sur l\'étape 3', (
      tester,
    ) async {
      await _lancer(
        tester,
        avecFichiers: true,
        routes: {
          _inscription: const Simulee(422, {
            'message': 'Les données sont invalides.',
            'errors': {
              'justificatifs': [
                'Le dossier doit contenir une pièce d\'identité.',
              ],
            },
          }),
        },
      );
      await toucher(tester, _envoyer());
      await avancer(tester, 200);
      expect(
        find.text("Le dossier doit contenir une pièce d'identité."),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.inscriptionJustificatifs);
    });

    testWidgets(
      '422 sur l\'e-mail : retour à l\'étape 2, message sous le champ',
      (tester) async {
        await _lancer(
          tester,
          avecFichiers: true,
          routes: {
            _inscription: const Simulee(422, {
              'message': 'Les données sont invalides.',
              'errors': {
                'email': ['Cette adresse e-mail est déjà utilisée.'],
              },
            }),
          },
        );
        await toucher(tester, _envoyer());
        await passerTransition(tester);
        expect(cheminCourant(tester), Chemins.inscriptionInformations);
        expect(find.text('Étape 2 sur 3'), findsOneWidget);
        expect(
          find.text('Cette adresse e-mail est déjà utilisée.'),
          findsOneWidget,
        );
        // Les données saisies sont toujours là.
        expect(find.text('awa@exemple.sn'), findsOneWidget);
      },
    );

    testWidgets('422 sur l\'entreprise et l\'e-mail : retour à l\'étape 1', (
      tester,
    ) async {
      await _lancer(
        tester,
        avecFichiers: true,
        routes: {
          _inscription: const Simulee(422, {
            'message': 'Les données sont invalides.',
            'errors': {
              'nom_entreprise': ['Ce nom est déjà pris.'],
              'email': ['Cette adresse e-mail est déjà utilisée.'],
            },
          }),
        },
      );
      await toucher(tester, _envoyer());
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
      expect(find.text('Ce nom est déjà pris.'), findsOneWidget);
      expect(find.text('Salon Awa'), findsOneWidget);
    });
  });
}
