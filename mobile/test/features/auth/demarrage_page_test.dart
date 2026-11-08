import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import 'aide_acces.dart';

AdaptateurSimule _serveur() =>
    AdaptateurSimule({'GET /metiers': const Simulee(200, <Object>[])});

void main() {
  group('Démarrage', () {
    testWidgets('animations actives : le nom et la description restent '
        'affichés environ 3,2 s, puis l\'orientation apparaît', (tester) async {
      await lancerAcces(
        tester,
        _serveur(),
        sansAnimation: false,
        depuisOrientation: true,
      );
      await avancer(tester, 800);
      expect(cheminCourant(tester), Chemins.demarrage);
      expect(find.text('SOPI'), findsOneWidget);
      expect(
        find.text('Vos services de proximité, à Keur Massar'),
        findsOneWidget,
      );

      await avancer(tester, 2600);
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.orientation);
      expect(find.text('Je cherche un artisan'), findsOneWidget);
    });

    testWidgets('animations réduites : passage immédiat vers l\'orientation', (
      tester,
    ) async {
      await lancerAcces(tester, _serveur(), depuisOrientation: true);
      expect(cheminCourant(tester), Chemins.orientation);
    });
  });
}
