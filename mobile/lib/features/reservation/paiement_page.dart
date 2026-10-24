import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/config/support.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/theme/disposition.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import 'depot_reservation.dart';
import 'modeles.dart';
import 'presentation_reservation.dart';
import 'reservations_controleur.dart';
import 'widgets/composants.dart';

/// Délai entre deux vérifications du paiement après le retour de la page du prestataire de paiement.
const intervalleVerificationPaiement = Duration(seconds: 3);

/// Durée maximale de la vérification : au-delà, le paiement est présenté comme non abouti.
const dureeVerificationPaiement = Duration(minutes: 3);

/// Nombre de vérifications possibles (la première est immédiate).
final _maxVerifications =
    1 +
    dureeVerificationPaiement.inSeconds ~/
        intervalleVerificationPaiement.inSeconds;

enum _Phase { recapitulatif, verification, succes, echec }

/// Paiement de l'acompte : récapitulatif (maquette Paiement), ouverture de la page du prestataire de
/// paiement, puis vérification au retour jusqu'à « Paiement réussi » (PaiementOk) ou « non abouti » (PaiementEchec).
/// Les montants viennent toujours de l'API ; l'application ne calcule rien.
class PaiementPage extends ConsumerStatefulWidget {
  const PaiementPage({super.key, required this.reservationId});

  final String reservationId;

  @override
  ConsumerState<PaiementPage> createState() => _EtatPaiement();
}

class _EtatPaiement extends ConsumerState<PaiementPage>
    with WidgetsBindingObserver {
  _Phase _phase = _Phase.recapitulatif;
  bool _chargement = false;
  String? _erreur;
  bool _lienOuvert = false;
  SessionPaiement? _session;
  ReservationClient? _finale;

  Timer? _minuteur;
  int _verifications = 0;
  bool _verificationEnCours = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _minuteur?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState etat) {
    if (etat == AppLifecycleState.resumed &&
        _lienOuvert &&
        _phase == _Phase.recapitulatif) {
      _demarrerVerification();
    }
  }

  Future<void> _payer() async {
    if (_chargement) return;
    setState(() {
      _chargement = true;
      _erreur = null;
    });
    try {
      final session = await ref
          .read(depotReservationProvider)
          .payer(widget.reservationId);
      final adresse = Uri.tryParse(session.urlPaiement);
      // Seule une page sécurisée peut recevoir le client.
      if (adresse == null || adresse.scheme != 'https') {
        throw const FormatException('Adresse de paiement invalide.');
      }
      final ouvert = await ref.read(lanceurUrlProvider)(adresse);
      if (!mounted) return;
      setState(() {
        _chargement = false;
        _session = session;
        _lienOuvert = ouvert;
        _erreur = ouvert
            ? null
            : 'Impossible d\'ouvrir la page de paiement. Veuillez réessayer.';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _chargement = false;
        _erreur = e is FormatException
            ? 'Le service de paiement a renvoyé une adresse invalide. Veuillez réessayer.'
            : ErreurApi.depuis(e).message;
      });
    }
  }

  void _demarrerVerification() {
    _minuteur?.cancel();
    _verifications = 0;
    setState(() => _phase = _Phase.verification);
    _verifier();
    _minuteur = Timer.periodic(
      intervalleVerificationPaiement,
      (_) => _verifier(),
    );
  }

  Future<void> _verifier() async {
    if (_verificationEnCours) return;
    _verificationEnCours = true;
    _verifications++;
    try {
      final r = await ref
          .read(depotReservationProvider)
          .reservation(widget.reservationId);
      if (!mounted || _phase != _Phase.verification) return;
      if (r.statut == StatutReservation.confirmee ||
          r.statut == StatutReservation.enCours ||
          r.statut == StatutReservation.terminee) {
        _conclure(_Phase.succes, r);
      } else if (r.paiement?.echoue == true || r.statut.ferme) {
        _conclure(_Phase.echec, r);
      } else if (_verifications >= _maxVerifications) {
        _conclure(_Phase.echec, r);
      }
    } catch (_) {
      // Une coupure réseau passagère ne conclut rien : on réessaie jusqu'au délai maximal.
      if (mounted &&
          _phase == _Phase.verification &&
          _verifications >= _maxVerifications) {
        _conclure(_Phase.echec, null);
      }
    } finally {
      _verificationEnCours = false;
    }
  }

  void _conclure(_Phase phase, ReservationClient? r) {
    _minuteur?.cancel();
    rafraichirReservations(ref);
    ref.invalidate(detailReservationProvider(widget.reservationId));
    setState(() {
      _phase = phase;
      _finale = r;
      _lienOuvert = false;
    });
  }

  void _reessayer() {
    ref.invalidate(detailReservationProvider(widget.reservationId));
    setState(() {
      _phase = _Phase.recapitulatif;
      _erreur = null;
      _session = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    switch (_phase) {
      case _Phase.recapitulatif:
        return _Recapitulatif(
          reservationId: widget.reservationId,
          chargement: _chargement,
          erreur: _erreur,
          onPayer: _payer,
        );
      case _Phase.verification:
        return _Verification(onVerifier: _demarrerVerification);
      case _Phase.succes:
        return PopScope(
          canPop: false,
          onPopInvokedWithResult: (_, _) => context.go(Chemins.reservations),
          child: _Succes(
            reservationId: widget.reservationId,
            reservation: _finale,
            session: _session,
          ),
        );
      case _Phase.echec:
        return PopScope(
          canPop: false,
          onPopInvokedWithResult: (_, _) => context.go(Chemins.reservations),
          child: _Echec(onReessayer: _reessayer),
        );
    }
  }
}

BoxDecoration _decorationCarte() => BoxDecoration(
  color: CouleursSopi.blanc,
  borderRadius: BorderRadius.circular(26),
  boxShadow: OmbresSopi.carte,
);

class _LigneRecap extends StatelessWidget {
  const _LigneRecap(this.libelle, this.valeur, {this.couleur});

  final String libelle;
  final String valeur;
  final Color? couleur;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Flexible(
          child: Text(
            libelle,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            valeur,
            textAlign: TextAlign.right,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: couleur ?? CouleursSopi.texte,
            ),
          ),
        ),
      ],
    );
  }
}

class _Recapitulatif extends ConsumerWidget {
  const _Recapitulatif({
    required this.reservationId,
    required this.chargement,
    required this.erreur,
    required this.onPayer,
  });

  final String reservationId;
  final bool chargement;
  final String? erreur;
  final VoidCallback onPayer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(detailReservationProvider(reservationId));
    final r = async.value;
    return Scaffold(
      body: Stack(
        children: [
          SafeArea(
            top: false,
            bottom: false,
            child: Column(
              children: [
                Padding(
                  padding: EdgeInsets.fromLTRB(
                    DimensionsSopi.margeEcran,
                    hautEcran(context),
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
                      Expanded(
                        child: Text(
                          'Récapitulatif',
                          textAlign: TextAlign.center,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                      ),
                      const SizedBox(width: DimensionsSopi.largeurRetour),
                    ],
                  ),
                ),
                Expanded(
                  child: r == null
                      ? (async.hasError
                            ? EtatErreur(
                                message: ErreurApi.depuis(async.error!).message,
                                onReessayer: () => ref.invalidate(
                                  detailReservationProvider(reservationId),
                                ),
                              )
                            : const _SqueletteRecap())
                      : _Contenu(
                          reservation: r,
                          chargement: chargement,
                          erreur: erreur,
                          onPayer: onPayer,
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Contenu extends ConsumerWidget {
  const _Contenu({
    required this.reservation,
    required this.chargement,
    required this.erreur,
    required this.onPayer,
  });

  final ReservationClient reservation;
  final bool chargement;
  final String? erreur;
  final VoidCallback onPayer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r = reservation;
    final theme = Theme.of(context);
    final cle = (prestataireId: r.prestataireId, prestationId: r.prestationId);
    final profil = ref.watch(acomptePrestationProvider(cle));
    final connu = acompteConnu(r);
    final montant = connu ?? profil.value?.montant;
    final pourcentage = profil.value?.pourcentage;
    final limite = r.paiementAvant;

    if (montant == null) {
      if (profil.hasError) {
        return EtatErreur(
          message: ErreurApi.depuis(profil.error!).message,
          onReessayer: () => ref.invalidate(acomptePrestationProvider(cle)),
        );
      }
      return const _SqueletteRecap();
    }

    final bas = MediaQuery.paddingOf(context).bottom;
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 24, 16, 16),
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Confirmez et',
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w400,
                        fontSize: 28,
                        height: 34 / 28,
                      ),
                    ),
                    Text(
                      'payez l\'acompte',
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        fontSize: 28,
                        height: 34 / 28,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 56),
              Apparition(
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: _decorationCarte(),
                  child: Column(
                    children: [
                      _LigneRecap('Prestataire', r.prestataireNom),
                      const SizedBox(height: 12),
                      _LigneRecap('Service', r.prestationNom),
                      const SizedBox(height: 12),
                      _LigneRecap('Date', formaterDate(r.debut)),
                      const SizedBox(height: 12),
                      const Divider(height: 1, color: CouleursSopi.separateur),
                      const SizedBox(height: 12),
                      _LigneRecap('Tarif du service', formaterArgent(r.prix)),
                      const SizedBox(height: 12),
                      _LigneRecap(
                        'Reste à payer sur place',
                        formaterArgent(r.prix - montant),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 65),
              Apparition.indexee(
                index: 1,
                child: Container(
                  constraints: const BoxConstraints(minHeight: 92),
                  padding: const EdgeInsets.symmetric(horizontal: 22),
                  decoration: BoxDecoration(
                    color: CouleursSopi.bleu,
                    borderRadius: BorderRadius.circular(26),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              pourcentage == null
                                  ? 'Acompte à payer maintenant'
                                  : 'Acompte à payer maintenant (${_pourcent(pourcentage)} %)',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: CouleursSopi.blanc.withValues(
                                  alpha: .85,
                                ),
                              ),
                            ),
                            Text(
                              formaterArgent(montant),
                              style: theme.textTheme.headlineMedium?.copyWith(
                                color: CouleursSopi.blanc,
                                fontWeight: FontWeight.w800,
                                fontSize: 28,
                                height: 34 / 28,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (limite != null) ...[
                        const SizedBox(width: 12),
                        PastilleDecompte(
                          expiration: limite,
                          hauteur: 34,
                          maintenant: ref.read(horlogeProvider),
                          onExpire: () =>
                              ref.invalidate(detailReservationProvider(r.id)),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Apparition.indexee(
                index: 2,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: CouleursSopi.blanc,
                    borderRadius: BorderRadius.circular(22),
                    boxShadow: OmbresSopi.carte,
                  ),
                  child: Row(
                    children: [
                      const _LogoMoyen('assets/wave.png'),
                      const SizedBox(width: 6),
                      const _LogoMoyen('assets/orange_money.png'),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Paiement sécurisé. Vous choisirez Wave ou Orange Money sur la page suivante.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: CouleursSopi.texteSecondaire,
                            height: 17 / 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 22),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: Text(
                  'Annulation possible avant le rendez-vous, mais l\'acompte n\'est pas remboursé. Il l\'est si le prestataire annule.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: CouleursSopi.texteSecondaire,
                    height: 17 / 12,
                  ),
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: EdgeInsets.fromLTRB(16, 8, 16, 20 + bas),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (erreur != null) ...[
                BandeauErreur(message: erreur!),
                const SizedBox(height: 10),
              ],
              if (!r.peutPayer)
                const BandeauInfo(
                  message:
                      'Cette réservation ne peut pas être payée pour le moment.',
                )
              else
                BoutonPrimaire(
                  libelle: 'Payer ${formaterArgent(montant)}',
                  hauteur: 56,
                  onPressed: chargement ? null : onPayer,
                  chargement: chargement,
                ),
            ],
          ),
        ),
      ],
    );
  }
}

String _pourcent(num p) => (p == p.round() ? p.round() : p).toString();

/// Logo d'un moyen de paiement (Wave, Orange Money), 40 × 40 arrondi (maquette Paiement).
class _LogoMoyen extends StatelessWidget {
  const _LogoMoyen(this.asset);

  final String asset;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(12),
    child: Image.asset(
      asset,
      width: 40,
      height: 40,
      fit: BoxFit.cover,
      excludeFromSemantics: true,
    ),
  );
}

class _SqueletteRecap extends StatelessWidget {
  const _SqueletteRecap();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.fromLTRB(24, 20, 24, 0),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Squelette(largeur: 180, hauteur: 30),
        SizedBox(height: 8),
        Squelette(largeur: 220, hauteur: 30),
        SizedBox(height: 28),
        Squelette(hauteur: 200, rayon: 26),
        SizedBox(height: 14),
        Squelette(hauteur: 92, rayon: 26),
      ],
    ),
  );
}

/// « Vérification du paiement… » (même présentation que la vérification du code de la maquette CodeVerification).
class _Verification extends StatelessWidget {
  const _Verification({required this.onVerifier});

  final VoidCallback onVerifier;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Stack(
        children: [
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(24, 40, 24, 32),
                  decoration: BoxDecoration(
                    color: CouleursSopi.blanc,
                    borderRadius: BorderRadius.circular(30),
                    boxShadow: OmbresSopi.carte,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Vérification du paiement…',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        'Un instant, nous confirmons votre paiement.',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 14,
                          height: 20 / 14,
                          color: CouleursSopi.texteSecondaire,
                        ),
                      ),
                      const SizedBox(height: 32),
                      Container(
                        width: 96,
                        height: 96,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: CouleursSopi.bleuPale,
                          borderRadius: BorderRadius.circular(28),
                          border: Border.all(
                            color: CouleursSopi.bleu,
                            width: 3,
                          ),
                        ),
                        child: const SizedBox(
                          width: 34,
                          height: 34,
                          child: CircularProgressIndicator(
                            strokeWidth: 4,
                            color: CouleursSopi.bleu,
                            backgroundColor: CouleursSopi.bleuPastel,
                          ),
                        ),
                      ),
                      const SizedBox(height: 28),
                      BoutonCompact(
                        libelle: 'Vérifier de nouveau',
                        onPressed: onVerifier,
                        ton: TonBoutonCompact.contour,
                        hauteur: 46,
                        taille: 14,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Grand rond d'état + titre en deux lignes + phrase (en-tête de PaiementOk et PaiementEchec).
class _EnTeteResultat extends StatelessWidget {
  const _EnTeteResultat({
    required this.fond,
    required this.icone,
    required this.couleurIcone,
    this.tailleIcone = 52,
    required this.ligne2,
    required this.message,
  });

  final Color fond;
  final IconData icone;
  final Color couleurIcone;
  final double tailleIcone;
  final String ligne2;
  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Container(
          width: 112,
          height: 112,
          decoration: BoxDecoration(color: fond, shape: BoxShape.circle),
          child: Icon(icone, size: tailleIcone, color: couleurIcone),
        ),
        const SizedBox(height: 22),
        Text(
          'Paiement',
          textAlign: TextAlign.center,
          style: theme.textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w400,
            fontSize: 28,
            height: 34 / 28,
          ),
        ),
        Text(
          ligne2,
          textAlign: TextAlign.center,
          style: theme.textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w800,
            fontSize: 28,
            height: 34 / 28,
          ),
        ),
        const SizedBox(height: 22),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 40),
          child: Text(
            message,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              height: 20 / 14,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
        ),
      ],
    );
  }
}

/// Mise en page commune des écrans de résultat : en-tête centré, carte, boutons en bas.
class _MiseEnPageResultat extends StatelessWidget {
  const _MiseEnPageResultat({
    required this.entete,
    required this.carte,
    required this.boutons,
  });

  final Widget entete;
  final Widget carte;
  final List<Widget> boutons;

  @override
  Widget build(BuildContext context) {
    final haut = hautEcran(context) - hautMaquette;
    final bas = MediaQuery.paddingOf(context).bottom;
    return Scaffold(
      body: Stack(
        children: [
          LayoutBuilder(
            builder: (context, contraintes) => SingleChildScrollView(
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: contraintes.maxHeight),
                child: IntrinsicHeight(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(16, haut + 130, 16, bas + 24),
                    child: Column(
                      children: [
                        Apparition(child: entete),
                        const SizedBox(height: 58),
                        Apparition.indexee(index: 1, child: carte),
                        const Spacer(),
                        const SizedBox(height: 24),
                        for (var i = 0; i < boutons.length; i++) ...[
                          if (i > 0) const SizedBox(height: 10),
                          boutons[i],
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Succes extends StatelessWidget {
  const _Succes({
    required this.reservationId,
    required this.reservation,
    required this.session,
  });

  final String reservationId;
  final ReservationClient? reservation;
  final SessionPaiement? session;

  @override
  Widget build(BuildContext context) {
    final r = reservation;
    final acompte = r?.paiement?.montantAcompte ?? session?.montantAcompte;
    // Mémoire, tableau 2.7 (étape 6) : la confirmation affiche le code de réservation généré au paiement.
    final code = r?.codeReservation;
    return _MiseEnPageResultat(
      entete: const _EnTeteResultat(
        fond: CouleursSopi.bleu,
        couleurIcone: CouleursSopi.blanc,
        icone: Icons.check_rounded,
        ligne2: 'réussi !',
        message:
            'Votre réservation est confirmée. Le prestataire a été prévenu.',
      ),
      carte: Container(
        padding: const EdgeInsets.all(18),
        decoration: _decorationCarte(),
        child: Column(
          children: [
            if (r != null) ...[
              _LigneRecap('Prestataire', r.prestataireNom),
              const SizedBox(height: 10),
              _LigneRecap('Service', r.prestationNom),
              const SizedBox(height: 10),
              _LigneRecap('Date', formaterDate(r.debut)),
              const SizedBox(height: 10),
            ],
            _LigneRecap(
              'Acompte payé',
              acompte == null ? '—' : formaterArgent(acompte),
              couleur: CouleursSopi.bleu,
            ),
            if (code != null) ...[
              const SizedBox(height: 10),
              _LigneRecap(
                'Code de réservation',
                code,
                couleur: CouleursSopi.bleu,
              ),
            ],
          ],
        ),
      ),
      boutons: [
        BoutonPrimaire(
          libelle: 'Voir ma réservation',
          onPressed: () {
            context.go(Chemins.reservations);
            context.push(Chemins.detailReservation(reservationId));
          },
        ),
        BoutonCompact(
          libelle: 'Retour à l\'accueil',
          onPressed: () => context.go(Chemins.accueil),
          ton: TonBoutonCompact.contour,
          hauteur: 54,
          taille: 15,
          pleineLargeur: true,
        ),
      ],
    );
  }
}

class _Echec extends StatelessWidget {
  const _Echec({required this.onReessayer});

  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) => _MiseEnPageResultat(
    entete: const _EnTeteResultat(
      fond: CouleursSopi.erreurPale,
      couleurIcone: CouleursSopi.erreurIcone,
      tailleIcone: 48,
      icone: Icons.close_rounded,
      ligne2: 'non abouti',
      message:
          'Aucun montant n\'a été débité. Vous pouvez réessayer ou choisir un autre moyen de paiement.',
    ),
    carte: Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
      decoration: BoxDecoration(
        color: CouleursSopi.bleuPastel,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Text(
        'Votre créneau est conservé pendant un délai limité. Passé ce délai sans paiement, la réservation est annulée automatiquement.',
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          fontSize: 13,
          height: 19 / 13,
          color: CouleursSopi.bleuProfond,
        ),
      ),
    ),
    boutons: [
      BoutonPrimaire(libelle: 'Réessayer le paiement', onPressed: onReessayer),
      BoutonCompact(
        libelle: 'Mes réservations',
        onPressed: () => context.go(Chemins.reservations),
        ton: TonBoutonCompact.contour,
        hauteur: 54,
        taille: 15,
        pleineLargeur: true,
      ),
    ],
  );
}
