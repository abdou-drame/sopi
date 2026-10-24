import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/saisie_masquee.dart';
import '../../core/theme/couleurs.dart';
import '../reservation/depot_reservation.dart' show horlogeProvider;
import 'actions_demande.dart';
import 'depot_prestataire.dart';
import 'modeles.dart';
import 'widgets/composants_agenda.dart';

/// Déclarer une indisponibilité (maquette Conge) : une période pendant laquelle le prestataire ne travaille pas.
/// Le serveur annule les réservations de la période et rembourse les clients ; la confirmation le rappelle.
class IndisponibilitePage extends ConsumerStatefulWidget {
  const IndisponibilitePage({super.key});

  @override
  ConsumerState<IndisponibilitePage> createState() =>
      _IndisponibilitePageState();
}

class _IndisponibilitePageState extends ConsumerState<IndisponibilitePage> {
  late final TextEditingController _jourDebut;
  final _heureDebut = TextEditingController(text: '08:00');
  late final TextEditingController _jourFin;
  final _heureFin = TextEditingController(text: '18:00');
  Map<String, String> _erreurs = {};
  bool _enCours = false;

  /// Réservations de la période saisie (aperçu serveur) et choix du prestataire à leur sujet.
  List<ReservationConcernee> _concernees = const [];
  bool _annuler = true;
  int _versionApercu = 0;

  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      if (mounted) _chargerApercu();
    });
    final j = ref.read(horlogeProvider)().toUtc();
    final aujourdhui =
        '${j.day.toString().padLeft(2, '0')}/${j.month.toString().padLeft(2, '0')}/${j.year}';
    _jourDebut = TextEditingController(text: aujourdhui);
    _jourFin = TextEditingController(text: aujourdhui);
  }

  @override
  void dispose() {
    _jourDebut.dispose();
    _heureDebut.dispose();
    _jourFin.dispose();
    _heureFin.dispose();
    super.dispose();
  }

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.disponibilites);

  void _effacer(String champ) {
    setState(() => _erreurs = {..._erreurs}..remove(champ));
    _chargerApercu();
  }

  /// Relit les réservations de la période dès que les deux dates sont complètes ; une réponse périmée est ignorée.
  /// Un échec de l'aperçu n'empêche pas de déclarer : la carte est simplement absente.
  Future<void> _chargerApercu() async {
    final version = ++_versionApercu;
    final debut = _valeur(_jourDebut, _heureDebut);
    final fin = _valeur(_jourFin, _heureFin);
    List<ReservationConcernee> liste = const [];
    if (debut != null && fin != null) {
      try {
        liste = await ref
            .read(depotPrestataireProvider)
            .apercuIndisponibilite(debut: debut, fin: fin);
      } catch (_) {
        liste = const [];
      }
    }
    if (!mounted || version != _versionApercu) return;
    setState(() {
      _concernees = liste;
      if (liste.isEmpty) _annuler = true;
    });
  }

  bool get _garder => _concernees.isNotEmpty && !_annuler;

  /// « AAAA-MM-JJ HH:MM », le format attendu par l'API ; null si la saisie n'est pas une date et une heure.
  String? _valeur(TextEditingController jour, TextEditingController heure) {
    final d = lireDate(jour.text);
    final h = lireHeure(heure.text);
    return d == null || h == null ? null : '$d $h';
  }

  /// « 2030-01-15 08:00 » → « Mar. 15 janv., 08h00 ».
  String _lisible(String valeur) {
    final p = valeur.split(' ');
    final d = p[0].split('-').map(int.parse).toList();
    final h = p[1].split(':').map(int.parse).toList();
    return formaterDate(DateTime.utc(d[0], d[1], d[2], h[0], h[1]));
  }

  Future<void> _declarer() async {
    final debut = _valeur(_jourDebut, _heureDebut);
    final fin = _valeur(_jourFin, _heureFin);
    final locales = <String, String>{
      if (debut == null) 'debut': 'Date ou heure invalide (JJ/MM/AAAA, HH:MM).',
      if (fin == null) 'fin': 'Date ou heure invalide (JJ/MM/AAAA, HH:MM).',
    };
    if (locales.isNotEmpty) {
      setState(() => _erreurs = locales);
      return;
    }
    final garder = _garder;
    final confirme = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text("Déclarer l'indisponibilité ?"),
        content: Text(
          'Du ${_lisible(debut!)} au ${_lisible(fin!)}.\n\n${garder ? 'Les réservations déjà prises sur cette période sont conservées ; seuls les créneaux libres seront bloqués.' : 'Les réservations déjà prises sur cette période seront annulées et les clients remboursés.'}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Non'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Oui, déclarer'),
          ),
        ],
      ),
    );
    if (confirme != true || !mounted) return;
    final messager = ScaffoldMessenger.of(context);
    if (_enCours) return;
    setState(() {
      _enCours = true;
      _erreurs = {};
    });
    try {
      final bilan = await ref
          .read(depotPrestataireProvider)
          .declarerIndisponibilite(
            debut: debut!,
            fin: fin!,
            annulerReservations: !garder,
          );
      ref.invalidate(creneauxProvider);
      rafraichirDemandes(ref);
      final annulees = bilan.reservationsAnnulees;
      messager.showSnackBar(
        SnackBar(
          content: Text(
            garder
                ? 'Indisponibilité enregistrée. Les réservations existantes sont conservées.'
                : annulees > 0
                ? "Indisponibilité enregistrée. $annulees réservation${annulees > 1 ? 's' : ''} annulée${annulees > 1 ? 's' : ''}, clients remboursés."
                : 'Indisponibilité enregistrée.',
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
        for (final champ in const ['debut', 'fin']) {
          final message = messageServeur(erreur, champ);
          if (message != null) parChamp[champ] = message;
        }
      }
      setState(() {
        _enCours = false;
        _erreurs = parChamp;
      });
      // 409 (prestation en cours), réseau, 429… : message du serveur, sans réécriture.
      if (parChamp.isEmpty) {
        messager.showSnackBar(SnackBar(content: Text(erreur.message)));
      }
    }
  }

  Widget _ligne({
    required String libelleJour,
    required TextEditingController jour,
    required TextEditingController heure,
    required String champ,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: ChampFormulaire(
                libelle: libelleJour,
                controleur: jour,
                hauteur: 50,
                margeInterne: 14,
                enabled: !_enCours,
                clavier: TextInputType.number,
                formateurs: const [SaisieMasquee.date],
                erreurSansTexte: _erreurs[champ] != null,
                onChanged: (_) => _effacer(champ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ChampFormulaire(
                libelle: 'Heure',
                controleur: heure,
                hauteur: 50,
                margeInterne: 14,
                enabled: !_enCours,
                clavier: TextInputType.number,
                formateurs: const [SaisieMasquee.heure],
                erreurSansTexte: _erreurs[champ] != null,
                onChanged: (_) => _effacer(champ),
              ),
            ),
          ],
        ),
        if (_erreurs[champ] != null) MessageChamp(_erreurs[champ]!),
      ],
    );
  }

  /// Carte « Réservations concernées (N) » : liste, puis choix d'annuler (par défaut) ou de garder.
  Widget _carteReservations(BuildContext context) {
    final theme = Theme.of(context).textTheme;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(30),
        boxShadow: OmbresSopi.carte,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Réservations concernées (${_concernees.length})',
            style: theme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 10),
          for (final r in _concernees)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Text(
                '${r.client} · ${r.prestation} · ${formaterDate(r.debut)}',
                style: theme.bodyMedium,
              ),
            ),
          const SizedBox(height: 8),
          _choix(
            context,
            libelle: 'Annuler ces réservations',
            detail: 'Les clients sont remboursés',
            choisi: _annuler,
            onTap: () => setState(() => _annuler = true),
          ),
          _choix(
            context,
            libelle: 'Garder ces réservations',
            detail: 'Seuls les créneaux libres sont bloqués',
            choisi: !_annuler,
            onTap: () => setState(() => _annuler = false),
          ),
        ],
      ),
    );
  }

  Widget _choix(
    BuildContext context, {
    required String libelle,
    required String detail,
    required bool choisi,
    required VoidCallback onTap,
  }) {
    final theme = Theme.of(context).textTheme;
    return Semantics(
      button: true,
      selected: choisi,
      child: InkWell(
        onTap: _enCours ? null : onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      libelle,
                      style: theme.bodyLarge?.copyWith(
                        fontWeight: choisi ? FontWeight.w700 : FontWeight.w500,
                      ),
                    ),
                    Text(
                      detail,
                      style: theme.bodySmall?.copyWith(
                        color: CouleursSopi.texteSecondaire,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                choisi
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: choisi ? CouleursSopi.bleu : CouleursSopi.iconeInactive,
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteEcran(
                  titreLigne1: 'Déclarer une',
                  titreLigne2: 'indisponibilité',
                  onRetour: _retour,
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 26, 16, 0),
                  child: Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: CouleursSopi.blanc,
                      borderRadius: BorderRadius.circular(30),
                      boxShadow: OmbresSopi.carte,
                    ),
                    child: Column(
                      children: [
                        _ligne(
                          libelleJour: 'Du',
                          jour: _jourDebut,
                          heure: _heureDebut,
                          champ: 'debut',
                        ),
                        const SizedBox(height: 12),
                        _ligne(
                          libelleJour: 'Au',
                          jour: _jourFin,
                          heure: _heureFin,
                          champ: 'fin',
                        ),
                      ],
                    ),
                  ),
                ),
                if (_concernees.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    child: _carteReservations(context),
                  ),
                if (!_garder)
                  const Padding(
                    padding: EdgeInsets.fromLTRB(16, 6, 16, 16),
                    child: BandeauErreur.alerte(
                      message:
                          'Attention : les réservations déjà prises sur cette période seront annulées et les clients remboursés.',
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
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                BoutonPrimaire(
                  libelle: "Déclarer l'indisponibilité",
                  chargement: _enCours,
                  onPressed: _enCours ? null : _declarer,
                ),
                const SizedBox(height: 10),
                BoutonCompact(
                  libelle: 'Annuler',
                  ton: TonBoutonCompact.clair,
                  hauteur: 50,
                  taille: 15,
                  pleineLargeur: true,
                  onPressed: _enCours ? null : _retour,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
