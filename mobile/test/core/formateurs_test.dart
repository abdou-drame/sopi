import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/widgets/decompte.dart';

void main() {
  setUpAll(initialiserFormats);

  group('formaterArgent', () {
    test('sépare les milliers et colle FCFA avec des espaces insécables', () {
      expect(formaterArgent(1500), '1 500 FCFA');
      expect(formaterArgent(500000), '500 000 FCFA');
      expect(formaterArgent(0), '0 FCFA');
      expect(formaterArgent(999), '999 FCFA');
      expect(formaterArgent(1234567), '1 234 567 FCFA');
    });

    test('arrondit les décimales et garde le signe', () {
      expect(formaterArgent(1500.4), '1 500 FCFA');
      expect(formaterArgent(-2500), '-2 500 FCFA');
    });
  });

  group('formaterDate', () {
    test('« Sam. 24 oct., 10h00 »', () {
      expect(
        formaterDate(DateTime.utc(2026, 10, 24, 10)),
        'Sam. 24 oct., 10h00',
      );
    });

    test(
      "affiche l'heure de Dakar (UTC) quel que soit le décalage de la date reçue",
      () {
        expect(
          formaterDate(DateTime.parse('2026-10-24T12:05:00+02:00')),
          'Sam. 24 oct., 10h05',
        );
      },
    );

    test('met un zéro devant les minutes et les heures', () {
      expect(
        formaterDate(DateTime.utc(2026, 1, 5, 7, 3)),
        'Lun. 5 janv., 07h03',
      );
    });
  });

  group('formaterTelephone', () {
    test('met en forme un mobile sénégalais', () {
      expect(formaterTelephone('+221771234567'), '+221 77 123 45 67');
      expect(formaterTelephone('221771234567'), '+221 77 123 45 67');
      expect(formaterTelephone('77 123 45 67'), '+221 77 123 45 67');
    });

    test('laisse tel quel un numéro masqué ou invalide', () {
      expect(formaterTelephone('+221******567'), '+221******567');
      expect(formaterTelephone('abc'), 'abc');
    });
  });

  group('formaterDecompte', () {
    test('mm:ss sous une heure', () {
      expect(
        formaterDecompte(const Duration(minutes: 4, seconds: 59)),
        '04:59',
      );
      expect(formaterDecompte(const Duration(seconds: 5)), '00:05');
    });

    test('« 1 h 52 » à partir d\'une heure', () {
      expect(
        formaterDecompte(const Duration(hours: 1, minutes: 52, seconds: 10)),
        '1 h 52',
      );
      expect(formaterDecompte(const Duration(hours: 2)), '2 h 00');
    });

    test('zéro quand le délai est dépassé', () {
      expect(formaterDecompte(Duration.zero), '00:00');
      expect(formaterDecompte(const Duration(seconds: -4)), '00:00');
    });
  });
}
