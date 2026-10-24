import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/theme/disposition.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import '../reservation/depot_reservation.dart';
import '../reservation/modeles.dart';
import 'depot_compte.dart';
import 'modeles_litige.dart';
import 'selecteur_photo.dart';

const messageFormatPhoto =
    'Format non accepté : choisissez une photo JPEG, PNG ou WebP.';
const messageTaillePhoto = 'Cette photo dépasse 512 Ko.';
const messageTropDePhotos = 'Vous pouvez joindre 3 photos au plus.';
const messageSignalementEnvoye =
    'Signalement envoyé. Un administrateur examinera votre dossier.';

/// « Signaler un problème » (maquette Litige) : motif, description, preuves en photo, puis écran de confirmation.
class LitigePage extends ConsumerStatefulWidget {
  const LitigePage({super.key, required this.reservationId});

  final String reservationId;

  @override
  ConsumerState<LitigePage> createState() => _EtatLitige();
}

class _EtatLitige extends ConsumerState<LitigePage> {
  final _description = TextEditingController();
  final _photos = <PhotoChoisie>[];
  String? _motif;
  bool _enCours = false;
  bool _envoye = false;
  String? _erreur;
  String? _erreurMotif;
  String? _erreurDescription;
  String? _erreurPhotos;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  void _retour() => context.canPop()
      ? context.pop()
      : context.go(Chemins.detailReservation(widget.reservationId));

  Future<void> _ajouterPhoto() async {
    if (_photos.length >= preuvesLitigeMax) {
      setState(() => _erreurPhotos = messageTropDePhotos);
      return;
    }
    final source = await showModalBottomSheet<SourcePhoto>(
      context: context,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      barrierColor: CouleursSopi.texte.withValues(alpha: .6),
      builder: (_) => const ChoixSourcePhoto(),
    );
    if (source == null || !mounted) return;
    final PhotoChoisie? photo;
    try {
      photo = await ref.read(selecteurPhotoProvider)(source);
    } on ImageTropLourdeException catch (e) {
      if (!mounted) return;
      setState(() => _erreurPhotos = e.message);
      return;
    } catch (_) {
      if (!mounted) return;
      setState(
        () => _erreurPhotos = source == SourcePhoto.appareil
            ? "Impossible d'ouvrir l'appareil photo."
            : "Impossible d'ouvrir la galerie.",
      );
      return;
    }
    if (photo == null || !mounted) return;
    setState(() {
      if (!typesPreuveAcceptes.contains(photo!.typeMime)) {
        _erreurPhotos = messageFormatPhoto;
      } else if (photo.octets.length > tailleMaxPreuve) {
        _erreurPhotos = messageTaillePhoto;
      } else {
        _photos.add(photo);
        _erreurPhotos = null;
      }
    });
  }

  String? _validerDescription() {
    final n = _description.text.trim().length;
    if (n == 0) return 'Décrivez la situation en quelques mots.';
    if (n < descriptionLitigeMin) {
      return 'La description doit contenir au moins $descriptionLitigeMin caractères.';
    }
    return null;
  }

  Future<void> _envoyer() async {
    final erreurMotif = _motif == null
        ? "Choisissez ce qui s'est passé."
        : null;
    final erreurDescription = _validerDescription();
    if (erreurMotif != null || erreurDescription != null) {
      setState(() {
        _erreurMotif = erreurMotif;
        _erreurDescription = erreurDescription;
        _erreur = null;
      });
      return;
    }
    if (_enCours) return;
    setState(() {
      _enCours = true;
      _erreur = null;
      _erreurMotif = null;
      _erreurDescription = null;
    });
    try {
      await ref
          .read(depotCompteProvider)
          .ouvrirLitige(
            widget.reservationId,
            motif: _motif!,
            description: _description.text.trim(),
            preuves: _photos,
          );
    } catch (e) {
      if (!mounted) return;
      final erreur = ErreurApi.depuis(e);
      setState(() {
        _enCours = false;
        if (erreur is ErreurValidation) {
          _erreurMotif = erreur.premierMessage('motif');
          _erreurDescription = erreur.premierMessage('description');
          final photos = erreur.champs.entries
              .where((c) => c.key.startsWith('preuves') && c.value.isNotEmpty)
              .map((c) => c.value.first)
              .toList();
          _erreurPhotos = photos.isEmpty ? null : photos.first;
          if (_erreurMotif == null &&
              _erreurDescription == null &&
              _erreurPhotos == null) {
            _erreur = erreur.message;
          }
        } else {
          _erreur = erreur.message;
        }
      });
      return;
    }
    if (!mounted) return;
    ref.invalidate(detailReservationProvider(widget.reservationId));
    setState(() {
      _enCours = false;
      _envoye = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_envoye) return _Confirmation(onRetour: _retour);

    final async = ref.watch(detailReservationProvider(widget.reservationId));
    final r = async.value;
    final theme = Theme.of(context);
    final bas = MediaQuery.viewInsetsOf(context).bottom;
    final haut = hautEcran(context);

    return Scaffold(
      backgroundColor: CouleursSopi.fond,
      body: Column(
        children: [
          Expanded(
            child: ListView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(
                DimensionsSopi.margeCarte,
                haut,
                DimensionsSopi.margeCarte,
                DimensionsSopi.espace,
              ),
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: BoutonRetour(onPressed: _enCours ? null : _retour),
                  ),
                ),
                const SizedBox(height: 24),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Text.rich(
                    TextSpan(
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontSize: DimensionsSopi.tailleTitreEcran,
                        height:
                            DimensionsSopi.interligneTitreEcran /
                            DimensionsSopi.tailleTitreEcran,
                        fontWeight: FontWeight.w400,
                      ),
                      children: const [
                        TextSpan(text: 'Signaler un\n'),
                        TextSpan(
                          text: 'problème',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 22),
                if (r == null)
                  async.hasError
                      ? SizedBox(
                          height: 260,
                          child: EtatErreur(
                            message: ErreurApi.depuis(async.error!).message,
                            onReessayer: () => ref.invalidate(
                              detailReservationProvider(widget.reservationId),
                            ),
                          ),
                        )
                      : const SizedBox(height: 260, child: EtatChargement())
                else
                  _CarteLitige(
                    reservation: r,
                    motif: _motif,
                    description: _description,
                    photos: _photos,
                    enCours: _enCours,
                    erreurMotif: _erreurMotif,
                    erreurDescription: _erreurDescription,
                    erreurPhotos: _erreurPhotos,
                    onMotif: (m) => setState(() {
                      _motif = m;
                      _erreurMotif = null;
                    }),
                    onDescription: () =>
                        setState(() => _erreurDescription = null),
                    onAjouter: _ajouterPhoto,
                    onRetirer: (i) => setState(() {
                      _photos.removeAt(i);
                      _erreurPhotos = null;
                    }),
                  ),
                if (_erreur != null) ...[
                  const SizedBox(height: 14),
                  BandeauErreur(message: _erreur!),
                ],
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(
              DimensionsSopi.margeCarte,
              8,
              DimensionsSopi.margeCarte,
              bas > 0 ? 8 : 24,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                BoutonPrimaire(
                  libelle: 'Envoyer le signalement',
                  chargement: _enCours,
                  onPressed: r == null || _enCours ? null : _envoyer,
                ),
                if (bas == 0) ...[
                  const SizedBox(height: 10),
                  Text(
                    'Un administrateur examinera votre dossier.',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12,
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                ],
              ],
            ),
          ),
          SizedBox(height: bas),
        ],
      ),
    );
  }
}

class _CarteLitige extends StatelessWidget {
  const _CarteLitige({
    required this.reservation,
    required this.motif,
    required this.description,
    required this.photos,
    required this.enCours,
    required this.erreurMotif,
    required this.erreurDescription,
    required this.erreurPhotos,
    required this.onMotif,
    required this.onDescription,
    required this.onAjouter,
    required this.onRetirer,
  });

  final ReservationClient reservation;
  final String? motif;
  final TextEditingController description;
  final List<PhotoChoisie> photos;
  final bool enCours;
  final String? erreurMotif;
  final String? erreurDescription;
  final String? erreurPhotos;
  final ValueChanged<String> onMotif;
  final VoidCallback onDescription;
  final VoidCallback onAjouter;
  final ValueChanged<int> onRetirer;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final date = DateFormat('d MMM', 'fr_FR').format(reservation.debut.toUtc());
    final etiquette = theme.textTheme.labelMedium?.copyWith(
      fontSize: 12,
      fontWeight: FontWeight.w600,
      color: CouleursSopi.texteSecondaire,
    );
    final aErreur = erreurDescription != null;
    final bordure = OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: aErreur
          ? const BorderSide(color: CouleursSopi.erreurTexte)
          : BorderSide.none,
    );
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(
          DimensionsSopi.rayonCarteFormulaire,
        ),
        boxShadow: OmbresSopi.carte,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Réservation : ${reservation.prestataireNom} · ${reservation.prestationNom} · $date',
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 13,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
          const SizedBox(height: 14),
          Text("Que s'est-il passé ?", style: etiquette),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final m in motifsLitige)
                _Pastille(
                  libelle: m,
                  choisie: m == motif,
                  onTap: enCours ? null : () => onMotif(m),
                ),
            ],
          ),
          if (erreurMotif != null) ...[
            const SizedBox(height: 6),
            Text(
              erreurMotif!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.erreurTexte,
              ),
            ),
          ],
          const SizedBox(height: 14),
          Text('Décrivez la situation', style: etiquette),
          const SizedBox(height: 6),
          TextField(
            controller: description,
            enabled: !enCours,
            minLines: 5,
            maxLines: 5,
            maxLength: descriptionLitigeMax,
            textCapitalization: TextCapitalization.sentences,
            keyboardType: TextInputType.multiline,
            onChanged: (_) => onDescription(),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              color: CouleursSopi.texte,
            ),
            decoration: InputDecoration(
              filled: true,
              fillColor: CouleursSopi.champ,
              contentPadding: const EdgeInsets.all(14),
              enabledBorder: bordure,
              disabledBorder: bordure,
              focusedBorder: bordure,
              errorText: erreurDescription,
              counterStyle: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.texteSecondaire,
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text('Preuves (photos)', style: etiquette),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (var i = 0; i < photos.length; i++)
                _Vignette(
                  photo: photos[i],
                  index: i,
                  onRetirer: enCours ? null : () => onRetirer(i),
                ),
              if (photos.length < preuvesLitigeMax)
                _BoutonAjouter(onPressed: enCours ? null : onAjouter),
            ],
          ),
          if (erreurPhotos != null) ...[
            const SizedBox(height: 8),
            Text(
              erreurPhotos!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.erreurTexte,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Pastille extends StatelessWidget {
  const _Pastille({
    required this.libelle,
    required this.choisie,
    required this.onTap,
  });

  final String libelle;
  final bool choisie;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    selected: choisie,
    child: Material(
      color: choisie ? CouleursSopi.bleu : CouleursSopi.fondSecondaire,
      shape: const StadiumBorder(),
      child: InkWell(
        customBorder: const StadiumBorder(),
        onTap: onTap,
        child: Container(
          height: 38,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          alignment: Alignment.center,
          child: Text(
            libelle,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: choisie ? CouleursSopi.blanc : CouleursSopi.texte,
            ),
          ),
        ),
      ),
    ),
  );
}

class _BoutonAjouter extends StatelessWidget {
  const _BoutonAjouter({required this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: 'Ajouter une photo',
    child: InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onPressed,
      child: CustomPaint(
        painter: _TraitsPointilles(),
        child: const SizedBox(
          width: 64,
          height: 64,
          child: Icon(Icons.add_rounded, size: 22, color: CouleursSopi.bleu),
        ),
      ),
    ),
  );
}

/// Cadre en pointillés de 2 px, arrondi à 18 (maquette : « dashed #8a90a2 »).
class _TraitsPointilles extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final fond = RRect.fromRectAndRadius(
      Offset.zero & size,
      const Radius.circular(18),
    );
    canvas.drawRRect(fond, Paint()..color = CouleursSopi.champ);
    final trait = Paint()
      ..color = CouleursSopi.iconeInactive
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    final chemin = Path()..addRRect(fond.deflate(1));
    for (final m in chemin.computeMetrics()) {
      for (var d = 0.0; d < m.length; d += 8) {
        canvas.drawPath(m.extractPath(d, (d + 4).clamp(0, m.length)), trait);
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter ancien) => false;
}

class _Vignette extends StatelessWidget {
  const _Vignette({
    required this.photo,
    required this.index,
    required this.onRetirer,
  });

  final PhotoChoisie photo;
  final int index;
  final VoidCallback? onRetirer;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 64,
    height: 64,
    child: Stack(
      clipBehavior: Clip.none,
      children: [
        Positioned.fill(
          child: ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: Image.memory(
              photo.octets,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => Container(
                color: CouleursSopi.champ,
                child: const Icon(
                  Icons.image_outlined,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
            ),
          ),
        ),
        Positioned(
          right: -6,
          top: -6,
          child: Semantics(
            button: true,
            label: 'Retirer la photo ${index + 1}',
            child: GestureDetector(
              key: ValueKey('retirer-photo-$index'),
              onTap: onRetirer,
              child: Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  color: CouleursSopi.bleu,
                  shape: BoxShape.circle,
                  border: Border.all(color: CouleursSopi.blanc, width: 2),
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
    ),
  );
}

/// Feuille de choix de la source de la photo, partagée avec les photos d'entreprise (ajout hors maquette, nécessaire pour la galerie et l'appareil photo).
class ChoixSourcePhoto extends StatelessWidget {
  const ChoixSourcePhoto({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget ligne(IconData icone, String libelle, SourcePhoto source) =>
        ListTile(
          leading: CircleAvatar(
            backgroundColor: CouleursSopi.bleuPastel,
            child: Icon(icone, color: CouleursSopi.bleu),
          ),
          title: Text(
            libelle,
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          onTap: () => Navigator.of(context).pop(source),
        );
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 14, 12, 16),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(36),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 44,
              height: 5,
              decoration: BoxDecoration(
                color: const Color(0xFFD5DDF0),
                borderRadius: BorderRadius.circular(3),
              ),
            ),
            const SizedBox(height: 12),
            ligne(Icons.photo_library_outlined, 'Galerie', SourcePhoto.galerie),
            ligne(
              Icons.photo_camera_outlined,
              'Appareil photo',
              SourcePhoto.appareil,
            ),
          ],
        ),
      ),
    );
  }
}

/// Écran « Signalement envoyé » (même composition que « Demande envoyée »).
class _Confirmation extends StatelessWidget {
  const _Confirmation({required this.onRetour});

  final VoidCallback onRetour;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = hautEcran(context);
    return Scaffold(
      backgroundColor: CouleursSopi.fond,
      body: Stack(
        children: [
          Padding(
            padding: EdgeInsets.only(top: haut + 56),
            child: Align(
              alignment: Alignment.topCenter,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 104,
                    height: 104,
                    decoration: const BoxDecoration(
                      color: CouleursSopi.bleuPale,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.check_rounded,
                      size: 46,
                      color: CouleursSopi.bleu,
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text.rich(
                    textAlign: TextAlign.center,
                    TextSpan(
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontSize: 28,
                        height: 34 / 28,
                        fontWeight: FontWeight.w400,
                      ),
                      children: const [
                        TextSpan(text: 'Signalement '),
                        TextSpan(
                          text: 'envoyé.',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 36),
                    child: Text(
                      'Un administrateur examinera votre dossier.',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 14,
                        height: 20 / 14,
                        color: CouleursSopi.texteSecondaire,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Positioned(
            left: DimensionsSopi.margeCarte,
            right: DimensionsSopi.margeCarte,
            bottom: 24,
            child: BoutonPrimaire(
              libelle: 'Retour à la réservation',
              onPressed: onRetour,
            ),
          ),
        ],
      ),
    );
  }
}
