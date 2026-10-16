import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/ligne_montant.dart';
import 'annulation_reservation.dart';
import 'demande_envoyee_page.dart'
    show delaiPaiementHeures, delaiReponsePrestataireHeures;
import 'depot_reservation.dart';
import 'lanceur_url.dart';
import 'modeles.dart';
import 'presentation_reservation.dart';
import 'reservations_controleur.dart';

/// Délai entre deux relectures automatiques tant que la réservation attend une réponse ou un paiement.
const intervalleActualisationDetail = Duration(seconds: 30);

/// Détail d'une réservation (maquettes DetailReservation, DetailEnAttente, DetailAPayer) :
/// la présentation dépend du statut renvoyé par l'API.
class DetailReservationPage extends ConsumerStatefulWidget {
  const DetailReservationPage({super.key, required this.reservationId});

  final String reservationId;

  @override
  ConsumerState<DetailReservationPage> createState() => _EtatDetail();
}

class _EtatDetail extends ConsumerState<DetailReservationPage>
    with WidgetsBindingObserver {
  Timer? _minuteur;
  bool _enPremierPlan = true;
  Timer? _finCopie;
  bool _copie = false;
  String? _erreurAction;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _minuteur = Timer.periodic(intervalleActualisationDetail, (_) => _tic());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _minuteur?.cancel();
    _finCopie?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState etat) {
    _enPremierPlan = etat == AppLifecycleState.resumed;
  }

  /// Relit la réservation seulement si elle attend encore quelque chose et que l'écran est visible.
  void _tic() {
    if (!mounted || !_enPremierPlan) return;
    if (ModalRoute.of(context)?.isCurrent == false) return;
    final r = ref.read(detailReservationProvider(widget.reservationId)).value;
    if (r == null) return;
    if (r.statut == StatutReservation.enAttente ||
        r.statut == StatutReservation.acceptee) {
      _relire();
    }
  }

  void _relire() =>
      ref.invalidate(detailReservationProvider(widget.reservationId));

  Future<void> _actualiser() async {
    _relire();
    try {
      await ref.read(detailReservationProvider(widget.reservationId).future);
    } catch (_) {
      // L'erreur s'affiche déjà par l'état du fournisseur.
    }
  }

  Future<void> _copier(String code) async {
    await Clipboard.setData(ClipboardData(text: code));
    if (!mounted) return;
    setState(() => _copie = true);
    _finCopie?.cancel();
    _finCopie = Timer(const Duration(seconds: 2), () {
      if (mounted) setState(() => _copie = false);
    });
  }

  Future<void> _appeler(String telephone) async {
    final lance = ref.read(lanceurUrlProvider);
    final ouvert = await lance(Uri(scheme: 'tel', path: telephone));
    if (!mounted) return;
    setState(
      () => _erreurAction = ouvert
          ? null
          : 'Impossible de lancer l\'appel depuis cet appareil.',
    );
  }

  Future<void> _aller(String chemin) async {
    await context.push(chemin);
    if (!mounted) return;
    _relire();
    rafraichirReservations(ref);
  }

  Future<void> _annuler(ReservationClient r) async {
    await ouvrirAnnulation(context, ref, r);
    if (!mounted) return;
    _relire();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(detailReservationProvider(widget.reservationId));
    final r = async.value;
    return Scaffold(
      body: Stack(
        children: [
          Positioned(
            left: 0,
            right: 0,
            top: 0,
            height: 250,
            child: DescenteDepuisLeHaut(
              child: ClipRRect(
                borderRadius: const BorderRadius.vertical(
                  bottom: Radius.circular(DimensionsSopi.rayonEntete),
                ),
                child: const FondDegrade(child: SizedBox.expand()),
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Column(
              children: [
                const _BarreHaute(),
                Expanded(child: _corps(async, r)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _corps(AsyncValue<ReservationClient> async, ReservationClient? r) {
    if (r == null) {
      if (async.hasError) {
        return EtatErreur(
          message: ErreurApi.depuis(async.error!).message,
          onReessayer: _relire,
        );
      }
      return const _SqueletteDetail();
    }
    final actions = _actions(r);
    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: _actualiser,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: _EnTeteReservation(reservation: r),
                ),
                const SizedBox(height: 26),
                ..._blocs(r),
              ],
            ),
          ),
        ),
        if (actions != null)
          Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              8,
              16,
              24 + MediaQuery.paddingOf(context).bottom,
            ),
            child: actions,
          ),
      ],
    );
  }

  List<Widget> _blocs(ReservationClient r) {
    final theme = Theme.of(context);
    final cle = (prestataireId: r.prestataireId, prestationId: r.prestationId);
    final acompte = ref.watch(acomptePrestationProvider(cle)).value;
    final montantAcompte = acompteConnu(r) ?? acompte?.montant;

    final blocs = <Widget>[];

    if (r.statut == StatutReservation.enAttente) {
      blocs.add(
        _BlocInfo(
          message:
              'En attente de la réponse du prestataire. Il a jusqu\'à $delaiReponsePrestataireHeures h pour répondre. Vous êtes prévenu dès qu\'il répond.',
        ),
      );
    } else if (r.statut == StatutReservation.acceptee) {
      blocs.add(
        const _BlocAcceptee(
          message: 'Payez l\'acompte pour confirmer votre réservation.',
          delai:
              'Vous avez $delaiPaiementHeures h après l\'acceptation pour payer.',
        ),
      );
    } else if (r.statut.ferme) {
      blocs.add(
        BandeauErreur(
          message:
              r.motifRefus ??
              (r.statut == StatutReservation.refusee
                  ? 'Le prestataire a refusé votre demande.'
                  : 'Cette réservation a été annulée.'),
        ),
      );
    }

    final code = r.codeReservation;
    final avecCode =
        code != null &&
        (r.statut == StatutReservation.confirmee ||
            r.statut == StatutReservation.enCours);
    if (avecCode) {
      blocs.add(
        _BlocCode(code: code, copie: _copie, onCopier: () => _copier(code)),
      );
    }

    if (blocs.isNotEmpty) blocs.add(const SizedBox(height: 14));

    blocs.add(
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
        decoration: _decorationCarte(),
        child: Column(
          children: [
            LigneMontant(
              libelle: 'Tarif du service',
              valeur: formaterArgent(r.prix),
              couleurValeur: CouleursSopi.marine,
            ),
            if (montantAcompte != null) ...[
              LigneMontant(
                libelle: _libelleAcompte(r, acompte?.pourcentage),
                valeur: formaterArgent(montantAcompte),
                couleurValeur: CouleursSopi.bleu,
              ),
              if (!r.statut.ferme)
                LigneMontant(
                  libelle: 'Reste à payer sur place',
                  valeur: formaterArgent(r.prix - montantAcompte),
                  couleurValeur: CouleursSopi.marine,
                ),
            ],
          ],
        ),
      ),
    );

    if (!r.statut.ferme) {
      blocs
        ..add(const SizedBox(height: 18))
        ..add(
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Text(
              'Suivi',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        )
        ..add(const SizedBox(height: 10))
        ..add(_CarteSuivi(statut: r.statut));
    }

    if (r.statut == StatutReservation.terminee && r.avis != null) {
      blocs
        ..add(const SizedBox(height: 14))
        ..add(
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(18),
            decoration: _decorationCarte(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Votre avis : ${r.avis!.note}/5',
                  style: theme.textTheme.titleSmall,
                ),
                if (r.avis!.commentaire != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    r.avis!.commentaire!,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
    }

    if (_erreurAction != null) {
      blocs
        ..add(const SizedBox(height: 14))
        ..add(BandeauErreur(message: _erreurAction!));
    }
    return blocs;
  }

  /// Boutons d'action fixés en bas, selon le statut et les droits renvoyés par l'API.
  Widget? _actions(ReservationClient r) {
    Widget contour(String libelle, VoidCallback surAppui) => BoutonCompact(
      libelle: libelle,
      onPressed: surAppui,
      ton: TonBoutonCompact.contour,
      hauteur: 50,
      taille: 14,
      pleineLargeur: true,
    );

    final telephone = r.telephone;
    final enfants = <Widget>[];
    switch (r.statut) {
      case StatutReservation.enAttente:
        if (r.peutAnnuler) {
          enfants.add(contour('Annuler la demande', () => _annuler(r)));
        }
      case StatutReservation.acceptee:
        if (r.peutPayer) {
          final montant =
              acompteConnu(r) ??
              ref
                  .watch(
                    acomptePrestationProvider((
                      prestataireId: r.prestataireId,
                      prestationId: r.prestationId,
                    )),
                  )
                  .value
                  ?.montant;
          enfants.add(
            BoutonPrimaire(
              libelle: montant == null
                  ? 'Payer l\'acompte'
                  : 'Payer l\'acompte · ${formaterArgent(montant)}',
              onPressed: () => _aller(Chemins.paiement(r.id)),
            ),
          );
        }
        if (r.peutAnnuler) {
          enfants.add(contour('Annuler la demande', () => _annuler(r)));
        }
      case StatutReservation.confirmee || StatutReservation.enCours:
        if (telephone != null) {
          enfants.add(
            BoutonPrimaire(
              libelle: 'Contacter le prestataire',
              onPressed: () => _appeler(telephone),
            ),
          );
        }
        enfants.add(
          Row(
            children: [
              if (r.peutAnnuler) ...[
                Expanded(child: contour('Annuler', () => _annuler(r))),
                const SizedBox(width: 10),
              ],
              Expanded(
                child: contour(
                  'Signaler un problème',
                  () => _aller(Chemins.litige(r.id)),
                ),
              ),
            ],
          ),
        );
      case StatutReservation.terminee:
        if (r.peutNoter) {
          enfants.add(
            BoutonPrimaire(
              libelle: 'Laisser un avis',
              onPressed: () => _aller(Chemins.avis(r.id)),
            ),
          );
        }
        enfants.add(
          contour('Signaler un problème', () => _aller(Chemins.litige(r.id))),
        );
      case StatutReservation.annulee || StatutReservation.refusee:
        break;
    }
    if (enfants.isEmpty) return null;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < enfants.length; i++) ...[
          if (i > 0) const SizedBox(height: 10),
          enfants[i],
        ],
      ],
    );
  }

  String _libelleAcompte(ReservationClient r, num? pourcentage) {
    final base = switch (r.paiement?.statut) {
      'reussi' => 'Acompte payé',
      'rembourse' => 'Acompte remboursé',
      _ => 'Acompte à payer',
    };
    if (pourcentage == null) return base;
    final p = pourcentage == pourcentage.round()
        ? pourcentage.round()
        : pourcentage;
    return '$base ($p %)';
  }
}

BoxDecoration _decorationCarte() => BoxDecoration(
  color: CouleursSopi.blanc,
  borderRadius: BorderRadius.circular(26),
  border: Border.all(color: CouleursSopi.bordure),
);

class _BarreHaute extends StatelessWidget {
  const _BarreHaute();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      DimensionsSopi.margeEcran,
      24,
      DimensionsSopi.margeEcran,
      0,
    ),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        BoutonRetour(
          onPressed: () => context.canPop()
              ? context.pop()
              : context.go(Chemins.reservations),
        ),
        Text(
          'Réservation',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(width: DimensionsSopi.largeurRetour),
      ],
    ),
  );
}

class _EnTeteReservation extends StatelessWidget {
  const _EnTeteReservation({required this.reservation});

  final ReservationClient reservation;

  @override
  Widget build(BuildContext context) {
    final r = reservation;
    final theme = Theme.of(context);
    final (libelle, fond) = switch (r.statut) {
      StatutReservation.acceptee when r.peutPayer => (
        'Acceptée · à payer',
        CouleursSopi.bleu,
      ),
      StatutReservation.enAttente => (
        r.statut.libelle,
        CouleursSopi.texteSecondaire,
      ),
      StatutReservation.terminee => (
        r.statut.libelle,
        CouleursSopi.succesTexte,
      ),
      StatutReservation.annulee ||
      StatutReservation.refusee => (r.statut.libelle, CouleursSopi.erreurTexte),
      _ => (r.statut.libelle, CouleursSopi.bleu),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          height: 28,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: fond,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Text(
            libelle,
            style: theme.textTheme.labelMedium?.copyWith(
              color: CouleursSopi.blanc,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(height: 10),
        Text(
          r.prestataireNom,
          style: theme.textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w800,
            height: 34 / 28,
          ),
        ),
        Text(
          '${r.prestationNom} · ${formaterDate(r.debut)}',
          style: theme.textTheme.bodyMedium?.copyWith(
            fontSize: 14,
            color: CouleursSopi.texteSecondaire,
          ),
        ),
      ],
    );
  }
}

/// Bloc d'information bleu pâle (« En attente de la réponse du prestataire… »).
class _BlocInfo extends StatelessWidget {
  const _BlocInfo({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
    decoration: BoxDecoration(
      color: CouleursSopi.bleuPastel,
      borderRadius: BorderRadius.circular(24),
    ),
    child: Text(
      message,
      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
        fontSize: 13,
        height: 19 / 13,
        color: CouleursSopi.bleuProfond,
      ),
    ),
  );
}

/// Bloc bleu « Le prestataire a accepté » (maquette DetailAPayer). L'API ne renvoie pas l'échéance
/// du paiement : on rappelle le délai de la règle plutôt que d'afficher un compte à rebours.
class _BlocAcceptee extends StatelessWidget {
  const _BlocAcceptee({required this.message, required this.delai});

  final String message;
  final String delai;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: CouleursSopi.bleu,
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Le prestataire a accepté',
            style: theme.textTheme.labelLarge?.copyWith(
              color: CouleursSopi.blanc,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            message,
            style: theme.textTheme.bodySmall?.copyWith(
              color: CouleursSopi.blanc.withValues(alpha: .9),
              height: 17 / 12,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            delai,
            style: theme.textTheme.bodySmall?.copyWith(
              color: CouleursSopi.blanc.withValues(alpha: .9),
              height: 17 / 12,
            ),
          ),
        ],
      ),
    );
  }
}

/// Code de démarrage à donner au prestataire (maquette DetailReservation).
class _BlocCode extends StatelessWidget {
  const _BlocCode({
    required this.code,
    required this.copie,
    required this.onCopier,
  });

  final String code;
  final bool copie;
  final VoidCallback onCopier;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: CouleursSopi.marine,
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Code de démarrage',
                style: theme.textTheme.labelLarge?.copyWith(
                  color: CouleursSopi.blanc,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Semantics(
                button: true,
                label: 'Copier le code',
                child: Material(
                  color: CouleursSopi.blanc.withValues(alpha: .16),
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: onCopier,
                    child: Container(
                      height: 28,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      alignment: Alignment.center,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (copie) ...[
                            const Icon(
                              Icons.check_rounded,
                              size: 14,
                              color: CouleursSopi.blanc,
                            ),
                            const SizedBox(width: 4),
                          ],
                          Text(
                            copie ? 'Copié' : 'Copier',
                            style: theme.textTheme.labelMedium?.copyWith(
                              color: CouleursSopi.blanc,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              for (var i = 0; i < code.length; i++) ...[
                if (i > 0) const SizedBox(width: 6),
                Expanded(
                  child: Container(
                    height: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: CouleursSopi.blanc,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      code[i],
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: CouleursSopi.marine,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Donnez ce code au prestataire à son arrivée.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: CouleursSopi.blanc.withValues(alpha: .8),
            ),
          ),
        ],
      ),
    );
  }
}

/// Étape de suivi : faite, en cours (cercle bleu) ou à venir (cercle gris).
enum _EtatEtape { faite, enCours, aVenir }

/// Suivi de la réservation, déduit du statut (maquettes : pastille pleine, cercle bleu, cercle gris).
class _CarteSuivi extends StatelessWidget {
  const _CarteSuivi({required this.statut});

  final StatutReservation statut;

  @override
  Widget build(BuildContext context) {
    final (accepte, paye, prestation) = switch (statut) {
      StatutReservation.enAttente => (
        _EtatEtape.enCours,
        _EtatEtape.aVenir,
        _EtatEtape.aVenir,
      ),
      StatutReservation.acceptee => (
        _EtatEtape.faite,
        _EtatEtape.enCours,
        _EtatEtape.aVenir,
      ),
      StatutReservation.confirmee => (
        _EtatEtape.faite,
        _EtatEtape.faite,
        _EtatEtape.aVenir,
      ),
      StatutReservation.enCours => (
        _EtatEtape.faite,
        _EtatEtape.faite,
        _EtatEtape.enCours,
      ),
      _ => (_EtatEtape.faite, _EtatEtape.faite, _EtatEtape.faite),
    };
    final libellePrestation = switch (prestation) {
      _EtatEtape.enCours => 'Prestation en cours',
      _EtatEtape.faite => 'Prestation terminée',
      _EtatEtape.aVenir => 'Prestation',
    };
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      decoration: _decorationCarte(),
      child: Column(
        children: [
          const _Etape('Demande envoyée', _EtatEtape.faite),
          const SizedBox(height: 10),
          _Etape('Acceptée par le prestataire', accepte),
          const SizedBox(height: 10),
          _Etape('Acompte payé, réservation confirmée', paye),
          const SizedBox(height: 10),
          _Etape(libellePrestation, prestation),
        ],
      ),
    );
  }
}

class _Etape extends StatelessWidget {
  const _Etape(this.libelle, this.etat);

  final String libelle;
  final _EtatEtape etat;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final puce = switch (etat) {
      _EtatEtape.faite => const DecoratedBox(
        decoration: BoxDecoration(
          color: CouleursSopi.bleu,
          shape: BoxShape.circle,
        ),
      ),
      _EtatEtape.enCours => DecoratedBox(
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: CouleursSopi.bleu, width: 2),
        ),
      ),
      _EtatEtape.aVenir => DecoratedBox(
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: CouleursSopi.bordureForte, width: 2),
        ),
      ),
    };
    return Row(
      children: [
        SizedBox(width: 14, height: 14, child: puce),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            libelle,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              fontWeight: switch (etat) {
                _EtatEtape.faite => FontWeight.w600,
                _EtatEtape.enCours => FontWeight.w700,
                _EtatEtape.aVenir => FontWeight.w400,
              },
              color: etat == _EtatEtape.aVenir
                  ? CouleursSopi.texteSecondaire
                  : CouleursSopi.marine,
            ),
          ),
        ),
      ],
    );
  }
}

class _SqueletteDetail extends StatelessWidget {
  const _SqueletteDetail();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.fromLTRB(24, 20, 24, 0),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Squelette(largeur: 110, hauteur: 28, rayon: 14),
        SizedBox(height: 12),
        Squelette(largeur: 200, hauteur: 30),
        SizedBox(height: 8),
        Squelette(largeur: 240, hauteur: 16),
        SizedBox(height: 44),
        Squelette(hauteur: 90, rayon: 26),
        SizedBox(height: 14),
        Squelette(hauteur: 130, rayon: 26),
        SizedBox(height: 14),
        Squelette(hauteur: 150, rayon: 26),
      ],
    ),
  );
}
