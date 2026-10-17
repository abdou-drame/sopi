import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../animation/apparition.dart';
import '../../api/erreurs.dart';
import '../../theme/couleurs.dart';
import 'trace_coche.dart';

/// Alphabet du code de démarrage (sans 0, O, 1, I, L), identique à celui du serveur.
const alphabetCode = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const longueurCode = 6;

/// Met en majuscules, retire les espaces et tout caractère hors alphabet, tronque à 6 caractères.
/// S'applique aussi à un code collé.
class FormateurCode extends TextInputFormatter {
  const FormateurCode();

  static String nettoyer(String brut) {
    final propre = brut
        .toUpperCase()
        .split('')
        .where(alphabetCode.contains)
        .join();
    return propre.length > longueurCode
        ? propre.substring(0, longueurCode)
        : propre;
  }

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue ancien,
    TextEditingValue nouveau,
  ) {
    final texte = nettoyer(nouveau.text);
    return TextEditingValue(
      text: texte,
      selection: TextSelection.collapsed(offset: texte.length),
    );
  }
}

enum _Phase { saisie, verification, succes, bloque }

/// Saisie du code de démarrage par le prestataire : 6 cases, puis animation (éventail, fusion en un carré
/// bleu pendant la vérification, coche verte ou secousse rouge).
///
/// [onSubmit] est appelé avec le code complet. Il renvoie normalement si le code est bon et lève une
/// [ErreurApi] sinon : une [ErreurValidation] affiche son message sous le champ, une [ErreurTropDeRequetes]
/// bloque le champ pendant `Retry-After`. [onSucces] est appelé une fois l'animation de succès terminée.
class CodeSaisie extends StatefulWidget {
  const CodeSaisie({super.key, required this.onSubmit, this.onSucces});

  final Future<void> Function(String code) onSubmit;
  final VoidCallback? onSucces;

  @override
  State<CodeSaisie> createState() => CodeSaisieState();
}

class CodeSaisieState extends State<CodeSaisie> with TickerProviderStateMixin {
  static const _hauteurCase = 46.0;
  static const _ecart = 8.0;
  static const _tailleCarre = 96.0;

  final _texte = TextEditingController();
  final _focus = FocusNode();

  late final AnimationController _eventail; // 0 = cases alignées, 1 = éventail
  late final AnimationController _fusion; // 0 = cases, 1 = carré unique
  late final AnimationController _coche; // dessin de la coche + rebond
  late final AnimationController _secousse;
  late final AnimationController _curseur;

  _Phase _phase = _Phase.saisie;
  String? _erreur;
  bool _erreurAffichee = false;
  Timer? _minuteurBlocage;
  Duration _blocageRestant = Duration.zero;
  bool _animer = true;
  bool _initialise = false;

  @override
  void initState() {
    super.initState();
    _eventail = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 350),
    );
    _fusion = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
    );
    _coche = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 650),
    );
    _secousse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 420),
    );
    _curseur = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 530),
    );
    _texte.addListener(_surChangement);
    _focus.addListener(() => setState(() {}));
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _animer = animationsActives(context);
    if (!_initialise) {
      _initialise = true;
      if (_animer) _curseur.repeat(reverse: true);
    } else if (!_animer) {
      _curseur.stop();
    } else if (!_curseur.isAnimating) {
      _curseur.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _minuteurBlocage?.cancel();
    _texte.dispose();
    _focus.dispose();
    _eventail.dispose();
    _fusion.dispose();
    _coche.dispose();
    _secousse.dispose();
    _curseur.dispose();
    super.dispose();
  }

  String get _code => _texte.text;

  bool get _saisieActive => _phase == _Phase.saisie;

  void _surChangement() {
    if (!mounted) return;
    if (_erreurAffichee && _phase == _Phase.saisie) {
      // Toute modification efface l'erreur précédente.
      _erreur = null;
      _erreurAffichee = false;
    }
    setState(() {});
    if (_phase == _Phase.saisie && _code.length == longueurCode) {
      unawaited(_soumettre(_code));
    }
  }

  /// Envoie le code saisi (bouton « Démarrer la prestation ») s'il est complet et qu'aucun envoi n'est en cours.
  void soumettreSiComplet() {
    if (_saisieActive && _code.length == longueurCode) {
      unawaited(_soumettre(_code));
    }
  }

  /// Vrai quand les 6 caractères sont saisis et qu'un envoi est possible.
  bool get peutSoumettre => _saisieActive && _code.length == longueurCode;

  Future<void> _soumettre(String code) async {
    setState(() {
      _phase = _Phase.verification;
      _erreur = null;
    });
    _focus.unfocus();

    // L'appel réseau part tout de suite ; l'animation tourne pendant ce temps.
    final appel = widget
        .onSubmit(code)
        .then<Object?>((_) => null, onError: (Object e) => e);

    if (_animer) {
      await _eventail.forward(from: 0);
      if (!mounted) return;
      await _fusion.forward(from: 0);
    } else {
      _eventail.value = 1;
      _fusion.value = 1;
    }
    if (!mounted) return;

    final erreur = await appel;
    if (!mounted) return;

    if (erreur == null) {
      await _succes();
    } else {
      await _echec(ErreurApi.depuis(erreur));
    }
  }

  Future<void> _succes() async {
    setState(() => _phase = _Phase.succes);
    if (_animer) {
      await _coche.forward(from: 0);
    } else {
      _coche.value = 1;
    }
    if (mounted) widget.onSucces?.call();
  }

  Future<void> _echec(ErreurApi erreur) async {
    if (_animer) {
      // Le carré se rouvre en cases, puis les cases reviennent en ligne.
      await _fusion.reverse();
      if (!mounted) return;
      await _eventail.reverse();
      if (!mounted) return;
    } else {
      _fusion.value = 0;
      _eventail.value = 0;
    }

    final message = switch (erreur) {
      ErreurValidation e => e.premierMessage('code') ?? e.message,
      _ => erreur.message,
    };

    // Sans Retry-After, aucune durée n'est inventée : le message du serveur est affiché tel quel.
    if (erreur is ErreurTropDeRequetes && erreur.delai != null) {
      _bloquer(erreur);
      return;
    }

    setState(() {
      _phase = _Phase.saisie;
      _erreur = message;
      _erreurAffichee = true;
    });
    if (_animer) unawaited(_secousse.forward(from: 0));
  }

  void _bloquer(ErreurTropDeRequetes erreur) {
    _minuteurBlocage?.cancel();
    _blocageRestant = erreur.delai!;
    setState(() {
      _phase = _Phase.bloque;
      _erreur = null;
      _erreurAffichee = false;
    });
    _minuteurBlocage = Timer.periodic(const Duration(seconds: 1), (minuteur) {
      if (!mounted) return;
      final reste = _blocageRestant - const Duration(seconds: 1);
      if (reste <= Duration.zero) {
        minuteur.cancel();
        _texte.removeListener(_surChangement);
        _texte.clear();
        _texte.addListener(_surChangement);
        setState(() {
          _phase = _Phase.saisie;
          _blocageRestant = Duration.zero;
        });
      } else {
        setState(() => _blocageRestant = reste);
      }
    });
  }

  String _delaiLisible(Duration d) {
    final s = d.inSeconds;
    if (s < 60) return '$s s';
    return '${s ~/ 60} min ${(s % 60).toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final aErreur = _erreur != null;
    final bloque = _phase == _Phase.bloque;

    return Semantics(
      label: 'Code de démarrage, $longueurCode caractères',
      textField: true,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Champ réel : invisible, reçoit le clavier et le collage.
          SizedBox(
            width: 1,
            height: 1,
            child: TextField(
              controller: _texte,
              focusNode: _focus,
              enabled: _saisieActive,
              inputFormatters: const [FormateurCode()],
              textCapitalization: TextCapitalization.characters,
              autocorrect: false,
              enableSuggestions: false,
              showCursor: false,
              enableInteractiveSelection: false,
              style: const TextStyle(color: Colors.transparent, fontSize: 1),
              decoration: const InputDecoration(
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                disabledBorder: InputBorder.none,
                filled: false,
                contentPadding: EdgeInsets.zero,
                isDense: true,
              ),
            ),
          ),
          GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: _saisieActive ? _focus.requestFocus : null,
            child: SizedBox(
              height: _tailleCarre,
              child: AnimatedBuilder(
                animation: Listenable.merge([
                  _eventail,
                  _fusion,
                  _coche,
                  _secousse,
                  _curseur,
                ]),
                builder: (context, _) => LayoutBuilder(
                  builder: (context, contraintes) =>
                      _zoneCases(contraintes.maxWidth, aErreur, bloque),
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(height: 22, child: Center(child: _message(theme, aErreur))),
        ],
      ),
    );
  }

  Widget _message(ThemeData theme, bool aErreur) {
    if (_phase == _Phase.bloque) {
      return Text(
        'Trop de tentatives. Réessayez dans ${_delaiLisible(_blocageRestant)}.',
        textAlign: TextAlign.center,
        style: theme.textTheme.bodyMedium?.copyWith(
          color: CouleursSopi.erreurTexte,
          fontWeight: FontWeight.w700,
        ),
      );
    }
    if (_phase == _Phase.verification) {
      return Text(
        'Un instant, nous confirmons le code du client.',
        style: theme.textTheme.bodyMedium?.copyWith(
          fontWeight: FontWeight.w700,
        ),
      );
    }
    if (_phase == _Phase.succes) {
      return Text(
        'Le code est bon. Vous pouvez démarrer la prestation.',
        style: theme.textTheme.bodyMedium?.copyWith(
          color: CouleursSopi.succesTexte,
          fontWeight: FontWeight.w700,
        ),
      );
    }
    if (aErreur) {
      return Text(
        _erreur!,
        textAlign: TextAlign.center,
        style: theme.textTheme.bodyMedium?.copyWith(
          color: CouleursSopi.erreurTexte,
          fontWeight: FontWeight.w700,
        ),
      );
    }
    return const SizedBox.shrink();
  }

  Widget _zoneCases(double largeurDispo, bool aErreur, bool bloque) {
    final largeurCase = math.min(
      52.0,
      (largeurDispo - _ecart * (longueurCode - 1)) / longueurCode,
    );
    final pas = largeurCase + _ecart;
    final f = _fusion.value;
    final e = _eventail.value;
    final decalage = _secousse.isAnimating
        ? math.sin(_secousse.value * math.pi * 6) * 10 * (1 - _secousse.value)
        : 0.0;

    final cases = <Widget>[
      for (var i = 0; i < longueurCode; i++)
        _caseEnEventail(i, largeurCase, pas, e, f, aErreur, bloque),
    ];

    final enFusion =
        f > 0 || _phase == _Phase.verification || _phase == _Phase.succes;

    return Transform.translate(
      offset: Offset(decalage, 0),
      child: Stack(
        alignment: Alignment.center,
        clipBehavior: Clip.none,
        children: [...cases, if (enFusion) _carre(f)],
      ),
    );
  }

  Widget _caseEnEventail(
    int i,
    double largeurCase,
    double pas,
    double e,
    double f,
    bool aErreur,
    bool bloque,
  ) {
    final d = i - (longueurCode - 1) / 2; // -2.5 .. 2.5
    final x = d * pas * (1 - 0.45 * e) * (1 - f);
    final angle = d / 2.5 * 12 * math.pi / 180 * e;
    final dy = d.abs() * d.abs() * 1.4 * e;
    final opacite = (1 - f).clamp(0.0, 1.0);
    final echelle = 1 - 0.35 * f;

    return Transform.translate(
      offset: Offset(x, dy),
      child: Transform.rotate(
        angle: angle,
        alignment: Alignment.bottomCenter,
        child: Transform.scale(
          scale: echelle,
          child: Opacity(
            opacity: opacite,
            child: _CaseCode(
              largeur: largeurCase,
              hauteur: _hauteurCase,
              caractere: i < _code.length ? _code[i] : null,
              active:
                  _saisieActive &&
                  _focus.hasFocus &&
                  i == math.min(_code.length, longueurCode - 1),
              curseurVisible:
                  !_animer || _curseur.value > 0.5 || !_curseur.isAnimating,
              erreur: aErreur,
              grisee: bloque,
            ),
          ),
        ),
      ),
    );
  }

  Widget _carre(double f) {
    final succes = _phase == _Phase.succes;
    final rebond = succes && _animer
        ? Curves.elasticOut.transform(_coche.value.clamp(0.0, 1.0))
        : 1.0;
    final echelle = (0.6 + 0.4 * f) * (succes ? (0.85 + 0.15 * rebond) : 1);
    return Opacity(
      opacity: f.clamp(0.0, 1.0),
      child: Transform.scale(
        scale: echelle,
        child: Container(
          width: _tailleCarre,
          height: _tailleCarre,
          decoration: BoxDecoration(
            color: succes ? CouleursSopi.succesFond : CouleursSopi.bleu,
            borderRadius: BorderRadius.circular(28),
            border: succes
                ? Border.all(color: CouleursSopi.succesTexte, width: 3)
                : null,
          ),
          child: succes
              ? CustomPaint(
                  painter: TraceCoche(
                    progression: _animer
                        ? Curves.easeOut.transform(
                            (_coche.value * 1.4).clamp(0.0, 1.0),
                          )
                        : 1,
                    couleur: CouleursSopi.succesTexte,
                  ),
                )
              : const Center(
                  child: SizedBox(
                    width: 38,
                    height: 38,
                    child: CircularProgressIndicator(
                      strokeWidth: 4,
                      color: CouleursSopi.blanc,
                      semanticsLabel:
                          'Un instant, nous confirmons le code du client.',
                    ),
                  ),
                ),
        ),
      ),
    );
  }
}

class _CaseCode extends StatelessWidget {
  const _CaseCode({
    required this.largeur,
    required this.hauteur,
    required this.caractere,
    required this.active,
    required this.curseurVisible,
    required this.erreur,
    required this.grisee,
  });

  final double largeur;
  final double hauteur;
  final String? caractere;
  final bool active;
  final bool curseurVisible;
  final bool erreur;
  final bool grisee;

  @override
  Widget build(BuildContext context) {
    final couleurBord = erreur
        ? CouleursSopi.erreurTexte
        : active
        ? CouleursSopi.bleu
        : CouleursSopi.bordureForte;
    return Container(
      width: largeur,
      height: hauteur,
      margin: const EdgeInsets.symmetric(horizontal: 0),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: grisee ? CouleursSopi.bordure : CouleursSopi.champ,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: couleurBord,
          width: active || erreur ? 2 : 1.2,
        ),
      ),
      child: caractere != null
          ? Text(
              caractere!,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: erreur ? CouleursSopi.erreurTexte : CouleursSopi.marine,
              ),
            )
          : (active && curseurVisible
                ? Container(width: 2, height: 22, color: CouleursSopi.bleu)
                : null),
    );
  }
}
