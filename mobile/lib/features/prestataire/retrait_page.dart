import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/saisie_masquee.dart';
import 'depot_prestataire.dart';
import 'modeles_portefeuille.dart';
import 'widgets/composants_agenda.dart';

/// Demande de retrait (maquettes Retrait et RetraitEchec). Le montant, le numéro et l'opérateur sont envoyés tels quels :
/// le serveur vérifie les limites et le solde, débite, puis verse. Un refus s'affiche avec le message du serveur.
class RetraitPage extends ConsumerStatefulWidget {
  const RetraitPage({super.key});

  @override
  ConsumerState<RetraitPage> createState() => _RetraitPageState();
}

class _RetraitPageState extends ConsumerState<RetraitPage> {
  final _montant = TextEditingController();
  final _telephone = TextEditingController();
  OperateurRetrait _operateur = OperateurRetrait.wave;
  Map<String, String> _erreurs = {};

  /// Message d'un refus qui ne concerne pas un champ (503 versement refusé, 429, réseau…), affiché dans le bandeau rouge.
  String? _echec;
  bool _enCours = false;
  RetraitCree? _resultat;

  @override
  void dispose() {
    _montant.dispose();
    _telephone.dispose();
    super.dispose();
  }

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.portefeuille);

  void _effacer(String champ) => setState(() {
    _erreurs = {..._erreurs}..remove(champ);
    _echec = null;
  });

  Future<void> _envoyer() async {
    // Garde contre le double envoi : le bouton est désactivé, et cette garde couvre un second appel avant la reconstruction.
    if (_enCours) return;
    final montant = lireMontant(_montant.text);
    final telephone = _telephone.text.replaceAll(RegExp(r'[\s.\-()]'), '');
    final locales = <String, String>{
      if (montant == null || montant <= 0)
        'montant': 'Indiquez le montant à retirer.',
      if (telephone.isEmpty) 'telephone_versement': 'Indiquez votre numéro.',
    };
    if (locales.isNotEmpty) {
      setState(() {
        _erreurs = locales;
        _echec = null;
      });
      return;
    }
    setState(() {
      _enCours = true;
      _erreurs = {};
      _echec = null;
    });
    try {
      final retrait = await ref
          .read(depotPrestataireProvider)
          .demanderRetrait(
            montant: montant!,
            telephone: telephone,
            operateur: _operateur,
          );
      rafraichirPortefeuille(ref);
      if (!mounted) return;
      setState(() {
        _enCours = false;
        _resultat = retrait;
      });
    } catch (e) {
      final erreur = ErreurApi.depuis(e);
      if (!mounted) return;
      final parChamp = <String, String>{};
      if (erreur is ErreurValidation) {
        for (final champ in const [
          'montant',
          'telephone_versement',
          'operateur',
        ]) {
          final message = messageServeur(erreur, champ);
          if (message != null) parChamp[champ] = message;
        }
      }
      // Une erreur de réseau ou de session peut cacher un retrait déjà pris en compte : le solde est relu.
      ref.invalidate(soldeProvider);
      setState(() {
        _enCours = false;
        _erreurs = parChamp;
        _echec = parChamp.isEmpty ? erreur.message : null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final resultat = _resultat;
    if (resultat != null) {
      return _Confirmation(retrait: resultat, onTerminer: _retour);
    }

    final theme = Theme.of(context);
    final solde = ref.watch(soldeProvider);
    final limites = solde.value;
    final montant = lireMontant(_montant.text);

    Widget ligneSolde;
    if (solde.hasValue) {
      ligneSolde = Text.rich(
        TextSpan(
          text: 'Solde disponible : ',
          children: [
            TextSpan(
              text: formaterArgent(limites!.solde),
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                color: CouleursSopi.marine,
              ),
            ),
          ],
        ),
        style: theme.textTheme.bodyMedium?.copyWith(
          fontSize: 13,
          color: CouleursSopi.texteDiscret,
        ),
      );
    } else if (solde.hasError) {
      ligneSolde = InkWell(
        onTap: () => ref.invalidate(soldeProvider),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 44),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Solde indisponible. Toucher pour réessayer.',
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                decoration: TextDecoration.underline,
                color: CouleursSopi.erreurTexte,
              ),
            ),
          ),
        ),
      );
    } else {
      ligneSolde = Text(
        'Solde disponible : …',
        style: theme.textTheme.bodyMedium?.copyWith(
          fontSize: 13,
          color: CouleursSopi.texteDiscret,
        ),
      );
    }

    final texteLimites = limites?.minimum != null && limites?.maximum != null
        ? 'Minimum ${formaterArgent(limites!.minimum!)}, maximum ${formaterArgent(limites.maximum!)}.'
        : null;

    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteDegrade(
                  titreLigne1: 'Retirer',
                  titreLigne2: 'mon argent',
                  onRetour: _retour,
                ),
                Transform.translate(
                  offset: const Offset(0, -16),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: CouleursSopi.blanc,
                        borderRadius: BorderRadius.circular(30),
                        border: Border.all(color: CouleursSopi.bordure),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          ligneSolde,
                          const SizedBox(height: 14),
                          ChampFormulaire(
                            libelle: 'Montant à retirer (FCFA)',
                            controleur: _montant,
                            hauteur: 60,
                            tailleTexte: 24,
                            poidsTexte: FontWeight.w800,
                            enabled: !_enCours,
                            clavier: TextInputType.number,
                            formateurs: const [SaisieMilliers()],
                            erreur: _erreurs['montant'],
                            onChanged: (_) {
                              _effacer('montant');
                            },
                          ),
                          if (texteLimites != null) ...[
                            const SizedBox(height: 14),
                            Text(
                              texteLimites,
                              style: theme.textTheme.bodySmall?.copyWith(
                                fontSize: 12,
                                color: CouleursSopi.texteDiscret,
                              ),
                            ),
                          ],
                          const SizedBox(height: 14),
                          Text(
                            'Recevoir sur',
                            style: theme.textTheme.labelMedium?.copyWith(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: CouleursSopi.texteSecondaire,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              for (final o in OperateurRetrait.values) ...[
                                if (o != OperateurRetrait.values.first)
                                  const SizedBox(width: 10),
                                Expanded(
                                  child: _ChoixOperateur(
                                    operateur: o,
                                    choisi: _operateur == o,
                                    actif: !_enCours,
                                    onTap: () => setState(() {
                                      _operateur = o;
                                      _erreurs = {..._erreurs}
                                        ..remove('operateur');
                                      _echec = null;
                                    }),
                                  ),
                                ),
                              ],
                            ],
                          ),
                          if (_erreurs['operateur'] != null)
                            MessageChamp(_erreurs['operateur']!),
                          const SizedBox(height: 14),
                          ChampFormulaire(
                            libelle: 'Numéro de téléphone',
                            controleur: _telephone,
                            indication: '77 123 45 67',
                            enabled: !_enCours,
                            clavier: TextInputType.phone,
                            formateurs: [
                              FilteringTextInputFormatter.allow(
                                RegExp(r'[0-9+ ]'),
                              ),
                            ],
                            erreur: _erreurs['telephone_versement'],
                            onChanged: (_) => _effacer('telephone_versement'),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  child: _echec != null
                      ? BandeauErreur.alerte(message: _echec!)
                      : Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 18,
                            vertical: 14,
                          ),
                          decoration: BoxDecoration(
                            color: CouleursSopi.bleuPastel,
                            borderRadius: BorderRadius.circular(22),
                          ),
                          child: Text(
                            "Le versement est envoyé automatiquement. Vous êtes prévenu dès qu'il est effectué.",
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 13,
                              height: 19 / 13,
                              color: CouleursSopi.bleuProfond,
                            ),
                          ),
                        ),
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              8,
              16,
              MediaQuery.paddingOf(context).bottom + 24,
            ),
            child: BoutonPrimaire(
              libelle: montant == null || montant <= 0
                  ? 'Retirer'
                  : 'Retirer ${formaterArgent(montant)}',
              chargement: _enCours,
              onPressed: _enCours ? null : _envoyer,
            ),
          ),
        ],
      ),
    );
  }
}

/// Bouton de choix de l'opérateur : logo 40 × 40 et nom, cadre bleu de 2 px quand il est choisi.
class _ChoixOperateur extends StatelessWidget {
  const _ChoixOperateur({
    required this.operateur,
    required this.choisi,
    required this.actif,
    required this.onTap,
  });

  final OperateurRetrait operateur;
  final bool choisi;
  final bool actif;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: choisi,
      label: operateur.libelle,
      excludeSemantics: true,
      child: Material(
        color: CouleursSopi.blanc,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(
            color: choisi ? CouleursSopi.bleu : CouleursSopi.bordure,
            width: choisi ? 2 : 1,
          ),
        ),
        child: InkWell(
          key: ValueKey('operateur-${operateur.code}'),
          customBorder: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          onTap: actif ? onTap : null,
          child: SizedBox(
            height: 60,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.asset(
                    operateur.logo,
                    width: 40,
                    height: 40,
                    fit: BoxFit.cover,
                    excludeFromSemantics: true,
                  ),
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    operateur.libelle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
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

/// Écran affiché une fois le retrait accepté par le serveur (sans maquette : même en-tête et même carte que le formulaire).
/// `reussi` : versement terminé. `en_cours` : accepté mais pas encore terminé (le montant reste débité).
class _Confirmation extends StatelessWidget {
  const _Confirmation({required this.retrait, required this.onTerminer});

  final RetraitCree retrait;
  final VoidCallback onTerminer;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final termine = retrait.statut == StatutRetrait.reussi;
    final vers = [
      if (retrait.operateur != null) retrait.operateur!.libelle,
      if (retrait.telephone != null) retrait.telephone!,
    ].join(' · ');
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteDegrade(
                  titreLigne1: 'Retrait',
                  titreLigne2: termine ? 'effectué' : 'en cours',
                  onRetour: onTerminer,
                ),
                Transform.translate(
                  offset: const Offset(0, -16),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: CouleursSopi.blanc,
                        borderRadius: BorderRadius.circular(30),
                        border: Border.all(color: CouleursSopi.bordure),
                      ),
                      child: Column(
                        children: [
                          Container(
                            width: 64,
                            height: 64,
                            decoration: BoxDecoration(
                              color: termine
                                  ? CouleursSopi.bleuPastel
                                  : CouleursSopi.ardoisePastel,
                              shape: BoxShape.circle,
                            ),
                            child: Icon(
                              termine
                                  ? Icons.check_rounded
                                  : Icons.schedule_rounded,
                              size: 32,
                              color: termine
                                  ? CouleursSopi.bleu
                                  : CouleursSopi.ardoiseTexte,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            formaterArgent(retrait.montant),
                            key: const ValueKey('montant-retrait'),
                            style: theme.textTheme.headlineSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          if (vers.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              vers,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: CouleursSopi.texteDiscret,
                              ),
                            ),
                          ],
                          const SizedBox(height: 14),
                          Text(
                            termine
                                ? 'Le versement a été effectué.'
                                : "Votre demande est prise en compte. Le montant est déjà retiré de votre solde ; vous êtes prévenu dès que le versement est terminé.",
                            textAlign: TextAlign.center,
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 13,
                              height: 19 / 13,
                              color: CouleursSopi.texteSecondaire,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              8,
              16,
              MediaQuery.paddingOf(context).bottom + 24,
            ),
            child: BoutonPrimaire(
              libelle: 'Retour au portefeuille',
              onPressed: onTerminer,
            ),
          ),
        ],
      ),
    );
  }
}
