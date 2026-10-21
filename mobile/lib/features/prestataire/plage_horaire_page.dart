import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/interrupteur_sopi.dart';
import '../../core/widgets/puces_choix.dart';
import '../../core/widgets/saisie_masquee.dart';
import '../reservation/depot_reservation.dart' show horlogeProvider;
import '../reservation/formats.dart';
import 'depot_prestataire.dart';
import 'modeles.dart';
import 'widgets/composants_agenda.dart';

/// Durées de créneau proposées par les puces (maquette PlageHoraire). Le serveur accepte de 15 à 480 minutes.
const dureesCreneau = [30, 45, 60, 90, 120];

/// Répétitions hebdomadaires proposées : 1 à 8 semaines (le serveur refuse au-delà).
const semainesMinRepetition = 1;
const semainesMaxRepetition = 8;

/// Ajouter des créneaux (maquette PlageHoraire) : un jour, une plage horaire, la durée d'un créneau, une répétition hebdomadaire.
class PlageHorairePage extends ConsumerStatefulWidget {
  const PlageHorairePage({super.key});

  @override
  ConsumerState<PlageHorairePage> createState() => _PlageHorairePageState();
}

class _PlageHorairePageState extends ConsumerState<PlageHorairePage> {
  final _debut = TextEditingController(text: '08:00');
  final _fin = TextEditingController(text: '18:00');
  late DateTime _jour;
  int _duree = 60;
  bool _repeter = false;
  int _semaines = 4;
  Map<String, String> _erreurs = {};
  bool _enCours = false;

  @override
  void initState() {
    super.initState();
    _jour = debutDuJour(ref.read(horlogeProvider)());
  }

  @override
  void dispose() {
    _debut.dispose();
    _fin.dispose();
    super.dispose();
  }

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.disponibilites);

  void _effacer(String champ) =>
      setState(() => _erreurs = {..._erreurs}..remove(champ));

  Future<void> _choisirJour() async {
    final aujourdhui = debutDuJour(ref.read(horlogeProvider)());
    final choix = await showDatePicker(
      context: context,
      initialDate: _jour.isBefore(aujourdhui) ? aujourdhui : _jour,
      firstDate: aujourdhui,
      lastDate: aujourdhui.add(const Duration(days: 365)),
      locale: const Locale('fr', 'FR'),
    );
    if (choix == null || !mounted) return;
    setState(() {
      _jour = DateTime.utc(choix.year, choix.month, choix.day);
      _erreurs = {..._erreurs}..remove('date');
    });
  }

  Future<void> _creer() async {
    final debut = lireHeure(_debut.text);
    final fin = lireHeure(_fin.text);
    final locales = <String, String>{
      if (debut == null) 'heure_debut': 'Heure invalide (HH:MM).',
      if (fin == null) 'heure_fin': 'Heure invalide (HH:MM).',
    };
    if (locales.isNotEmpty) {
      setState(() => _erreurs = locales);
      return;
    }
    final messager = ScaffoldMessenger.of(context);
    if (_enCours) return;
    setState(() {
      _enCours = true;
      _erreurs = {};
    });
    try {
      final nombre = await ref
          .read(depotPrestataireProvider)
          .creerPlage(
            PlageHoraire(
              jour: formaterJourApi(_jour),
              heureDebut: debut!,
              heureFin: fin!,
              dureeCreneauMinutes: _duree,
              repeterSemaines: _repeter ? _semaines : null,
            ),
          );
      ref.invalidate(creneauxProvider);
      messager.showSnackBar(
        SnackBar(
          content: Text(
            nombre > 0
                ? (nombre == 1 ? '1 créneau créé.' : '$nombre créneaux créés.')
                : 'Créneaux créés.',
          ),
        ),
      );
      if (!mounted) return;
      _retour();
    } catch (e) {
      final erreur = ErreurApi.depuis(e);
      if (!mounted) return;
      final parChamp = <String, String>{};
      if (erreur is ErreurValidation) {
        for (final champ in const [
          'date',
          'heure_debut',
          'heure_fin',
          'duree_creneau_minutes',
          'repeter_semaines',
          'plages',
        ]) {
          final message = messageServeur(erreur, champ);
          if (message != null) parChamp[champ] = message;
        }
      }
      setState(() {
        _enCours = false;
        _erreurs = parChamp;
      });
      if (parChamp.isEmpty) {
        messager.showSnackBar(SnackBar(content: Text(erreur.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteEcran(
                  titreLigne1: 'Ajouter des',
                  titreLigne2: 'créneaux',
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
                          ChampSelecteur(
                            libelle: 'Jour',
                            valeur: '${formaterJourCourt(_jour)} ${_jour.year}',
                            erreur: _erreurs['date'],
                            onTap: _enCours ? () {} : _choisirJour,
                          ),
                          const SizedBox(height: 14),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: ChampFormulaire(
                                  libelle: 'De',
                                  controleur: _debut,
                                  hauteur: 50,
                                  margeInterne: 14,
                                  enabled: !_enCours,
                                  clavier: TextInputType.number,
                                  formateurs: const [SaisieMasquee.heure],
                                  erreur: _erreurs['heure_debut'],
                                  onChanged: (_) => _effacer('heure_debut'),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: ChampFormulaire(
                                  libelle: 'À',
                                  controleur: _fin,
                                  hauteur: 50,
                                  margeInterne: 14,
                                  enabled: !_enCours,
                                  clavier: TextInputType.number,
                                  formateurs: const [SaisieMasquee.heure],
                                  erreur: _erreurs['heure_fin'],
                                  onChanged: (_) => _effacer('heure_fin'),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          Text(
                            "Durée d'un créneau",
                            style: theme.textTheme.labelMedium?.copyWith(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: CouleursSopi.texteSecondaire,
                            ),
                          ),
                          const SizedBox(height: 8),
                          PucesChoix<int>(
                            valeurs: dureesCreneau,
                            libelle: formaterDuree,
                            choisie: _duree,
                            actif: !_enCours,
                            onChoix: (d) => setState(() {
                              _duree = d;
                              _erreurs = {..._erreurs}
                                ..remove('duree_creneau_minutes');
                            }),
                          ),
                          if (_erreurs['duree_creneau_minutes'] != null)
                            MessageChamp(_erreurs['duree_creneau_minutes']!),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Répéter chaque semaine',
                                      style: theme.textTheme.titleMedium
                                          ?.copyWith(
                                            fontSize: 15,
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                                    Text(
                                      'Pendant $_semaines semaines ($semainesMaxRepetition au maximum)',
                                      style: theme.textTheme.bodySmall
                                          ?.copyWith(
                                            fontSize: 12,
                                            color: CouleursSopi.texteSecondaire,
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 12),
                              InterrupteurSopi(
                                libelle: 'Répéter chaque semaine',
                                valeur: _repeter,
                                onChanged: _enCours
                                    ? null
                                    : (v) => setState(() {
                                        _repeter = v;
                                        _erreurs = {..._erreurs}
                                          ..remove('repeter_semaines');
                                      }),
                              ),
                            ],
                          ),
                          if (_repeter)
                            Padding(
                              padding: const EdgeInsets.only(top: 10),
                              child: _CompteurSemaines(
                                valeur: _semaines,
                                actif: !_enCours,
                                onChange: (v) => setState(() {
                                  _semaines = v;
                                  _erreurs = {..._erreurs}
                                    ..remove('repeter_semaines');
                                }),
                              ),
                            ),
                          if (_erreurs['repeter_semaines'] != null)
                            MessageChamp(_erreurs['repeter_semaines']!),
                        ],
                      ),
                    ),
                  ),
                ),
                if (_erreurs['plages'] != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                    child: BandeauErreur.alerte(message: _erreurs['plages']!),
                  ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                  child: Text(
                    'Les clients pourront réserver ces créneaux. Les réservations déjà prises ne sont pas modifiées.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12,
                      height: 17 / 12,
                      color: CouleursSopi.texteSecondaire,
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
              libelle: 'Créer les créneaux',
              chargement: _enCours,
              onPressed: _enCours ? null : _creer,
            ),
          ),
        ],
      ),
    );
  }
}

/// Choix du nombre de semaines de répétition (ajout à la maquette : elle n'affiche que « 4 semaines »).
class _CompteurSemaines extends StatelessWidget {
  const _CompteurSemaines({
    required this.valeur,
    required this.onChange,
    required this.actif,
  });

  final int valeur;
  final ValueChanged<int> onChange;
  final bool actif;

  @override
  Widget build(BuildContext context) {
    Widget bouton(IconData icone, String libelle, int? cible) => Semantics(
      button: true,
      label: libelle,
      excludeSemantics: true,
      child: Material(
        color: CouleursSopi.enteteFond,
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: actif && cible != null ? () => onChange(cible) : null,
          child: SizedBox(
            width: 36,
            height: 36,
            child: Icon(
              icone,
              size: 18,
              color: cible == null
                  ? CouleursSopi.bordureForte
                  : CouleursSopi.bleu,
            ),
          ),
        ),
      ),
    );

    return Row(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        bouton(
          Icons.remove_rounded,
          'Moins de semaines',
          valeur > semainesMinRepetition ? valeur - 1 : null,
        ),
        SizedBox(
          width: 44,
          child: Text(
            '$valeur',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
          ),
        ),
        bouton(
          Icons.add_rounded,
          'Plus de semaines',
          valeur < semainesMaxRepetition ? valeur + 1 : null,
        ),
      ],
    );
  }
}
