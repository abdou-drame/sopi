import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/api/client_api.dart';
import 'package:sopi/core/api/erreurs.dart';
import 'package:sopi/core/api/stockage_jeton.dart';
import 'package:sopi/core/api_client.dart';
import 'package:sopi/features/auth/session.dart';
import 'package:sopi/features/auth/utilisateur.dart';

import '../aide_api.dart';

const _profilClient = <String, Object?>{
  'id': 'u1',
  'role': 'client',
  'prenom': 'Awa',
  'nom': 'Diop',
  'statutCompte': 'ACTIF',
};

Dio _client(
  AdaptateurSimule a, {
  StockageJeton? stockage,
  void Function()? expire,
}) => creerClientApi(
  stockage: stockage ?? StockageJetonMemoire(),
  surSessionExpiree: expire ?? () {},
  urlBase: 'http://test/api/v1',
  adaptateur: a,
);

Future<ErreurApi> _erreur(
  Dio dio,
  String chemin, {
  String methode = 'GET',
}) async {
  try {
    await dio.request<Object?>(chemin, options: Options(method: methode));
  } catch (e) {
    return ErreurApi.depuis(e);
  }
  fail('Une erreur était attendue');
}

void main() {
  group('client API', () {
    test('envoie Accept-Language fr et le jeton Bearer', () async {
      final a = AdaptateurSimule({
        'GET /me': const Simulee(200, _profilClient),
      });
      await _client(
        a,
        stockage: StockageJetonMemoire('abc'),
      ).get<Object?>('/me');
      expect(a.requetes.single.headers['Authorization'], 'Bearer abc');
      expect(a.requetes.single.headers['Accept-Language'], 'fr');
    });

    test('sans jeton, pas d\'en-tête Authorization', () async {
      final a = AdaptateurSimule({
        'GET /me': const Simulee(200, _profilClient),
      });
      await _client(a).get<Object?>('/me');
      expect(a.requetes.single.headers.containsKey('Authorization'), isFalse);
    });

    test('401 : session expirée signalée, sauf sur la connexion', () async {
      var expirations = 0;
      final a = AdaptateurSimule({
        'GET /me': const Simulee(401, {'message': 'Non authentifié.'}),
        'POST /auth/connexion': const Simulee(401, {
          'message': 'Identifiants incorrects.',
        }),
      });
      final dio = _client(a, expire: () => expirations++);

      expect(await _erreur(dio, '/me'), isA<ErreurSession>());
      expect(expirations, 1);

      final e = await _erreur(dio, '/auth/connexion', methode: 'POST');
      expect(e.message, 'Identifiants incorrects.');
      expect(expirations, 1);
    });

    test('422 : messages par champ', () async {
      final a = AdaptateurSimule({
        'POST /x': const Simulee(422, {
          'message': 'Les données sont invalides.',
          'errors': {
            'code': ['Code de réservation incorrect.'],
          },
        }),
      });
      final e =
          await _erreur(_client(a), '/x', methode: 'POST') as ErreurValidation;
      expect(e.premierMessage('code'), 'Code de réservation incorrect.');
      expect(e.premierMessage('autre'), isNull);
    });

    test('429 : lit Retry-After', () async {
      final a = AdaptateurSimule({
        'POST /x': const Simulee(
          429,
          {'message': 'Trop de tentatives. Réessayez dans quelques instants.'},
          {'retry-after': '42'},
        ),
      });
      final e =
          await _erreur(_client(a), '/x', methode: 'POST')
              as ErreurTropDeRequetes;
      expect(e.delai, const Duration(seconds: 42));
    });

    test(
      '403 : message, motif et finSuspension (à la racine ou dans detail)',
      () async {
        final a = AdaptateurSimule({
          'GET /a': const Simulee(403, {
            'message': 'Compte suspendu.',
            'motif': 'Fraude',
            'finSuspension': '2026-11-01T00:00:00+00:00',
          }),
          'GET /b': const Simulee(403, {
            'message': 'Refusé.',
            'detail': {'motif': 'Dossier incomplet'},
          }),
        });
        final dio = _client(a);
        final e1 = await _erreur(dio, '/a') as ErreurAccesRefuse;
        expect(e1.message, 'Compte suspendu.');
        expect(e1.motif, 'Fraude');
        expect(e1.finSuspension, DateTime.utc(2026, 11, 1));
        final e2 = await _erreur(dio, '/b') as ErreurAccesRefuse;
        expect(e2.motif, 'Dossier incomplet');
        expect(e2.finSuspension, isNull);
      },
    );

    test('409 et 503 : message du serveur', () async {
      final a = AdaptateurSimule({
        'GET /a': const Simulee(409, {
          'message': 'Cette réservation ne peut pas être démarrée.',
        }),
        'GET /b': const Simulee(503, {'message': 'Service en maintenance.'}),
      });
      final dio = _client(a);
      expect(
        (await _erreur(dio, '/a')).message,
        'Cette réservation ne peut pas être démarrée.',
      );
      expect((await _erreur(dio, '/b')).message, 'Service en maintenance.');
    });

    test('autre cas : message générique', () async {
      final a = AdaptateurSimule({
        'GET /a': const Simulee(500, {
          'message': 'SQLSTATE[...] secret technique',
        }),
      });
      final e = await _erreur(_client(a), '/a');
      expect(e.message, 'Une erreur est survenue. Réessayez.');
    });

    test('réseau coupé', () async {
      final a = AdaptateurSimule({})..reseauCoupe = true;
      final e = await _erreur(_client(a), '/me');
      expect(e, isA<ErreurReseau>());
      expect(e.message, 'Pas de connexion. Vérifiez votre réseau.');
    });
  });

  group('session', () {
    ProviderContainer conteneur(AdaptateurSimule a, StockageJeton s) {
      final c = ProviderContainer(overrides: surchargesApi(a, s));
      addTearDown(c.dispose);
      return c;
    }

    Future<EtatSession> attendre(ProviderContainer c) async {
      await c.read(sessionProvider.notifier).restaurer();
      return c.read(sessionProvider);
    }

    test('sans jeton stocké : non connecté', () async {
      final c = conteneur(AdaptateurSimule({}), StockageJetonMemoire());
      expect(await attendre(c), isA<NonConnecte>());
    });

    test('jeton valide : GET /me donne le rôle', () async {
      final a = AdaptateurSimule({
        'GET /me': Simulee(200, {
          'data': {
            ..._profilClient,
            ...const {'role': 'prestataire'},
          },
        }),
      });
      final c = conteneur(a, StockageJetonMemoire('abc'));
      final etat = await attendre(c) as Connecte;
      expect(etat.utilisateur.role, RoleSopi.prestataire);
      expect(etat.utilisateur.nomComplet, 'Awa Diop');
    });

    test('jeton expiré : effacé, non connecté', () async {
      final s = StockageJetonMemoire('vieux');
      final c = conteneur(
        AdaptateurSimule({
          'GET /me': const Simulee(401, {'message': 'Non authentifié.'}),
        }),
        s,
      );
      expect(await attendre(c), isA<NonConnecte>());
      expect(await s.lire(), isNull);
    });

    test('réseau coupé au démarrage : le jeton est conservé', () async {
      final s = StockageJetonMemoire('abc');
      final a = AdaptateurSimule({})..reseauCoupe = true;
      final c = conteneur(a, s);
      expect(await attendre(c), isA<NonConnecte>());
      expect(await s.lire(), 'abc');
    });

    test(
      'connexion : jeton stocké, rôle lu dans la réponse, corps {identifiant, password}',
      () async {
        final s = StockageJetonMemoire();
        final a = AdaptateurSimule({
          'POST /auth/connexion': const Simulee(200, {
            'token': 'jeton1',
            'user': _profilClient,
          }),
        });
        final c = conteneur(a, s);
        await c
            .read(sessionProvider.notifier)
            .connecter('awa@exemple.sn', 'secret');
        expect(
          (c.read(sessionProvider) as Connecte).utilisateur.role,
          RoleSopi.client,
        );
        expect(await s.lire(), 'jeton1');
        expect(a.requetes.last.data, {
          'identifiant': 'awa@exemple.sn',
          'password': 'secret',
        });
      },
    );

    test('connexion : identifiants faux = erreur, aucun jeton', () async {
      final s = StockageJetonMemoire();
      final a = AdaptateurSimule({
        'POST /auth/connexion': const Simulee(401, {
          'message': 'Identifiants incorrects.',
        }),
      });
      final c = conteneur(a, s);
      await expectLater(
        c.read(sessionProvider.notifier).connecter('x', 'y'),
        throwsA(
          isA<ErreurSession>().having(
            (e) => e.message,
            'message',
            'Identifiants incorrects.',
          ),
        ),
      );
      expect(await s.lire(), isNull);
    });

    test(
      'connexion 403 : état « compte restreint » avec motif et fin de suspension',
      () async {
        final a = AdaptateurSimule({
          'POST /auth/connexion': const Simulee(403, {
            'message': 'Votre compte est suspendu.',
            'motif': 'Retards répétés',
            'finSuspension': '2026-11-01T00:00:00+00:00',
          }),
        });
        final c = conteneur(a, StockageJetonMemoire());
        await c.read(sessionProvider.notifier).connecter('x', 'y');
        final etat = c.read(sessionProvider) as Restreint;
        expect(etat.message, 'Votre compte est suspendu.');
        expect(etat.motif, 'Retards répétés');
        expect(etat.finSuspension, DateTime.utc(2026, 11, 1));
      },
    );

    test(
      'connexion d\'un administrateur : refusée, rien de stocké, jeton fermé côté serveur',
      () async {
        final s = StockageJetonMemoire();
        final a = AdaptateurSimule({
          'POST /auth/connexion': const Simulee(200, {
            'token': 'adm',
            'user': {'id': 'a', 'role': 'admin'},
          }),
          'POST /auth/deconnexion': const Simulee(204),
        });
        final c = conteneur(a, s);
        await expectLater(
          c.read(sessionProvider.notifier).connecter('a', 'b'),
          throwsA(isA<ErreurAccesRefuse>()),
        );
        expect(await s.lire(), isNull);
        expect(c.read(sessionProvider), isNot(isA<Connecte>()));
        expect(a.requetes.last.path, '/auth/deconnexion');
        expect(a.requetes.last.headers['Authorization'], 'Bearer adm');
      },
    );

    test('déconnexion : POST puis effacement local', () async {
      final s = StockageJetonMemoire('abc');
      final a = AdaptateurSimule({
        'GET /me': const Simulee(200, _profilClient),
        'POST /auth/deconnexion': const Simulee(200, {}),
      });
      final c = conteneur(a, s);
      await attendre(c);
      await c.read(sessionProvider.notifier).deconnecter();
      expect(
        a.requetes.any(
          (r) => r.method == 'POST' && r.path == '/auth/deconnexion',
        ),
        isTrue,
      );
      expect(await s.lire(), isNull);
      expect(c.read(sessionProvider), isA<NonConnecte>());
    });

    test('déconnexion : effacement local même si l\'appel échoue', () async {
      final s = StockageJetonMemoire('abc');
      final a = AdaptateurSimule({
        'GET /me': const Simulee(200, _profilClient),
      });
      final c = conteneur(a, s);
      await attendre(c);
      a.reseauCoupe = true;
      await c.read(sessionProvider.notifier).deconnecter();
      expect(await s.lire(), isNull);
      expect(c.read(sessionProvider), isA<NonConnecte>());
    });

    test('un 401 en cours d\'usage efface la session', () async {
      final s = StockageJetonMemoire('abc');
      final a = AdaptateurSimule({
        'GET /me': const Simulee(200, _profilClient),
        'GET /reservations': const Simulee(401, {
          'message': 'Non authentifié.',
        }),
      });
      final c = conteneur(a, s);
      await attendre(c);
      await expectLater(
        c.read(dioProvider).get<Object?>('/reservations'),
        throwsA(isA<DioException>()),
      );
      await Future<void>.delayed(Duration.zero);
      expect(c.read(sessionProvider), isA<NonConnecte>());
      expect(await s.lire(), isNull);
    });
  });
}
