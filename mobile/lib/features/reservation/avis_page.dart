import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import 'depot_reservation.dart';
import 'modeles.dart';
import 'reservations_controleur.dart';

/// Message affiché une fois l'avis publié.
const messageAvisPublie = 'Merci ! Votre avis a été publié.';

/// Libellé de chaque note, de 1 à 5.
const libellesNotes = <int, String>{
  1: 'Très mauvais',
  2: 'Mauvais',
  3: 'Correct',
  4: 'Bien',
  5: 'Très bien',
};

const longueurMinCommentaire = 3;
const longueurMaxCommentaire = 1000;

/// « Comment s'est passée la prestation ? » (maquette Avis). L'avis est publié tout de suite.
class AvisPage extends ConsumerStatefulWidget {
  const AvisPage({super.key, required this.reservationId});

  final String reservationId;

  @override
  ConsumerState<AvisPage> createState() => _EtatAvis();
}

class _EtatAvis extends ConsumerState<AvisPage> {
  final _commentaire = TextEditingController();
  int _note = 0;
  bool _enCours = false;
  String? _erreur;
  String? _erreurNote;
  String? _erreurCommentaire;

  @override
  void dispose() {
    _commentaire.dispose();
    super.dispose();
  }

  /// Message d'erreur local du commentaire (facultatif, mais 3 à 1000 caractères s'il est rempli).
  String? _validerCommentaire() {
    final n = _commentaire.text.trim().length;
    if (n == 0) return null;
    if (n < longueurMinCommentaire) {
      return 'Le commentaire doit contenir au moins $longueurMinCommentaire caractères.';
    }
    if (n > longueurMaxCommentaire) {
      return 'Le commentaire ne peut pas dépasser $longueurMaxCommentaire caractères.';
    }
    return null;
  }

  Future<void> _publier() async {
    final erreurNote = _note == 0 ? 'Choisissez une note de 1 à 5.' : null;
    final erreurCommentaire = _validerCommentaire();
    if (erreurNote != null || erreurCommentaire != null) {
      setState(() {
        _erreurNote = erreurNote;
        _erreurCommentaire = erreurCommentaire;
        _erreur = null;
      });
      return;
    }
    if (_enCours) return;
    setState(() {
      _enCours = true;
      _erreur = null;
      _erreurNote = null;
      _erreurCommentaire = null;
    });
    final texte = _commentaire.text.trim();
    try {
      await ref
          .read(depotReservationProvider)
          .publierAvis(
            widget.reservationId,
            note: _note,
            commentaire: texte.isEmpty ? null : texte,
          );
    } catch (e) {
      if (!mounted) return;
      final erreur = ErreurApi.depuis(e);
      setState(() {
        _enCours = false;
        if (erreur is ErreurValidation) {
          _erreurNote = erreur.premierMessage('note');
          _erreurCommentaire = erreur.premierMessage('commentaire');
          if (_erreurNote == null && _erreurCommentaire == null) {
            _erreur = erreur.message;
          }
        } else {
          _erreur = erreur.message;
        }
      });
      return;
    }
    if (!mounted) return;
    rafraichirReservations(ref);
    ref.invalidate(detailReservationProvider(widget.reservationId));
    final messager = ScaffoldMessenger.of(context);
    messager.showSnackBar(const SnackBar(content: Text(messageAvisPublie)));
    if (context.canPop()) {
      context.pop();
    } else {
      context.go(Chemins.detailReservation(widget.reservationId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(detailReservationProvider(widget.reservationId));
    final r = async.value;
    final theme = Theme.of(context);
    final bas = MediaQuery.viewInsetsOf(context).bottom;
    final haut = MediaQuery.paddingOf(context).top;

    return Scaffold(
      body: Stack(
        children: [
          const Positioned(
            left: 0,
            right: 0,
            top: 0,
            height: 230,
            child: DescenteDepuisLeHaut(
              child: ClipRRect(
                borderRadius: BorderRadius.vertical(
                  bottom: Radius.circular(DimensionsSopi.rayonEntete),
                ),
                child: FondDegrade(child: SizedBox.expand()),
              ),
            ),
          ),
          Column(
            children: [
              Expanded(
                child: ListView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: EdgeInsets.fromLTRB(
                    0,
                    haut + 24,
                    0,
                    DimensionsSopi.espace,
                  ),
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: DimensionsSopi.margeEcran,
                      ),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: _BoutonFermer(
                          onPressed: _enCours
                              ? null
                              : () => context.canPop()
                                    ? context.pop()
                                    : context.go(Chemins.reservations),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
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
                            TextSpan(text: "Comment s'est\n"),
                            TextSpan(
                              text: 'passée la prestation ?',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: DimensionsSopi.margeCarte,
                      ),
                      child: r == null
                          ? (async.hasError
                                ? EtatErreur(
                                    message: ErreurApi.depuis(
                                      async.error!,
                                    ).message,
                                    onReessayer: () => ref.invalidate(
                                      detailReservationProvider(
                                        widget.reservationId,
                                      ),
                                    ),
                                  )
                                : const Padding(
                                    padding: EdgeInsets.only(top: 40),
                                    child: EtatChargement(),
                                  ))
                          : _CarteAvis(
                              reservation: r,
                              note: _note,
                              commentaire: _commentaire,
                              enCours: _enCours,
                              erreurNote: _erreurNote,
                              erreurCommentaire: _erreurCommentaire,
                              onNote: (n) => setState(() {
                                _note = n;
                                _erreurNote = null;
                              }),
                              onCommentaire: () => setState(() {
                                _erreurCommentaire = null;
                              }),
                            ),
                    ),
                    if (_erreur != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(
                          DimensionsSopi.margeCarte,
                          14,
                          DimensionsSopi.margeCarte,
                          0,
                        ),
                        child: BandeauErreur(message: _erreur!),
                      ),
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
                      libelle: 'Publier mon avis',
                      chargement: _enCours,
                      onPressed: r == null || _enCours ? null : _publier,
                    ),
                    if (bas == 0) ...[
                      const SizedBox(height: 10),
                      Text(
                        'Votre avis est visible immédiatement sur la fiche du prestataire.',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 12,
                          color: CouleursSopi.texteDiscret,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              SizedBox(height: bas),
            ],
          ),
        ],
      ),
    );
  }
}

/// Bouton « fermer » : pilule marine 64×44 avec une croix blanche (maquette Avis).
class _BoutonFermer extends StatelessWidget {
  const _BoutonFermer({required this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: 'Fermer',
    child: Material(
      color: CouleursSopi.marine,
      borderRadius: BorderRadius.circular(DimensionsSopi.hauteurRetour / 2),
      child: InkWell(
        borderRadius: BorderRadius.circular(DimensionsSopi.hauteurRetour / 2),
        onTap: onPressed,
        child: const SizedBox(
          width: DimensionsSopi.largeurRetour,
          height: DimensionsSopi.hauteurRetour,
          child: Icon(Icons.close_rounded, size: 22, color: CouleursSopi.blanc),
        ),
      ),
    ),
  );
}

class _CarteAvis extends StatelessWidget {
  const _CarteAvis({
    required this.reservation,
    required this.note,
    required this.commentaire,
    required this.enCours,
    required this.erreurNote,
    required this.erreurCommentaire,
    required this.onNote,
    required this.onCommentaire,
  });

  final ReservationClient reservation;
  final int note;
  final TextEditingController commentaire;
  final bool enCours;
  final String? erreurNote;
  final String? erreurCommentaire;
  final ValueChanged<int> onNote;
  final VoidCallback onCommentaire;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final date = DateFormat('d MMM', 'fr_FR').format(reservation.debut.toUtc());
    final aErreur = erreurCommentaire != null;
    final bordure = OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(
        color: aErreur ? CouleursSopi.erreurTexte : CouleursSopi.bordureForte,
      ),
    );
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(
          DimensionsSopi.rayonCarteFormulaire,
        ),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            reservation.prestataireNom,
            textAlign: TextAlign.center,
            style: theme.textTheme.titleSmall?.copyWith(
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
          Text(
            '${reservation.prestationNom} · $date',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 13,
              color: CouleursSopi.texteDiscret,
            ),
          ),
          const SizedBox(height: 18),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var i = 1; i <= 5; i++) ...[
                if (i > 1) const SizedBox(width: 10),
                Semantics(
                  button: true,
                  label: '$i sur 5 : ${libellesNotes[i]}',
                  selected: i == note,
                  child: GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    key: ValueKey('etoile-$i'),
                    onTap: enCours ? null : () => onNote(i),
                    child: Icon(
                      i <= note
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      size: 40,
                      color: i <= note
                          ? CouleursSopi.bleu
                          : CouleursSopi.texteSurMarine,
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 8),
          Text(
            note == 0 ? 'Touchez une étoile' : libellesNotes[note]!,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: note == 0 ? CouleursSopi.texteDiscret : CouleursSopi.bleu,
            ),
          ),
          if (erreurNote != null) ...[
            const SizedBox(height: 6),
            Text(
              erreurNote!,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.erreurTexte,
              ),
            ),
          ],
          const SizedBox(height: 18),
          Text(
            'Votre commentaire (facultatif)',
            style: theme.textTheme.labelMedium?.copyWith(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
          const SizedBox(height: 6),
          TextField(
            controller: commentaire,
            enabled: !enCours,
            minLines: 4,
            maxLines: 4,
            maxLength: longueurMaxCommentaire,
            textCapitalization: TextCapitalization.sentences,
            keyboardType: TextInputType.multiline,
            onChanged: (_) => onCommentaire(),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              color: CouleursSopi.marine,
            ),
            decoration: InputDecoration(
              filled: true,
              fillColor: CouleursSopi.champ,
              contentPadding: const EdgeInsets.all(14),
              enabledBorder: bordure,
              focusedBorder: bordure.copyWith(
                borderSide: BorderSide(
                  color: aErreur ? CouleursSopi.erreurTexte : CouleursSopi.bleu,
                  width: 1.5,
                ),
              ),
              disabledBorder: bordure,
              errorText: erreurCommentaire,
              counterStyle: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.texteDiscret,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
