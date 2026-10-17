import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../compte/selecteur_photo.dart';
import 'modeles.dart';

enum SourceJustificatif { galerie, appareil, fichier }

/// Choix d'un justificatif ; remplaçable dans les tests. Renvoie null si l'utilisateur annule.
typedef SelecteurJustificatif =
    Future<FichierJustificatif?> Function(SourceJustificatif source);

const messageFormatJustificatif =
    'Format non accepté. Choisissez un PDF, une photo JPEG ou PNG.';
const messageTailleJustificatif =
    'Ce fichier est trop lourd. La taille maximale est de 5 Mo.';

/// Type MIME déduit de l'extension (le sélecteur de fichiers ne le donne pas).
String typeMimeDepuisNom(String nom) {
  final n = nom.toLowerCase();
  if (n.endsWith('.pdf')) return 'application/pdf';
  if (n.endsWith('.png')) return 'image/png';
  if (n.endsWith('.jpg') || n.endsWith('.jpeg')) return 'image/jpeg';
  return 'application/octet-stream';
}

/// Contrôles faits côté application avant l'envoi (le serveur revérifie le contenu réel).
/// Renvoie le message à afficher, ou null si le fichier convient.
String? controlerJustificatif(FichierJustificatif f) {
  if (!typesJustificatifAcceptes.contains(f.typeMime)) {
    return messageFormatJustificatif;
  }
  if (f.octets.length > tailleMaxJustificatif) {
    return messageTailleJustificatif;
  }
  return null;
}

/// Galerie et appareil photo via image_picker (réutilise le sélecteur du module Litige) ; fichier via file_picker.
final selecteurJustificatifProvider = Provider<SelecteurJustificatif>((ref) {
  return (source) async {
    switch (source) {
      case SourceJustificatif.galerie:
      case SourceJustificatif.appareil:
        final photo = await ref.read(selecteurPhotoProvider)(
          source == SourceJustificatif.galerie
              ? SourcePhoto.galerie
              : SourcePhoto.appareil,
        );
        if (photo == null) return null;
        return FichierJustificatif(
          nom: photo.nom,
          octets: photo.octets,
          typeMime: photo.typeMime,
        );
      case SourceJustificatif.fichier:
        final resultat = await FilePicker.pickFiles(
          type: FileType.custom,
          allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png'],
          withData: true,
        );
        final choisi = resultat?.files.firstOrNull;
        final octets = choisi?.bytes;
        if (choisi == null || octets == null) return null;
        return FichierJustificatif(
          nom: choisi.name,
          octets: octets,
          typeMime: typeMimeDepuisNom(choisi.name),
        );
    }
  };
});
