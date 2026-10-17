import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import '../compte/litige_page.dart' show ChoixSourcePhoto;
import '../compte/modeles_litige.dart' show PhotoChoisie;
import '../compte/selecteur_photo.dart';
import 'depot_prestataire.dart';
import 'widgets/composants_agenda.dart';

/// « Photos de l'entreprise » (maquette PrestaPhotos) : grille de 3 colonnes, ajout (galerie ou appareil photo) et
/// suppression avec confirmation. Aucun plafond ni contrôle de format ici : le serveur décide et son message est affiché.
class PhotosPage extends ConsumerStatefulWidget {
  const PhotosPage({super.key});

  @override
  ConsumerState<PhotosPage> createState() => _PhotosPageState();
}

class _PhotosPageState extends ConsumerState<PhotosPage> {
  bool _envoi = false;
  String? _erreur;

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.profilPrestataire);

  String _message(Object e) {
    final erreur = ErreurApi.depuis(e);
    return (erreur is ErreurValidation
            ? messageServeur(erreur, 'photo')
            : null) ??
        erreur.message;
  }

  Future<void> _ajouter() async {
    if (_envoi) return;
    final source = await showModalBottomSheet<SourcePhoto>(
      context: context,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      barrierColor: CouleursSopi.marine.withValues(alpha: .6),
      builder: (_) => const ChoixSourcePhoto(),
    );
    if (source == null || !mounted) return;
    final PhotoChoisie? photo;
    try {
      photo = await ref.read(selecteurPhotoProvider)(source);
    } catch (_) {
      if (!mounted) return;
      setState(
        () => _erreur = source == SourcePhoto.appareil
            ? "Impossible d'ouvrir l'appareil photo."
            : "Impossible d'ouvrir la galerie.",
      );
      return;
    }
    if (photo == null || !mounted) return;
    setState(() {
      _envoi = true;
      _erreur = null;
    });
    try {
      await ref
          .read(depotPrestataireProvider)
          .ajouterPhoto(
            nom: photo.nom,
            octets: photo.octets,
            typeMime: photo.typeMime,
          );
      ref.invalidate(photosAtelierProvider);
      if (!mounted) return;
      setState(() => _envoi = false);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _envoi = false;
        _erreur = _message(e);
      });
    }
  }

  Future<void> _supprimer(String id) async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Supprimer cette photo ?'),
        content: const Text('Elle ne sera plus visible par les clients.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirme != true || !mounted) return;
    setState(() => _erreur = null);
    try {
      await ref.read(depotPrestataireProvider).supprimerPhoto(id);
      ref.invalidate(photosAtelierProvider);
    } catch (e) {
      if (!mounted) return;
      // Une photo déjà supprimée (404) : on relit la liste pour refléter le serveur.
      ref.invalidate(photosAtelierProvider);
      setState(() => _erreur = _message(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final photos = ref.watch(photosAtelierProvider);

    Widget corps;
    if (photos.isLoading && !photos.hasValue) {
      corps = const Padding(
        padding: EdgeInsets.fromLTRB(16, 20, 16, 0),
        child: Squelette(hauteur: 110, rayon: 20),
      );
    } else if (photos.hasError && !photos.hasValue) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(
          message: ErreurApi.depuis(photos.error!).message,
          onReessayer: () => ref.invalidate(photosAtelierProvider),
        ),
      );
    } else {
      final ids = photos.requireValue;
      corps = Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
        child: Column(
          children: [
            if (_erreur != null) ...[
              BandeauErreur.alerte(message: _erreur!),
              const SizedBox(height: 12),
            ],
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 3,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              children: [
                for (final id in ids)
                  _Vignette(
                    key: ValueKey('photo-$id'),
                    id: id,
                    onSupprimer: () => _supprimer(id),
                  ),
                if (_envoi)
                  const DecoratedBox(
                    key: ValueKey('photo-envoi'),
                    decoration: BoxDecoration(
                      color: CouleursSopi.champ,
                      borderRadius: BorderRadius.all(Radius.circular(20)),
                    ),
                    child: Center(
                      child: SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(strokeWidth: 2.5),
                      ),
                    ),
                  ),
                _TuileAjout(onTap: _envoi ? null : _ajouter),
              ],
            ),
          ],
        ),
      );
    }

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(photosAtelierProvider.future),
        edgeOffset: MediaQuery.paddingOf(context).top,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: EnTeteDegrade(
                titreLigne1: 'Photos de',
                titreLigne2: "l'entreprise",
                onRetour: _retour,
                enfant: Text(
                  'Ces photos sont visibles par les clients sur votre fiche. '
                  'Utilisez des images nettes (JPEG, PNG ou WebP).',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 13,
                    height: 19 / 13,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
              ),
            ),
            SliverToBoxAdapter(child: corps),
            SliverToBoxAdapter(
              child: SizedBox(
                height: MediaQuery.paddingOf(context).bottom + 24,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Une photo de l'entreprise (carré à coins de 20) avec sa pastille de suppression.
class _Vignette extends ConsumerWidget {
  const _Vignette({super.key, required this.id, required this.onSupprimer});

  final String id;
  final VoidCallback onSupprimer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final image = ref.watch(photoAtelierProvider(id));
    return Stack(
      fit: StackFit.expand,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: image.when(
            data: (octets) => Image.memory(
              octets,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => const _ImageIndisponible(),
            ),
            error: (_, _) => const _ImageIndisponible(),
            loading: () => const ColoredBox(color: CouleursSopi.champ),
          ),
        ),
        Positioned(
          top: 6,
          right: 6,
          child: Semantics(
            button: true,
            label: 'Supprimer la photo',
            child: GestureDetector(
              key: ValueKey('supprimer-$id'),
              onTap: onSupprimer,
              child: Container(
                width: 26,
                height: 26,
                decoration: const BoxDecoration(
                  color: CouleursSopi.marine,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.close_rounded,
                  size: 14,
                  color: CouleursSopi.blanc,
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _ImageIndisponible extends StatelessWidget {
  const _ImageIndisponible();

  @override
  Widget build(BuildContext context) => const ColoredBox(
    color: CouleursSopi.champ,
    child: Center(
      child: Icon(
        Icons.broken_image_outlined,
        color: CouleursSopi.texteDiscret,
      ),
    ),
  );
}

/// Tuile « + » à bord pointillé de la maquette (bord plein : Flutter n'a pas de bordure pointillée native).
class _TuileAjout extends StatelessWidget {
  const _TuileAjout({required this.onTap});

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Ajouter une photo',
      child: InkWell(
        key: const ValueKey('ajouter-photo'),
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            color: const Color(0xFFF7F9FF),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFB8C4E6), width: 2),
          ),
          child: const Center(
            child: Icon(Icons.add_rounded, size: 26, color: CouleursSopi.bleu),
          ),
        ),
      ),
    );
  }
}
