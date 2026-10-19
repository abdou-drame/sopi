import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/compte_a_rebours.dart';
import '../../core/widgets/sopi_logo.dart';
import '../../core/widgets/decompte.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../inscription_prestataire/stockage_jeton_suivi.dart';
import 'session.dart';
import 'widgets/mise_en_page_acces.dart';

const messageIdentifiantsFaux = 'Identifiant ou mot de passe incorrect.';
const _blocageParDefaut = Duration(minutes: 15);

/// Écran de connexion (maquettes Connexion et ConnexionBloquee).
class ConnexionPage extends ConsumerStatefulWidget {
  const ConnexionPage({super.key});

  @override
  ConsumerState<ConnexionPage> createState() => _ConnexionPageState();
}

class _ConnexionPageState extends ConsumerState<ConnexionPage> {
  final _identifiant = TextEditingController();
  final _motDePasse = TextEditingController();
  bool _envoi = false;
  String? _erreurFormulaire;
  String? _erreurIdentifiant;
  String? _erreurMotDePasse;

  /// Durée du blocage en cours (429) ; null hors blocage.
  Duration? _blocage;

  @override
  void dispose() {
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
    return Scaffold(
      body: EcranAcces(
        chevauchement: 20,
        margePied: 28,
        entete: _EnteteConnexion(
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
        corps: [
          ApparitionAcces(
            rang: 0,
            child: CarteFormulaire(
              children: [
                ChampFormulaire(
                  libelle: 'E-mail ou téléphone',
                  controleur: _identifiant,
                  clavier: TextInputType.emailAddress,
                  actionClavier: TextInputAction.next,
                  autofill: const [AutofillHints.username],
                  erreur: _erreurIdentifiant,
                  onChanged: (_) {
                    if (_erreurIdentifiant != null) {
                      setState(() => _erreurIdentifiant = null);
                    }
                  },
                ),
                ChampFormulaire(
                  libelle: 'Mot de passe',
                  controleur: _motDePasse,
                  motDePasse: true,
                  actionClavier: TextInputAction.done,
                  autofill: const [AutofillHints.password],
                  erreur: _erreurMotDePasse,
                  erreurSansTexte: bloque,
                  onChanged: (_) {
                    if (_erreurMotDePasse != null) {
                      setState(() => _erreurMotDePasse = null);
                    }
                  },
                  onSubmitted: (_) => _envoyer(),
                ),
                Align(
                  alignment: Alignment.centerRight,
                  child: LienTexte(
                    texte: 'Mot de passe oublié ?',
                    taille: 13,
                    poids: FontWeight.w600,
                    onTap: () => context.push(Chemins.motDePasseOublie),
                  ),
                ),
                BoutonPrimaire(
                  libelle: 'Se connecter',
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
          ApparitionAcces(
            rang: 1,
            child: Wrap(
              alignment: WrapAlignment.center,
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: 6,
              children: [
                Text(
                  'Pas encore de compte ?',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 14,
                    fontWeight: FontWeight.w400,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
                LienTexte(
                  texte: 'Créer un compte',
                  onTap: () => context.push(Chemins.inscription),
                ),
                if (ref.watch(jetonSuiviProvider) != null)
                  LienTexte(
                    texte: 'Suivre mon dossier',
                    onTap: () => context.push(Chemins.suiviDossier),
                  ),
              ],
            ),
          ),
        ],
        pied: ApparitionAcces(
          rang: 2,
          child: _CartePrestataire(
            onTap: () => context.push(Chemins.inscriptionPrestataire),
          ),
        ),
      ),
    );
  }
}

/// En-tête : grand dégradé (coins 40), logo, titre en deux lignes (32/38) puis phrase d'accroche ou bandeau de blocage.
class _EnteteConnexion extends StatelessWidget {
  const _EnteteConnexion({required this.bloque, this.bandeau});

  final bool bloque;
  final Widget? bandeau;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    return DescenteDepuisLeHaut(
      child: ClipRRect(
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(DimensionsSopi.rayonEnteteConnexion),
        ),
        child: FondDegrade(
          tacheBasse: true,
          child: Padding(
            padding: EdgeInsets.only(top: haut + 28, bottom: bloque ? 42 : 70),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Apparition(
                    delai: DescenteDepuisLeHaut.duree,
                    child: Row(
                      children: [
                        const SopiLogo(taille: 52),
                        const SizedBox(width: 12),
                        Text(
                          'Sopi',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontSize: 28,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 52),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Apparition(
                    delai: delaiApresEntete(1),
                    child: const TitreDeuxLignes(
                      ligne1: 'Heureux de',
                      ligne2: 'vous revoir !',
                      taille: 32,
                      interligne: 38,
                    ),
                  ),
                ),
                SizedBox(height: bloque ? 6 : 18),
                if (bandeau != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: DimensionsSopi.margeCarte,
                    ),
                    child: Apparition(
                      delai: delaiApresEntete(2),
                      child: bandeau!,
                    ),
                  )
                else
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 28),
                    child: Apparition(
                      delai: delaiApresEntete(2),
                      child: Text(
                        'Réservez un mécanicien, un coiffeur ou un pressing dans votre quartier.',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 14,
                          height: 20 / 14,
                          fontWeight: FontWeight.w400,
                          color: CouleursSopi.texteSecondaire,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Carte marine « Vous êtes professionnel ? » avec son bouton rond bleu.
class _CartePrestataire extends StatelessWidget {
  const _CartePrestataire({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
      decoration: BoxDecoration(
        color: CouleursSopi.marine,
        borderRadius: BorderRadius.circular(DimensionsSopi.rayonCarte),
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
                    color: CouleursSopi.blanc.withValues(alpha: 0.7),
                  ),
                ),
                Text(
                  'Devenir prestataire Sopi',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: CouleursSopi.blanc,
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
