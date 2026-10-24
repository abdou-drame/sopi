import 'dart:math' as math;
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:image/image.dart' as img;
import 'package:sopi/features/compte/selecteur_photo.dart';

/// Dégradé légèrement bruité : lourd en PNG, mais compressible en JPEG comme une vraie photo.
Uint8List _bruitPng(int largeur, int hauteur) {
  final alea = math.Random(42);
  final image = img.Image(width: largeur, height: hauteur);
  for (final pixel in image) {
    pixel
      ..r = pixel.x * 255 ~/ largeur
      ..g = pixel.y * 255 ~/ hauteur
      ..b = alea.nextInt(32);
  }
  return Uint8List.fromList(img.encodePng(image));
}

void main() {
  test('une petite image légère est rendue telle quelle', () {
    final source = Uint8List.fromList(
      img.encodePng(img.Image(width: 20, height: 10)),
    );
    expect(identical(compresserImage(source), source), isTrue);
  });

  test('une grande image est réduite à 1600 px et passe sous 500 Ko', () {
    final source = _bruitPng(2400, 1800);
    expect(source.length, greaterThan(tailleMaxImageOctets));
    final sortie = compresserImage(source, tailleMax: tailleMaxImageOctets);
    expect(sortie.length, lessThanOrEqualTo(tailleMaxImageOctets));
    final resultat = img.decodeJpg(sortie)!;
    expect(math.max(resultat.width, resultat.height), 1600);
    expect(resultat.width / resultat.height, closeTo(2400 / 1800, .01));
  });

  test('une image qui ne passe sous aucun palier est refusée', () {
    final source = _bruitPng(800, 800);
    expect(
      () => compresserImage(source, tailleMax: 1000),
      throwsA(isA<ImageTropLourdeException>()),
    );
  });

  test('des octets illisibles sont refusés avec un message clair', () {
    expect(
      () => compresserImage(Uint8List.fromList([1, 2, 3, 4])),
      throwsA(
        isA<ImageTropLourdeException>().having(
          (e) => e.message,
          'message',
          messageImageIllisible,
        ),
      ),
    );
  });
}
