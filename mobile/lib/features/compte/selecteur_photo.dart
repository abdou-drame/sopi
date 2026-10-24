import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image/image.dart' as img;
import 'package:image_picker/image_picker.dart';

import 'modeles_litige.dart';

enum SourcePhoto { galerie, appareil }

/// Choix d'une photo ; remplaçable dans les tests. Renvoie null si le client annule.
typedef SelecteurPhoto = Future<PhotoChoisie?> Function(SourcePhoto source);

/// Poids maximal d'une image envoyée (le serveur refuse au-delà de 512 Ko).
const tailleMaxImageOctets = 500 * 1024;

/// Côté le plus long maximal après redimensionnement.
const dimensionMaxImage = 1600;

/// Paliers de qualité JPEG essayés, du meilleur au plus compressé.
const qualitesJpeg = [90, 80, 70, 60, 50, 40, 30];

const messageImageTropLourde =
    'Cette photo est trop lourde, même après compression. Choisissez-en une autre.';
const messageImageIllisible =
    'Impossible de lire cette image. Choisissez-en une autre.';

/// Levée quand l'image ne peut pas passer sous la limite : rien n'est envoyé.
class ImageTropLourdeException implements Exception {
  const ImageTropLourdeException([this.message = messageImageTropLourde]);
  final String message;
  @override
  String toString() => message;
}

/// Redimensionne à [dimensionMax] px (côté le plus long) puis encode en JPEG en baissant la qualité
/// par paliers jusqu'à passer sous [tailleMax]. Une image déjà assez légère et assez petite est
/// rendue telle quelle. Lève [ImageTropLourdeException] si aucun palier ne suffit.
Uint8List compresserImage(
  Uint8List octets, {
  int tailleMax = tailleMaxImageOctets,
  int dimensionMax = dimensionMaxImage,
}) {
  final image = img.decodeImage(octets);
  if (image == null) {
    throw const ImageTropLourdeException(messageImageIllisible);
  }
  final plusLong = math.max(image.width, image.height);
  if (octets.length <= tailleMax && plusLong <= dimensionMax) return octets;
  var cible = image;
  if (plusLong > dimensionMax) {
    cible = img.copyResize(
      image,
      width: image.width >= image.height ? dimensionMax : null,
      height: image.height > image.width ? dimensionMax : null,
      interpolation: img.Interpolation.average,
    );
  }
  for (final qualite in qualitesJpeg) {
    final jpeg = Uint8List.fromList(img.encodeJpg(cible, quality: qualite));
    if (jpeg.length <= tailleMax) return jpeg;
  }
  throw const ImageTropLourdeException();
}

/// Version [PhotoChoisie] : renvoie une photo JPEG (nom en .jpg) prête à l'envoi.
Future<PhotoChoisie> compresserPhoto(PhotoChoisie photo) async {
  final octets = await compute(_compresserEnArrierePlan, photo.octets);
  if (identical(octets, photo.octets)) return photo;
  final point = photo.nom.lastIndexOf('.');
  final base = point > 0 ? photo.nom.substring(0, point) : photo.nom;
  return PhotoChoisie(nom: '$base.jpg', octets: octets, typeMime: 'image/jpeg');
}

Uint8List _compresserEnArrierePlan(Uint8List octets) => compresserImage(octets);

String _typeMime(XFile fichier) {
  final declare = fichier.mimeType;
  if (declare != null && declare.isNotEmpty) return declare.toLowerCase();
  final nom = fichier.name.toLowerCase();
  if (nom.endsWith('.png')) return 'image/png';
  if (nom.endsWith('.webp')) return 'image/webp';
  if (nom.endsWith('.jpg') || nom.endsWith('.jpeg')) return 'image/jpeg';
  return 'application/octet-stream';
}

/// Galerie (sélecteur de photos du système) ou appareil photo, via image_picker. Android n'exige aucune
/// permission pour ces deux usages (le système prête sa propre interface).
final selecteurPhotoProvider = Provider<SelecteurPhoto>((ref) {
  final picker = ImagePicker();
  return (source) async {
    final fichier = await picker.pickImage(
      source: source == SourcePhoto.galerie
          ? ImageSource.gallery
          : ImageSource.camera,
    );
    if (fichier == null) return null;
    return compresserPhoto(
      PhotoChoisie(
        nom: fichier.name,
        octets: await fichier.readAsBytes(),
        typeMime: _typeMime(fichier),
      ),
    );
  };
});
