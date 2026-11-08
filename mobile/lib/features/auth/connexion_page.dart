import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/effets.dart';
import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/compte_a_rebours.dart';
import '../../core/widgets/decompte.dart';
import '../inscription_prestataire/stockage_jeton_suivi.dart';
import 'session.dart';
import 'widgets/mise_en_page_acces.dart';

const messageIdentifiantsFaux = 'Identifiant ou mot de passe incorrect.';
const _blocageParDefaut = Duration(minutes: 15);

/// Accent orange et fond clair de la feuille, comme l'écran d'orientation.
const _orange = Color(0xFFF59E0B);
const _fondFeuille = Color(0xFFF4F6FA);

/// Écran de connexion (maquettes Connexion et ConnexionBloquee).
class ConnexionPage extends ConsumerStatefulWidget {
  const ConnexionPage({super.key});

  @override
  ConsumerState<ConnexionPage> createState() => _ConnexionPageState();
}

class _ConnexionPageState extends ConsumerState<ConnexionPage>
    with SingleTickerProviderStateMixin {
  final _identifiant = TextEditingController();
  final _motDePasse = TextEditingController();
  bool _envoi = false;

  /// Vrai un instant après une connexion acceptée : le bouton affiche « Bienvenue ! » puis reprend son aspect.
  bool _succes = false;
  late final AnimationController _delaiSucces =
      AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 1500),
      )..addStatusListener((etat) {
        if (etat == AnimationStatus.completed && mounted) {
          setState(() => _succes = false);
        }
      });
  String? _erreurFormulaire;
  String? _erreurIdentifiant;
  String? _erreurMotDePasse;

  /// Durée du blocage en cours (429) ; null hors blocage.
  Duration? _blocage;

  @override
  void initState() {
    super.initState();
    // Créé tout de suite : un contrôleur créé à la volée dans dispose() échoue.
    _delaiSucces;
  }

  @override
  void dispose() {
    _delaiSucces.dispose();
    _identifiant.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  bool _validerLocalement() {
    final id = _identifiant.text.trim().isEmpty
        ? 'Saisissez votre e-mail ou votre numéro de téléphone.'
        : null;
    final mdp = _motDePasse.text.isEmpty
        ? 'Saisissez votre mot de passe.'
        : null;
    setState(() {
      _erreurIdentifiant = id;
      _erreurMotDePasse = mdp;
      _erreurFormulaire = null;
    });
    return id == null && mdp == null;
  }

  Future<void> _envoyer() async {
    if (_envoi || _blocage != null || !_validerLocalement()) return;
    setState(() => _envoi = true);
    try {
      await ref
          .read(sessionProvider.notifier)
          .connecter(_identifiant.text.trim(), _motDePasse.text);
      if (mounted) {
        setState(() => _succes = true);
        _delaiSucces.forward(from: 0);
      }
    } on ErreurValidation catch (e) {
      if (!mounted) return;
      setState(() {
        _erreurIdentifiant = e.premierMessage('identifiant');
        _erreurMotDePasse = e.premierMessage('password');
        if (_erreurIdentifiant == null && _erreurMotDePasse == null) {
          _erreurFormulaire = e.message;
        }
      });
    } on ErreurTropDeRequetes catch (e) {
      if (mounted) setState(() => _blocage = e.delai ?? _blocageParDefaut);
    } on ErreurSession {
      if (mounted) setState(() => _erreurFormulaire = messageIdentifiantsFaux);
    } on ErreurApi catch (e) {
      if (mounted) setState(() => _erreurFormulaire = e.message);
    } finally {
      if (mounted) setState(() => _envoi = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bloque = _blocage != null;
    final bas = MediaQuery.paddingOf(context).bottom;
    return Scaffold(
      backgroundColor: _fondFeuille,
      body: CustomScrollView(
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _EnteteConnexion(
                  bloque: bloque,
                  bandeau: bloque
                      ? CompteARebours(
                          key: ValueKey(_blocage),
                          duree: _blocage!,
                          onFin: () => setState(() => _blocage = null),
                          builder: (_, restant) => BandeauErreur.alerte(
                            message:
                                'Trop de tentatives. Votre accès est temporairement bloqué. '
                                'Réessayez dans ${formaterDecompte(restant)}.',
                          ),
                        )
                      : null,
                ),
                Expanded(
                  // La feuille monte du bas, puis ses éléments arrivent l'un après l'autre.
                  child: Transform.translate(
                    offset: const Offset(0, -28),
                    child: Apparition(
                      delai: const Duration(milliseconds: 250),
                      duree: const Duration(milliseconds: 650),
                      decalage: const Offset(0, 160),
                      child: DecoratedBox(
                        decoration: const BoxDecoration(
                          color: _fondFeuille,
                          borderRadius: BorderRadius.vertical(
                            top: Radius.circular(32),
                          ),
                        ),
                        child: Padding(
                          padding: EdgeInsets.fromLTRB(20, 14, 20, 12 + bas),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _BarresProgression(
                                ecoute: Listenable.merge([
                                  _identifiant,
                                  _motDePasse,
                                ]),
                                rempli: () => [
                                  _identifiant.text.trim().isNotEmpty,
                                  _motDePasse.text.isNotEmpty,
                                ],
                              ),
                              const SizedBox(height: 18),
                              _ArriveeChamp(
                                rang: 0,
                                estompe: _envoi,
                                child: ChampFormulaire(
                                  libelle: 'E-mail ou téléphone',
                                  icone: Icons.person_outline_rounded,
                                  controleur: _identifiant,
                                  clavier: TextInputType.emailAddress,
                                  actionClavier: TextInputAction.next,
                                  autofill: const [AutofillHints.username],
                                  erreur: _erreurIdentifiant,
                                  hauteur: 58,
                                  suffixe: _CocheSaisie(
                                    controleur: _identifiant,
                                  ),
                                  onChanged: (_) {
                                    if (_erreurIdentifiant != null) {
                                      setState(() => _erreurIdentifiant = null);
                                    }
                                  },
                                ),
                              ),
                              const SizedBox(height: 12),
                              _ArriveeChamp(
                                rang: 1,
                                estompe: _envoi,
                                child: ChampFormulaire(
                                  libelle: 'Mot de passe',
                                  icone: Icons.lock_outline_rounded,
                                  controleur: _motDePasse,
                                  motDePasse: true,
                                  actionClavier: TextInputAction.done,
                                  autofill: const [AutofillHints.password],
                                  erreur: _erreurMotDePasse,
                                  erreurSansTexte: bloque,
                                  hauteur: 58,
                                  onChanged: (_) {
                                    if (_erreurMotDePasse != null) {
                                      setState(() => _erreurMotDePasse = null);
                                    }
                                  },
                                  onSubmitted: (_) => _envoyer(),
                                ),
                              ),
                              const SizedBox(height: 4),
                              _ArriveeChamp(
                                rang: 2,
                                estompe: _envoi,
                                child: Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    const SizedBox(width: 8),
                                    const Icon(
                                      Icons.verified_user_outlined,
                                      size: 18,
                                      color: CouleursSopi.succesTexte,
                                    ),
                                    const SizedBox(width: 6),
                                    Flexible(
                                      child: Text(
                                        'Connexion sécurisée',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: theme.textTheme.bodySmall
                                            ?.copyWith(
                                              fontSize: 13,
                                              color:
                                                  CouleursSopi.texteSecondaire,
                                            ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Flexible(
                                      child: LienTexte(
                                        texte: 'Mot de passe oublié ?',
                                        taille: 13,
                                        poids: FontWeight.w700,
                                        onTap: () => context.push(
                                          Chemins.motDePasseOublie,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                              _ArriveeChamp(
                                rang: 3,
                                child: _BoutonConnexion(
                                  envoi: _envoi,
                                  succes: _succes,
                                  onPressed: bloque ? null : _envoyer,
                                ),
                              ),
                              if (_erreurFormulaire != null) ...[
                                const SizedBox(height: 12),
                                Text(
                                  _erreurFormulaire!,
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: CouleursSopi.erreurTexte,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                              const SizedBox(height: 14),
                              ApparitionAcces(
                                rang: 1,
                                child: _LienInscription(
                                  suivi: ref.watch(jetonSuiviProvider) != null,
                                ),
                              ),
                              const Spacer(),
                              const SizedBox(height: 16),
                              ApparitionAcces(
                                rang: 2,
                                child: _CartePrestataire(
                                  onTap: () => context.push(
                                    Chemins.inscriptionPrestataire,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
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

/// Bouton « Se connecter » : au toucher il passe à « Connexion… » avec un cercle qui tourne ; une fois la
/// connexion acceptée il devient vert « Bienvenue ! » avec une coche, puis reprend son aspect normal.
class _BoutonConnexion extends StatelessWidget {
  const _BoutonConnexion({
    required this.envoi,
    required this.succes,
    required this.onPressed,
  });

  final bool envoi;
  final bool succes;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final actif = onPressed != null && !envoi && !succes;
    final fond = succes ? CouleursSopi.succesTexte : CouleursSopi.bleu;
    final animer = animationsActives(context);
    final Widget contenu;
    if (succes) {
      contenu = const Row(
        key: ValueKey('succes'),
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.check_rounded, size: 22),
          SizedBox(width: 8),
          Flexible(child: Text('Bienvenue !', textAlign: TextAlign.center)),
        ],
      );
    } else if (envoi) {
      contenu = const Row(
        key: ValueKey('envoi'),
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              color: CouleursSopi.blanc,
              semanticsLabel: 'Chargement',
            ),
          ),
          SizedBox(width: 10),
          Flexible(child: Text('Connexion…', textAlign: TextAlign.center)),
        ],
      );
    } else {
      contenu = const Row(
        key: ValueKey('repos'),
        mainAxisSize: MainAxisSize.min,
        children: [
          Flexible(child: Text('Se connecter', textAlign: TextAlign.center)),
          SizedBox(width: 10),
          Icon(Icons.arrow_forward_rounded, size: 20),
        ],
      );
    }
    return Appui(
      actif: actif,
      child: AnimatedContainer(
        duration: animer ? const Duration(milliseconds: 300) : Duration.zero,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(30),
          boxShadow: [
            BoxShadow(
              color: fond.withValues(alpha: 0.28),
              blurRadius: 22,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: FilledButton(
          onPressed: actif ? onPressed : null,
          style: ButtonStyle(
            minimumSize: const WidgetStatePropertyAll(Size.fromHeight(60)),
            elevation: const WidgetStatePropertyAll(0),
            backgroundColor: WidgetStateProperty.resolveWith(
              (etats) =>
                  etats.contains(WidgetState.disabled) && !envoi && !succes
                  ? CouleursSopi.fondSecondaire
                  : fond,
            ),
            foregroundColor: WidgetStateProperty.resolveWith(
              (etats) =>
                  etats.contains(WidgetState.disabled) && !envoi && !succes
                  ? CouleursSopi.texteSecondaire
                  : CouleursSopi.blanc,
            ),
            shape: WidgetStatePropertyAll(
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
            ),
            textStyle: WidgetStatePropertyAll(
              Theme.of(context).textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w800,
                fontSize: 17,
              ),
            ),
          ),
          child: AnimatedSwitcher(
            duration: animer
                ? const Duration(milliseconds: 250)
                : Duration.zero,
            transitionBuilder: (enfant, anim) => FadeTransition(
              opacity: anim,
              child: ScaleTransition(scale: anim, child: enfant),
            ),
            child: contenu,
          ),
        ),
      ),
    );
  }
}

/// Coche verte qui apparaît (avec un petit rebond) dès qu'on a écrit quelque chose dans le champ.
class _CocheSaisie extends StatelessWidget {
  const _CocheSaisie({required this.controleur});

  final TextEditingController controleur;

  @override
  Widget build(BuildContext context) {
    final animer = animationsActives(context);
    return ValueListenableBuilder<TextEditingValue>(
      valueListenable: controleur,
      builder: (context, valeur, _) => AnimatedScale(
        scale: valeur.text.trim().isEmpty ? 0 : 1,
        duration: animer ? const Duration(milliseconds: 380) : Duration.zero,
        curve: Curves.elasticOut,
        child: const Padding(
          padding: EdgeInsets.only(right: 6),
          child: Icon(
            Icons.check_circle_rounded,
            size: 24,
            color: CouleursSopi.succesTexte,
          ),
        ),
      ),
    );
  }
}

/// Deux petites barres au sommet de la feuille : chacune se colore en bleu quand son champ est rempli.
class _BarresProgression extends StatelessWidget {
  const _BarresProgression({required this.ecoute, required this.rempli});

  final Listenable ecoute;
  final List<bool> Function() rempli;

  @override
  Widget build(BuildContext context) {
    final animer = animationsActives(context);
    return ExcludeSemantics(
      child: ListenableBuilder(
        listenable: ecoute,
        builder: (context, _) {
          final etats = rempli();
          return Row(
            children: [
              for (var i = 0; i < etats.length; i++) ...[
                if (i > 0) const SizedBox(width: 12),
                Expanded(
                  child: AnimatedContainer(
                    duration: animer
                        ? const Duration(milliseconds: 350)
                        : Duration.zero,
                    height: 4,
                    decoration: BoxDecoration(
                      color: etats[i]
                          ? CouleursSopi.bleu
                          : const Color(0xFFDDE2EC),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// « Nouveau sur Sopi ? Créer un compte » dans un cadre arrondi, et « Suivre mon dossier » s'il y a un dossier.
class _LienInscription extends StatelessWidget {
  const _LienInscription({required this.suivi});

  final bool suivi;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: const Color(0xFFDDE2EC), width: 1.5),
          ),
          child: Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 6,
            children: [
              Text(
                'Nouveau sur Sopi ?',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 15,
                  fontWeight: FontWeight.w400,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
              LienTexte(
                texte: 'Créer un compte',
                taille: 15,
                onTap: () => context.push(Chemins.inscription),
              ),
              if (suivi)
                LienTexte(
                  texte: 'Suivre mon dossier',
                  onTap: () => context.push(Chemins.suiviDossier),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

/// En-tête bleu : bouton retour et pastille du quartier, deux cartes de réservation qui glissent puis flottent,
/// titre « Bon retour. » et phrase d'accroche (ou bandeau de blocage).
class _EnteteConnexion extends StatefulWidget {
  const _EnteteConnexion({required this.bloque, this.bandeau});

  final bool bloque;
  final Widget? bandeau;

  @override
  State<_EnteteConnexion> createState() => _EnteteConnexionState();
}

class _EnteteConnexionState extends State<_EnteteConnexion>
    with SingleTickerProviderStateMixin {
  /// Flottement des cartes : trois tours de 6 s, puis elles se figent.
  late final AnimationController _boucle = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 6),
  );
  bool _initialise = false;
  bool _anime = false;

  @override
  void initState() {
    super.initState();
    _boucle;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialise) return;
    _initialise = true;
    _anime = animationsActives(context);
    if (_anime) _boucle.repeat(count: 3);
  }

  @override
  void dispose() {
    _boucle.dispose();
    super.dispose();
  }

  void _retour() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go(Chemins.orientation);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    final styleTitre = theme.textTheme.headlineMedium!.copyWith(
      fontSize: 44,
      fontWeight: FontWeight.w400,
      letterSpacing: -1.2,
      height: 50 / 44,
      color: CouleursSopi.blanc,
    );
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [CouleursSopi.bleu, Color(0xFF1D4ED8)],
        ),
      ),
      child: Padding(
        padding: EdgeInsets.fromLTRB(24, haut + 16, 24, 48),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Apparition(
              duree: const Duration(milliseconds: 450),
              decalage: const Offset(0, -16),
              child: Row(
                children: [
                  Semantics(
                    button: true,
                    label: 'Retour',
                    excludeSemantics: true,
                    onTap: _retour,
                    child: Material(
                      color: CouleursSopi.blanc.withValues(alpha: 0.16),
                      shape: const CircleBorder(),
                      child: InkWell(
                        customBorder: const CircleBorder(),
                        onTap: _retour,
                        child: const SizedBox(
                          width: 48,
                          height: 48,
                          child: Icon(
                            Icons.arrow_back_rounded,
                            size: 24,
                            color: CouleursSopi.blanc,
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(child: _PastilleQuartier()),
                ],
              ),
            ),
            const SizedBox(height: 18),
            ExcludeSemantics(
              child: _CartesReservation(boucle: _anime ? _boucle : null),
            ),
            const SizedBox(height: 14),
            Apparition(
              delai: const Duration(milliseconds: 200),
              child: Text.rich(
                TextSpan(
                  children: [
                    const TextSpan(text: 'Bon '),
                    const TextSpan(
                      text: 'retour',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                    TextSpan(
                      text: '.',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        color: _orange,
                      ),
                    ),
                  ],
                ),
                style: styleTitre,
              ),
            ),
            const SizedBox(height: 8),
            _TraitAccent(animer: _anime),
            const SizedBox(height: 14),
            if (widget.bandeau != null)
              Apparition(
                delai: const Duration(milliseconds: 300),
                child: widget.bandeau!,
              )
            else
              Apparition(
                delai: const Duration(milliseconds: 300),
                child: Text(
                  'Retrouvez vos réservations et vos artisans de Keur Massar.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 16,
                    height: 22 / 16,
                    color: const Color(0xFFDBEAFE),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Pastille « Keur Massar » : fait pendant au bouton retour.
class _PastilleQuartier extends StatelessWidget {
  const _PastilleQuartier();

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerRight,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: CouleursSopi.blanc.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(10, 8, 14, 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.location_on_outlined,
                size: 16,
                color: CouleursSopi.blanc,
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  'Keur Massar',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: CouleursSopi.blanc,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Petit trait orange qui se trace sous le titre.
class _TraitAccent extends StatelessWidget {
  const _TraitAccent({required this.animer});

  final bool animer;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: animer ? 0 : 1, end: 1),
      duration: animer ? const Duration(milliseconds: 700) : Duration.zero,
      curve: Curves.easeOutCubic,
      builder: (context, t, _) => Container(
        width: 56 * t,
        height: 4,
        decoration: BoxDecoration(
          color: _orange,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
    );
  }
}

/// Deux cartes de réservation (Vidange, Coiffure) qui glissent depuis la droite l'une après l'autre puis flottent,
/// et la pastille « Acompte 30 % » qui apparaît avec un petit rebond.
class _CartesReservation extends StatelessWidget {
  const _CartesReservation({this.boucle});

  final Animation<double>? boucle;

  Widget _flotte(double phase, Widget enfant) {
    final b = boucle;
    if (b == null) return enfant;
    return AnimatedBuilder(
      animation: b,
      builder: (context, c) => Transform.translate(
        offset: Offset(0, math.sin((b.value * 3 + phase) * 2 * math.pi) * 5),
        child: c,
      ),
      child: enfant,
    );
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 128,
      child: Stack(
        children: [
          Positioned(
            top: 0,
            left: 36,
            right: 0,
            child: Apparition(
              delai: const Duration(milliseconds: 350),
              duree: const Duration(milliseconds: 600),
              decalage: const Offset(160, 0),
              child: _flotte(
                0,
                const _CarteRdv(
                  titre: 'Vidange',
                  detail: 'Demain · 10:30',
                  icone: Icons.build_outlined,
                  claire: true,
                ),
              ),
            ),
          ),
          Positioned(
            top: 60,
            left: 90,
            right: 0,
            child: Apparition(
              delai: const Duration(milliseconds: 550),
              duree: const Duration(milliseconds: 600),
              decalage: const Offset(160, 0),
              child: _flotte(
                0.5,
                const _CarteRdv(
                  titre: 'Coiffure',
                  detail: "Aujourd'hui · 14:00",
                  icone: Icons.content_cut_rounded,
                  claire: false,
                ),
              ),
            ),
          ),
          Positioned(
            top: 98,
            left: 0,
            child: Pop(
              delai: const Duration(milliseconds: 950),
              duree: const Duration(milliseconds: 550),
              echelleDepart: 0.3,
              alignement: Alignment.centerLeft,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: _orange,
                  borderRadius: BorderRadius.circular(999),
                  boxShadow: [
                    BoxShadow(
                      color: _orange.withValues(alpha: 0.4),
                      blurRadius: 14,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                  child: Text(
                    'Acompte 30 %',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF111628),
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

class _CarteRdv extends StatelessWidget {
  const _CarteRdv({
    required this.titre,
    required this.detail,
    required this.icone,
    required this.claire,
  });

  final String titre;
  final String detail;
  final IconData icone;
  final bool claire;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: claire
            ? CouleursSopi.blanc
            : CouleursSopi.blanc.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(22),
        border: claire
            ? null
            : Border.all(color: CouleursSopi.blanc.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: claire ? CouleursSopi.bleu : CouleursSopi.blanc,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              icone,
              size: 22,
              color: claire ? CouleursSopi.blanc : const Color(0xFF0F766E),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  titre,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: claire ? CouleursSopi.texte : CouleursSopi.blanc,
                  ),
                ),
                Text(
                  detail,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13,
                    color: claire
                        ? CouleursSopi.texteSecondaire
                        : CouleursSopi.blanc.withValues(alpha: 0.85),
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

/// Un élément de la feuille de connexion : il arrive après la montée de la feuille, au rang [rang] ; pendant
/// l'envoi, les champs (`estompe`) s'estompent et ne répondent plus.
class _ArriveeChamp extends StatelessWidget {
  const _ArriveeChamp({
    required this.rang,
    required this.child,
    this.estompe = false,
  });

  final int rang;
  final Widget child;
  final bool estompe;

  @override
  Widget build(BuildContext context) {
    return Apparition(
      delai: Duration(milliseconds: 600 + 110 * rang),
      duree: const Duration(milliseconds: 380),
      decalage: const Offset(0, 14),
      child: IgnorePointer(
        ignoring: estompe,
        child: AnimatedOpacity(
          duration: animationsActives(context)
              ? const Duration(milliseconds: 300)
              : Duration.zero,
          opacity: estompe ? 0.35 : 1,
          child: child,
        ),
      ),
    );
  }
}

/// Carte blanche « Vous êtes professionnel ? » avec son bouton rond bleu.
class _CartePrestataire extends StatelessWidget {
  const _CartePrestataire({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(24),
        boxShadow: OmbresSopi.carte,
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Vous êtes professionnel ?',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12,
                    fontWeight: FontWeight.w400,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
                Text(
                  'Devenir prestataire Sopi',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: CouleursSopi.texte,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Semantics(
            button: true,
            label: 'Devenir prestataire',
            child: Material(
              color: CouleursSopi.bleu,
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: onTap,
                child: const SizedBox(
                  width: 44,
                  height: 44,
                  child: Icon(
                    Icons.north_east_rounded,
                    size: 20,
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
}
