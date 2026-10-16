import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/animation/chargement.dart';
import '../../../core/api_client.dart';
import '../../../core/theme/couleurs.dart';
import '../depot_reservation.dart';

/// Octets d'une photo. L'API protège les photos : la requête passe par le client HTTP (jeton Sanctum).
/// L'URL de l'API est relative (`/api/v1/prestataires/{id}/photos/{photo}`) : on la complète avec l'hôte.
final photoProvider = FutureProvider.autoDispose.family<Uint8List, String>(
  retry: sansRelance,
  (ref, url) async {
    final dio = ref.watch(dioProvider);
    final complete = Uri.parse(dio.options.baseUrl).resolve(url).toString();
    final reponse = await dio.get<List<int>>(
      complete,
      options: Options(responseType: ResponseType.bytes),
    );
    return Uint8List.fromList(reponse.data ?? const []);
  },
);

/// Photo d'un prestataire : squelette gris pendant le chargement, icône neutre si elle ne charge pas.
class PhotoReseau extends ConsumerWidget {
  const PhotoReseau({
    super.key,
    required this.url,
    required this.largeur,
    required this.hauteur,
    this.rayon = 22,
  });

  final String url;
  final double largeur;
  final double hauteur;
  final double rayon;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final octets = ref.watch(photoProvider(url));
    return ClipRRect(
      borderRadius: BorderRadius.circular(rayon),
      child: SizedBox(
        width: largeur,
        height: hauteur,
        child: octets.when(
          loading: () =>
              Squelette(largeur: largeur, hauteur: hauteur, rayon: 0),
          error: (_, _) => const ColoredBox(
            color: CouleursSopi.bleuPastel,
            child: Icon(
              Icons.image_not_supported_outlined,
              color: CouleursSopi.bleu,
            ),
          ),
          data: (b) => Image.memory(
            b,
            fit: BoxFit.cover,
            gaplessPlayback: true,
            errorBuilder: (_, _, _) => const ColoredBox(
              color: CouleursSopi.bleuPastel,
              child: Icon(
                Icons.image_not_supported_outlined,
                color: CouleursSopi.bleu,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
