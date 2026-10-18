import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/interrupteur_sopi.dart';
import '../../core/widgets/puces_choix.dart';
import '../reservation/formats.dart';
import 'depot_prestataire.dart';
import 'modeles.dart';

/// Durées proposées par les puces (maquette ServiceForm). La validité reste jugée par le serveur.
const dureesService = [30, 60, 90, 120, 180];

/// Ajout (`id` nul) ou modification d'un service (maquette ServiceForm).
/// Aucun acompte n'est affiché ni calculé ; il n'y a pas de suppression, seulement l'interrupteur « Service actif ».
class ServiceFormPage extends ConsumerWidget {
  const ServiceFormPage({super.key, this.id});

  final String? id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (id == null) return const _Formulaire();
    final services = ref.watch(prestationsProvider);
    return services.when(
      loading: () => const Scaffold(body: EtatChargement()),
      error: (e, _) => Scaffold(
        body: EtatErreur(
          message: ErreurApi.depuis(e).message,
          onReessayer: () => ref.invalidate(prestationsProvider),
        ),
      ),
      data: (liste) {
        final service = liste.where((s) => s.id == id).firstOrNull;
        if (service == null) {
          return Scaffold(
            body: EtatVide(
              titre: 'Service introuvable',
              message: 'Prestation introuvable.',
              libelleAction: 'Retour',
              onAction: () => context.go(Chemins.services),
            ),
          );
        }
        return _Formulaire(service: service);
      },
    );
  }
}

class _Formulaire extends ConsumerStatefulWidget {
  const _Formulaire({this.service});

  final PrestationService? service;

  @override
  ConsumerState<_Formulaire> createState() => _FormulaireState();
}

class _FormulaireState extends ConsumerState<_Formulaire> {
  late final TextEditingController _nom;
  late final TextEditingController _prix;
  late int? _duree;
  late bool _actif;
  Map<String, String> _erreurs = {};
  bool _enCours = false;

  PrestationService? get _service => widget.service;

  @override
  void initState() {
    super.initState();
    final s = _service;
    _nom = TextEditingController(text: s?.nom ?? '');
    _prix = TextEditingController(text: s == null ? '' : '${s.prix.round()}');
    _duree = s?.dureeMinutes ?? dureesService[1];
    _actif = s?.actif ?? true;
  }

  @override
  void dispose() {
    _nom.dispose();
    _prix.dispose();
    super.dispose();
  }

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.services);

  /// Un service dont la durée n'est pas parmi les puces garde sa durée actuelle comme puce supplémentaire.
  List<int> get _durees {
    final actuelle = _service?.dureeMinutes;
    if (actuelle == null || dureesService.contains(actuelle)) {
      return dureesService;
    }
    return [...dureesService, actuelle]..sort();
  }

  void _effacer(String champ) =>
      setState(() => _erreurs = {..._erreurs}..remove(champ));

  Future<void> _enregistrer() async {
    final nom = _nom.text.trim();
    final prix = num.tryParse(_prix.text.trim());
    final locales = <String, String>{
      if (nom.isEmpty) 'nom': 'Indiquez le nom du service.',
      if (prix == null) 'prix': 'Indiquez le prix du service.',
    };
    if (locales.isNotEmpty) {
      setState(() => _erreurs = locales);
      return;
    }
    final messager = ScaffoldMessenger.of(context);
    final depot = ref.read(depotPrestataireProvider);
    final s = _service;
    if (_enCours) return;
    setState(() {
      _enCours = true;
      _erreurs = {};
    });
    try {
      if (s == null) {
        await depot.creerPrestation(
          nom: nom,
          dureeMinutes: _duree!,
          prix: prix!,
        );
      } else {
        final change =
            nom != s.nom ||
            _duree != s.dureeMinutes ||
            prix != s.prix ||
            _actif != s.actif;
        if (change) {
          await depot.modifierPrestation(
            s.id,
            nom: nom != s.nom ? nom : null,
            dureeMinutes: _duree != s.dureeMinutes ? _duree : null,
            prix: prix != s.prix ? prix : null,
            actif: _actif != s.actif ? _actif : null,
          );
        }
      }
      ref.invalidate(prestationsProvider);
      messager.showSnackBar(
        const SnackBar(content: Text('Service enregistré.')),
      );
      if (!mounted) return;
      _retour();
    } catch (e) {
      final erreur = ErreurApi.depuis(e);
      if (!mounted) return;
      final parChamp = <String, String>{};
      if (erreur is ErreurValidation) {
        for (final champ in const ['nom', 'duree_minutes', 'prix', 'actif']) {
          final message = erreur.premierMessage(champ);
          if (message != null) parChamp[champ] = message;
        }
      }
      setState(() {
        _enCours = false;
        _erreurs = parChamp;
      });
      // Erreur sans champ (réseau, serveur, 404) : message du serveur.
      if (parChamp.isEmpty) {
        messager.showSnackBar(SnackBar(content: Text(erreur.message)));
      }
    }
  }

  Widget _messageErreur(ThemeData theme, String champ) {
    final message = _erreurs[champ];
    if (message == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(left: 6, top: 6),
      child: Text(
        message,
        style: theme.textTheme.bodySmall?.copyWith(
          color: CouleursSopi.erreurTexte,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final edition = _service != null;
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteDegrade(
                  titreLigne1: edition ? 'Modifier le' : 'Ajouter un',
                  titreLigne2: 'service',
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
                          ChampFormulaire(
                            libelle: 'Nom du service',
                            controleur: _nom,
                            hauteur: 50,
                            margeInterne: 14,
                            enabled: !_enCours,
                            erreur: _erreurs['nom'],
                            onChanged: (_) => _effacer('nom'),
                          ),
                          const SizedBox(height: 14),
                          Text(
                            'Durée',
                            style: theme.textTheme.labelMedium?.copyWith(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: CouleursSopi.texteSecondaire,
                            ),
                          ),
                          const SizedBox(height: 8),
                          PucesChoix<int>(
                            valeurs: _durees,
                            libelle: formaterDuree,
                            choisie: _duree,
                            actif: !_enCours,
                            onChoix: (d) => setState(() {
                              _duree = d;
                              _erreurs = {..._erreurs}..remove('duree_minutes');
                            }),
                          ),
                          _messageErreur(theme, 'duree_minutes'),
                          const SizedBox(height: 14),
                          ChampFormulaire(
                            libelle: 'Prix (FCFA)',
                            controleur: _prix,
                            hauteur: 50,
                            margeInterne: 14,
                            enabled: !_enCours,
                            clavier: TextInputType.number,
                            formateurs: [
                              FilteringTextInputFormatter.digitsOnly,
                            ],
                            erreur: _erreurs['prix'],
                            onChanged: (_) => _effacer('prix'),
                          ),
                          const SizedBox(height: 14),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 12,
                            ),
                            decoration: BoxDecoration(
                              color: CouleursSopi.enteteFond,
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Text(
                              "L'acompte demandé au client est calculé automatiquement par Sopi.",
                              style: theme.textTheme.bodyMedium?.copyWith(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: CouleursSopi.bleu,
                              ),
                            ),
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Service actif',
                                      style: theme.textTheme.titleMedium
                                          ?.copyWith(
                                            fontSize: 15,
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                                    Text(
                                      'Visible et réservable par les clients',
                                      style: theme.textTheme.bodySmall
                                          ?.copyWith(
                                            fontSize: 12,
                                            color: CouleursSopi.texteDiscret,
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 12),
                              InterrupteurSopi(
                                libelle: 'Service actif',
                                valeur: _actif,
                                onChanged: _enCours
                                    ? null
                                    : (v) => setState(() {
                                        _actif = v;
                                        _erreurs = {..._erreurs}
                                          ..remove('actif');
                                      }),
                              ),
                            ],
                          ),
                          _messageErreur(theme, 'actif'),
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
              libelle: 'Enregistrer',
              chargement: _enCours,
              onPressed: _enCours ? null : _enregistrer,
            ),
          ),
        ],
      ),
    );
  }
}
