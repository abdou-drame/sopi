import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/compte_a_rebours.dart';
import '../../core/widgets/decompte.dart';
import 'session.dart';
import 'validateurs.dart';
import 'widgets/mise_en_page_acces.dart';

const _blocageParDefaut = Duration(minutes: 1);

/// Inscription d'un client (maquette Inscription). Le mot de passe est confirmé dans l'application seulement :
/// l'API n'attend pas de confirmation, elle n'est donc pas envoyée.
class InscriptionPage extends ConsumerStatefulWidget {
  const InscriptionPage({super.key});

  @override
  ConsumerState<InscriptionPage> createState() => _InscriptionPageState();
}

class _InscriptionPageState extends ConsumerState<InscriptionPage> {
  final _prenom = TextEditingController();
  final _nom = TextEditingController();
  final _telephone = TextEditingController();
  final _email = TextEditingController();
  final _motDePasse = TextEditingController();
  final _confirmation = TextEditingController();

  bool _envoi = false;
  String? _erreurFormulaire;
  final Map<String, String?> _erreurs = {};

  /// Durée d'attente imposée par le serveur (429) ; null hors blocage.
  Duration? _blocage;

  @override
  void dispose() {
    for (final c in [
      _prenom,
      _nom,
      _telephone,
      _email,
      _motDePasse,
      _confirmation,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  bool _validerLocalement() {
    final erreurs = <String, String?>{
      'prenom': ValidateursAcces.nom(_prenom.text, 'Le prénom'),
      'nom': ValidateursAcces.nom(_nom.text, 'Le nom'),
      'telephone': ValidateursAcces.telephone(_telephone.text),
      'email': ValidateursAcces.emailFacultatif(_email.text),
      'password': ValidateursAcces.motDePasse(_motDePasse.text),
      'confirmation': ValidateursAcces.confirmation(
        _motDePasse.text,
        _confirmation.text,
      ),
    };
    setState(() {
      _erreurs
        ..clear()
        ..addAll(erreurs);
      _erreurFormulaire = null;
    });
    return erreurs.values.every((e) => e == null);
  }

  void _effacer(String cle) {
    if (_erreurs[cle] != null) setState(() => _erreurs[cle] = null);
  }

  Future<void> _envoyer() async {
    if (_envoi || _blocage != null || !_validerLocalement()) return;
    setState(() => _envoi = true);
    final email = _email.text.trim();
    try {
      await ref.read(sessionProvider.notifier).inscrire({
        'nom': _nom.text.trim(),
        'prenom': _prenom.text.trim(),
        'telephone': ValidateursAcces.telephoneApi(_telephone.text),
        if (email.isNotEmpty) 'email': email,
        'password': _motDePasse.text,
      });
    } on ErreurValidation catch (e) {
      if (!mounted) return;
      setState(() {
        var trouve = false;
        for (final cle in ['prenom', 'nom', 'telephone', 'email', 'password']) {
          final message = e.premierMessage(cle);
          _erreurs[cle] = message;
          if (message != null) trouve = true;
        }
        if (!trouve) _erreurFormulaire = e.message;
      });
    } on ErreurTropDeRequetes catch (e) {
      if (mounted) setState(() => _blocage = e.delai ?? _blocageParDefaut);
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
    return Scaffold(
      body: EcranAcces(
        chevauchement: 40,
        margePied: 24,
        entete: const EnteteFormulaire(
          titre1: 'Créer votre',
          titre2: 'compte client',
          droite: 'Étape 1 sur 1',
          basEntete: 26,
        ),
        corps: [
          ApparitionAcces(
            rang: 0,
            child: CarteFormulaire(
              espace: 12,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: ChampFormulaire(
                        libelle: 'Prénom',
                        controleur: _prenom,
                        hauteur: 50,
                        margeInterne: 14,
                        actionClavier: TextInputAction.next,
                        autofill: const [AutofillHints.givenName],
                        erreur: _erreurs['prenom'],
                        onChanged: (_) => _effacer('prenom'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: ChampFormulaire(
                        libelle: 'Nom',
                        controleur: _nom,
                        hauteur: 50,
                        margeInterne: 14,
                        actionClavier: TextInputAction.next,
                        autofill: const [AutofillHints.familyName],
                        erreur: _erreurs['nom'],
                        onChanged: (_) => _effacer('nom'),
                      ),
                    ),
                  ],
                ),
                ChampFormulaire(
                  libelle: 'Téléphone',
                  controleur: _telephone,
                  hauteur: 50,
                  margeInterne: 14,
                  clavier: TextInputType.phone,
                  actionClavier: TextInputAction.next,
                  autofill: const [AutofillHints.telephoneNumber],
                  erreur: _erreurs['telephone'],
                  onChanged: (_) => _effacer('telephone'),
                ),
                ChampFormulaire(
                  libelle:
                      'E-mail (facultatif, utile pour récupérer votre compte)',
                  controleur: _email,
                  hauteur: 50,
                  margeInterne: 14,
                  clavier: TextInputType.emailAddress,
                  actionClavier: TextInputAction.next,
                  autofill: const [AutofillHints.email],
                  erreur: _erreurs['email'],
                  onChanged: (_) => _effacer('email'),
                ),
                ChampFormulaire(
                  libelle: 'Mot de passe',
                  controleur: _motDePasse,
                  hauteur: 50,
                  margeInterne: 14,
                  motDePasse: true,
                  actionClavier: TextInputAction.next,
                  autofill: const [AutofillHints.newPassword],
                  erreur: _erreurs['password'],
                  onChanged: (_) => _effacer('password'),
                ),
                ChampFormulaire(
                  libelle: 'Confirmer le mot de passe',
                  controleur: _confirmation,
                  hauteur: 50,
                  margeInterne: 14,
                  motDePasse: true,
                  actionClavier: TextInputAction.done,
                  autofill: const [AutofillHints.newPassword],
                  erreur: _erreurs['confirmation'],
                  onChanged: (_) => _effacer('confirmation'),
                  onSubmitted: (_) => _envoyer(),
                ),
                if (bloque)
                  CompteARebours(
                    key: ValueKey(_blocage),
                    duree: _blocage!,
                    onFin: () => setState(() => _blocage = null),
                    builder: (_, restant) => BandeauErreur.alerte(
                      message:
                          'Trop de tentatives. Réessayez dans ${formaterDecompte(restant)}.',
                    ),
                  ),
                BoutonPrimaire(
                  libelle: 'Créer mon compte',
                  chargement: _envoi,
                  onPressed: bloque ? null : _envoyer,
                ),
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
        ],
        pied: ApparitionAcces(
          rang: 1,
          child: Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 6,
            children: [
              Text(
                'Déjà un compte ?',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 14,
                  fontWeight: FontWeight.w400,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
              LienTexte(
                texte: 'Se connecter',
                onTap: () => retourOuConnexion(context),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
