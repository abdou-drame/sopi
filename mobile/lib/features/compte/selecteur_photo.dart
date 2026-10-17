import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import 'modeles_litige.dart';

enum SourcePhoto { galerie, appareil }

/// Choix d'une photo ; remplaçable dans les tests. Renvoie null si le client annule.
typedef SelecteurPhoto = Future<PhotoChoisie?> Function(SourcePhoto source);

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
    return PhotoChoisie(
      nom: fichier.name,
      octets: await fichier.readAsBytes(),
      typeMime: _typeMime(fichier),
    );
  };
});
