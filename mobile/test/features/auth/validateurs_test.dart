import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/features/auth/validateurs.dart';

void main() {
  group('Téléphone sénégalais', () {
    test('formats acceptés', () {
      for (final saisie in [
        '+221 77 123 45 67',
        '+221771234567',
        '00221 77 123 45 67',
        '77 123 45 67',
        '771234567',
        '77.123.45.67',
        '78 123 45 67',
        '76 123 45 67',
        '75 123 45 67',
        '70 123 45 67',
        '  77 123 45 67  ',
      ]) {
        expect(ValidateursAcces.telephone(saisie), isNull, reason: saisie);
        expect(ValidateursAcces.mobileNational(saisie)?.length, 9);
      }
    });

    test('formats refusés', () {
      for (final saisie in [
        '71 123 45 67',
        '72 123 45 67',
        '79 123 45 67',
        '33 123 45 67',
        '77 123 45 6',
        '77 123 45 678',
        '+33 77 123 45 67',
        'abc',
      ]) {
        expect(
          ValidateursAcces.telephone(saisie),
          ValidateursAcces.messageTelephone,
          reason: saisie,
        );
        expect(ValidateursAcces.mobileNational(saisie), isNull);
      }
    });

    test('vide : champ obligatoire', () {
      expect(
        ValidateursAcces.telephone('  '),
        'Le numéro de téléphone est obligatoire.',
      );
    });

    test('format envoyé à l\'API', () {
      expect(ValidateursAcces.telephoneApi('77 123 45 67'), '+221771234567');
      expect(
        ValidateursAcces.telephoneApi('+221 78 123 45 67'),
        '+221781234567',
      );
      expect(ValidateursAcces.telephoneApi('71 123 45 67'), isNull);
    });

    test('message exact de la maquette', () {
      expect(
        ValidateursAcces.messageTelephone,
        'Le numéro de téléphone doit être un mobile sénégalais valide (ex. : 77 123 45 67).',
      );
    });
  });

  group('Noms', () {
    test('obligatoire, 100 caractères au plus', () {
      expect(ValidateursAcces.nom('', 'Le nom'), 'Le nom est obligatoire.');
      expect(ValidateursAcces.nom('   ', 'Le nom'), isNotNull);
      expect(ValidateursAcces.nom('a' * 100, 'Le nom'), isNull);
      expect(
        ValidateursAcces.nom('a' * 101, 'Le prénom'),
        'Le prénom ne doit pas dépasser 100 caractères.',
      );
    });
  });

  group('E-mail', () {
    test('obligatoire', () {
      expect(
        ValidateursAcces.emailObligatoire(''),
        "L'adresse e-mail est obligatoire.",
      );
      expect(
        ValidateursAcces.emailObligatoire('nimporte'),
        "L'adresse e-mail n'est pas valide.",
      );
      expect(ValidateursAcces.emailObligatoire(' a@b.sn '), isNull);
    });
  });

  group('Mot de passe', () {
    test('8 caractères au moins', () {
      expect(ValidateursAcces.motDePasse(''), isNotNull);
      expect(ValidateursAcces.motDePasse('1234567'), isNotNull);
      expect(ValidateursAcces.motDePasse('12345678'), isNull);
    });

    test('confirmation identique', () {
      expect(ValidateursAcces.confirmation('motdepasse', ''), isNotNull);
      expect(
        ValidateursAcces.confirmation('motdepasse', 'Motdepasse'),
        isNotNull,
      );
      expect(ValidateursAcces.confirmation('motdepasse', 'motdepasse'), isNull);
    });
  });
}
