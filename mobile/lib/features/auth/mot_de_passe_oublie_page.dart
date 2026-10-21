import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/client_api.dart';
import '../../core/api/erreurs.dart';
import '../../core/api_client.dart';
import '../../core/config/support.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/compte_a_rebours.dart';
import '../../core/widgets/decompte.dart';
import 'validateurs.dart';
import 'widgets/mise_en_page_acces.dart';

/// Délai avant de pouvoir demander un nouveau lien.
const delaiRenvoi = Duration(seconds: 30);

const _blocageParDefaut = Duration(minutes: 1);

const messageLienOuvreNavigateur =
    "Le lien s'ouvre dans votre navigateur : vous y choisirez votre nouveau mot de passe, "
    'puis vous reviendrez vous connecter ici.';

const messageSupportSansEmail =
    "Pas d'adresse e-mail sur votre compte ? Contactez le support Sopi.";

const _messageParDefaut =
    "Si un compte correspond à cette adresse, un lien de réinitialisation vient d'être envoyé. "
    'Pensez à vérifier vos spams.';

/// Demande de réinitialisation du mot de passe (maquette MotDePasse). La réponse du serveur est affichée telle
/// quelle : elle ne révèle jamais si un compte existe.
class MotDePasseOubliePage extends ConsumerStatefulWidget {
  const MotDePasseOubliePage({super.key});

  @override
  ConsumerState<MotDePasseOubliePage> createState() =>
      _MotDePasseOubliePageState();
}

class _MotDePasseOubliePageState extends ConsumerState<MotDePasseOubliePage> {
  final _email = TextEditingController();
  bool _envoi = false;
  String? _erreurEmail;
  String? _erreurFormulaire;

  /// Message du serveur après un envoi réussi ; null tant qu'aucun lien n'a été demandé.
  String? _confirmation;

  /// Attente avant un nouvel envoi (30 s après un succès, durée du serveur après un 429).
  Duration? _attente;
  bool _attenteServeur = false;

  /// Change à chaque nouvelle attente pour relancer le décompte.
  int _numeroAttente = 0;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  void _demarrerAttente(Duration duree, {required bool serveur}) {
    _attente = duree;
    _attenteServeur = serveur;
    _numeroAttente++;
  }

  Future<void> _envoyer() async {
    if (_envoi || _attente != null) return;
    final erreur = ValidateursAcces.emailObligatoire(_email.text);
    setState(() {
      _erreurEmail = erreur;
      _erreurFormulaire = null;
    });
    if (erreur != null) return;

    setState(() => _envoi = true);
    try {
      final reponse = await ref
          .read(dioProvider)
          .post<Object?>(
            cheminMotDePasseOublie,
            data: {'email': _email.text.trim()},
          );
      if (!mounted) return;
      final corps = reponse.data;
      final message = corps is Map && corps['message'] is String
          ? corps['message'] as String
          : _messageParDefaut;
      setState(() {
        _confirmation = message;
        _demarrerAttente(delaiRenvoi, serveur: false);
      });
    } catch (e) {
      if (!mounted) return;
      final erreur = ErreurApi.depuis(e);
      setState(() {
        switch (erreur) {
          case ErreurValidation():
            _erreurEmail = erreur.premierMessage('email');
            if (_erreurEmail == null) _erreurFormulaire = erreur.message;
          case ErreurTropDeRequetes():
            _demarrerAttente(erreur.delai ?? _blocageParDefaut, serveur: true);
          default:
            _erreurFormulaire = erreur.message;
        }
      });
    } finally {
      if (mounted) setState(() => _envoi = false);
    }
  }

  Future<void> _contacterSupport() async {
    final ouvert = await contacterSupport(ref);
    if (!ouvert && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Impossible d'ouvrir votre messagerie.")),
      );
    }
  }

  Widget _bouton() {
    final envoye = _confirmation != null;
    if (_attente == null) {
      return BoutonPrimaire(
        libelle: envoye ? 'Renvoyer le lien' : 'Envoyer le lien',
        chargement: _envoi,
        onPressed: _envoyer,
      );
    }
    return CompteARebours(
      key: ValueKey('bouton$_numeroAttente'),
      duree: _attente!,
      onFin: () => setState(() => _attente = null),
      builder: (_, restant) => BoutonPrimaire(
        libelle: _attenteServeur
            ? 'Envoyer le lien'
            : 'Renvoyer dans ${restant.inSeconds} s',
        onPressed: null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final aSupport = ref.watch(supportEmailProvider).isNotEmpty;
    final styleSupport = theme.textTheme.bodySmall?.copyWith(
      fontSize: 13,
      fontWeight: aSupport ? FontWeight.w600 : FontWeight.w400,
      color: aSupport ? CouleursSopi.bleu : CouleursSopi.texteSecondaire,
    );
    return Scaffold(
      body: EcranAcces(
        chevauchement: 40,
        margePied: 24,
        entete: const EnteteFormulaire(
          titre1: 'Mot de passe',
          titre2: 'oublié ?',
          sousTitre:
              "Saisissez l'adresse e-mail de votre compte. Nous vous envoyons un lien valable 60 minutes.",
          basEntete: 50,
        ),
        corps: [
          ApparitionAcces(
            rang: 0,
            child: CarteFormulaire(
              children: [
                ChampFormulaire(
                  libelle: 'E-mail',
                  controleur: _email,
                  clavier: TextInputType.emailAddress,
                  actionClavier: TextInputAction.done,
                  autofill: const [AutofillHints.email],
                  erreur: _erreurEmail,
                  onChanged: (_) {
                    if (_erreurEmail != null) {
                      setState(() => _erreurEmail = null);
                    }
                  },
                  onSubmitted: (_) => _envoyer(),
                ),
                if (_attente != null && _attenteServeur)
                  CompteARebours(
                    key: ValueKey('bandeau$_numeroAttente'),
                    duree: _attente!,
                    onFin: () => setState(() => _attente = null),
                    builder: (_, restant) => BandeauErreur.alerte(
                      message:
                          'Trop de tentatives. Réessayez dans ${formaterDecompte(restant)}.',
                    ),
                  ),
                _bouton(),
                if (_erreurFormulaire != null)
                  Text(
                    _erreurFormulaire!,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: CouleursSopi.erreurTexte,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
              ],
            ),
          ),
          if (_confirmation != null)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  BandeauInfo.confirmation(message: _confirmation!),
                  const SizedBox(height: 14),
                  Text(
                    messageLienOuvreNavigateur,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 13,
                      height: 18 / 13,
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                ],
              ),
            ),
        ],
        pied: ApparitionAcces(
          rang: 1,
          child: aSupport
              ? InkWell(
                  onTap: _contacterSupport,
                  borderRadius: BorderRadius.circular(8),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Text(
                      messageSupportSansEmail,
                      textAlign: TextAlign.center,
                      style: styleSupport,
                    ),
                  ),
                )
              : Text(
                  messageSupportSansEmail,
                  textAlign: TextAlign.center,
                  style: styleSupport,
                ),
        ),
      ),
    );
  }
}
