import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/decompte.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import 'depot_reservation.dart';
import 'fiche_prestataire_page.dart';
import 'formats.dart';
import 'modeles.dart';
import 'widgets/composants.dart';

/// Nombre de jours proposés à partir d'aujourd'hui.
const joursProposes = 14;

const messageDelaiDepasse =
    'Délai dépassé. Veuillez sélectionner de nouveau un créneau.';

/// Choix du créneau (maquette Creneau) : jours, heures libres, verrou de 5 minutes avec compte à rebours.
class ChoixCreneauPage extends ConsumerStatefulWidget {
  const ChoixCreneauPage({
    super.key,
    required this.prestataireId,
    required this.prestationId,
  });

  final String prestataireId;
  final String prestationId;

  @override
  ConsumerState<ChoixCreneauPage> createState() => _ChoixCreneauPageState();
}

class _ChoixCreneauPageState extends ConsumerState<ChoixCreneauPage> {
  late final DepotReservation _depot;
  late final DateTime _aujourdhui;
  late final List<DateTime> _jours;

  List<CreneauLibre> _creneaux = const [];
  bool _chargement = true;
  String? _erreur;

  late DateTime _jour;
  CreneauLibre? _choisi;
  VerrouCreneau? _verrou;
  bool _verrouEnCours = false;
  bool _envoi = false;
  bool _consomme = false;
  String? _message;

  @override
  void initState() {
    super.initState();
    _depot = ref.read(depotReservationProvider);
    _aujourdhui = debutDuJour(ref.read(horlogeProvider)());
    _jours = [
      for (var i = 0; i < joursProposes; i++)
        _aujourdhui.add(Duration(days: i)),
    ];
    _jour = _aujourdhui;
    Future.microtask(() => _charger(premiere: true));
  }

  @override
  void dispose() {
    // Quitter l'écran libère le créneau retenu (sauf s'il vient d'être consommé par la demande).
    final v = _verrou;
    if (v != null && !_consomme) _libererSansAttendre(v.creneauId);
    super.dispose();
  }

  void _libererSansAttendre(String creneauId) {
    _depot.liberer(creneauId).catchError((Object _) {});
  }

  Future<void> _charger({bool premiere = false}) async {
    if (premiere) {
      setState(() {
        _chargement = true;
        _erreur = null;
      });
    }
    try {
      final liste = await _depot.agenda(
        widget.prestataireId,
        du: formaterJourApi(_jours.first),
        au: formaterJourApi(_jours.last),
      );
      if (!mounted) return;
      liste.sort((a, b) => a.debut.compareTo(b.debut));
      setState(() {
        _creneaux = liste;
        _chargement = false;
        _erreur = null;
        if (premiere) _jour = _premierJourLibre(liste) ?? _aujourdhui;
        final c = _choisi;
        if (c != null && !liste.any((e) => e.id == c.id) && _verrou == null) {
          _choisi = null;
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        if (premiere || _creneaux.isEmpty) {
          _erreur = ErreurApi.depuis(e).message;
        }
        _chargement = false;
      });
    }
  }

  DateTime? _premierJourLibre(List<CreneauLibre> liste) {
    for (final j in _jours) {
      if (liste.any((c) => debutDuJour(c.debut) == j)) return j;
    }
    return null;
  }

  List<CreneauLibre> get _duJour => [
    for (final c in _creneaux)
      if (debutDuJour(c.debut) == _jour) c,
  ];

  Future<void> _choisirJour(DateTime jour) async {
    if (jour == _jour) return;
    setState(() {
      _jour = jour;
      _message = null;
    });
  }

  Future<void> _choisir(CreneauLibre creneau) async {
    if (_verrouEnCours || _envoi || _choisi?.id == creneau.id) return;
    final ancien = _verrou;
    setState(() {
      _verrouEnCours = true;
      _message = null;
      if (ancien != null) {
        _verrou = null;
        _choisi = null;
      }
    });
    if (ancien != null) {
      try {
        await _depot.liberer(ancien.creneauId);
      } catch (_) {
        // Le verrou expirera tout seul : on poursuit.
      }
    }
    try {
      final verrou = await _depot.verrouiller(creneau.id);
      if (!mounted) {
        _libererSansAttendre(creneau.id);
        return;
      }
      setState(() {
        _choisi = creneau;
        _verrou = verrou;
        _verrouEnCours = false;
      });
    } catch (e) {
      if (!mounted) return;
      final erreur = ErreurApi.depuis(e);
      setState(() {
        _verrouEnCours = false;
        _message = erreur.message;
      });
      if (erreur is ErreurServeur && erreur.statut == 409) {
        await _charger();
      }
    }
  }

  void _delaiDepasse() {
    if (!mounted || _verrou == null) return;
    setState(() {
      _verrou = null;
      _choisi = null;
      _message = messageDelaiDepasse;
    });
  }

  Future<void> _continuer() async {
    final c = _choisi;
    if (c == null || _verrou == null || _envoi) return;
    setState(() {
      _envoi = true;
      _message = null;
    });
    try {
      final reservation = await _depot.demander(
        creneauId: c.id,
        prestationId: widget.prestationId,
      );
      _consomme = true;
      if (!mounted) return;
      context.go(Chemins.demandeEnvoyee(reservation.id), extra: reservation);
    } catch (e) {
      if (!mounted) return;
      final erreur = ErreurApi.depuis(e);
      final perdu = erreur is ErreurServeur && erreur.statut == 409;
      setState(() {
        _envoi = false;
        _message = erreur is ErreurValidation
            ? (erreur.premierMessage('prestation_id') ??
                  erreur.premierMessage('creneau_id') ??
                  erreur.message)
            : erreur.message;
        if (perdu) {
          _verrou = null;
          _choisi = null;
        }
      });
      if (perdu) await _charger();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bas = MediaQuery.paddingOf(context).bottom;
    final profil = ref.watch(profilPrestataireProvider(widget.prestataireId));
    final prestataire = profil.value;
    Prestation? prestation;
    for (final p in prestataire?.prestations ?? const <Prestation>[]) {
      if (p.id == widget.prestationId) prestation = p;
    }

    final corps = widget.prestationId.isEmpty
        ? SizedBox(
            height: 320,
            child: EtatErreur(
              message: "Choisissez d'abord un service sur la fiche.",
              onReessayer: () => context.pop(),
            ),
          )
        : _erreur != null
        ? SizedBox(
            height: 320,
            child: EtatErreur(
              message: _erreur!,
              onReessayer: () => _charger(premiere: true),
            ),
          )
        : _contenu(theme, prestation);

    final c = _choisi;
    return Scaffold(
      body: Stack(
        children: [
          RefreshIndicator(
            onRefresh: _charger,
            edgeOffset: MediaQuery.paddingOf(context).top,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: EnTeteEcran(
                    titreLigne1: 'Choisissez un',
                    titreLigne2: 'créneau',
                    tailleTitre: 28,
                    interligneTitre: 34,
                    onRetour: () => context.pop(),
                    action: prestataire == null
                        ? null
                        : ConstrainedBox(
                            constraints: BoxConstraints(
                              maxWidth: MediaQuery.sizeOf(context).width - 140,
                            ),
                            child: Text(
                              prestataire.nomEntreprise,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontSize: 17,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                  ),
                ),
                SliverToBoxAdapter(child: corps),
                SliverToBoxAdapter(child: SizedBox(height: bas + 120)),
              ],
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: bas + 16,
            child: BarreBasBleue(
              etiquette: c == null ? null : formaterJourCourt(c.debut),
              valeur: c == null ? null : formaterHeure(c.debut),
              texteVide: 'Choisissez une heure',
              libelleBouton: 'Continuer',
              chargement: _envoi,
              onPressed: c == null || _verrou == null || _envoi
                  ? null
                  : _continuer,
            ),
          ),
        ],
      ),
    );
  }

  Widget _contenu(ThemeData theme, Prestation? prestation) {
    final heures = _duJour;
    final retenu = _verrou;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Text(
                  formaterMoisAnnee(_jour),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (prestation != null)
                Flexible(
                  child: Text(
                    '${prestation.nom} · ${formaterDuree(prestation.dureeMinutes)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12,
                      color: CouleursSopi.texteSecondaire,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 76,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _jours.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (_, i) => _TuileJour(
                jour: _jours[i],
                choisi: _jours[i] == _jour,
                onTap: () => _choisirJour(_jours[i]),
              ),
            ),
          ),
          const SizedBox(height: 22),
          Text(
            'Heures disponibles',
            style: theme.textTheme.titleMedium?.copyWith(
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          if (_chargement)
            const _SqueletteHeures()
          else if (heures.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text(
                'Aucun créneau libre ce jour-là.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
            )
          else
            LayoutBuilder(
              builder: (_, contraintes) {
                const ecart = 10.0;
                final largeur = (contraintes.maxWidth - 2 * ecart) / 3;
                return Wrap(
                  spacing: ecart,
                  runSpacing: ecart,
                  children: [
                    for (var i = 0; i < heures.length; i++)
                      Apparition.indexee(
                        index: i,
                        child: SizedBox(
                          width: largeur,
                          child: _BoutonHeure(
                            libelle: formaterHeure(heures[i].debut),
                            choisi: _choisi?.id == heures[i].id,
                            onTap: () => _choisir(heures[i]),
                          ),
                        ),
                      ),
                  ],
                );
              },
            ),
          if (_message != null) ...[
            const SizedBox(height: 14),
            Text(
              _message!,
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: CouleursSopi.erreurTexte,
              ),
            ),
          ],
          if (retenu != null) ...[
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: CouleursSopi.bleuPastel,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Créneau retenu pour vous',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: CouleursSopi.bleuProfond,
                      ),
                    ),
                  ),
                  Decompte(
                    key: ValueKey('${retenu.creneauId}-${retenu.expireA}'),
                    expiration: retenu.expireA,
                    maintenant: ref.read(horlogeProvider),
                    onExpire: _delaiDepasse,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: CouleursSopi.bleuProfond,
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          Text(
            'Seuls les créneaux libres sont affichés. Une fois choisi, le créneau vous est réservé le temps du compte à rebours. Passé ce délai, il faudra le choisir de nouveau.',
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 12,
              height: 17 / 12,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
        ],
      ),
    );
  }
}

class _TuileJour extends StatelessWidget {
  const _TuileJour({
    required this.jour,
    required this.choisi,
    required this.onTap,
  });

  final DateTime jour;
  final bool choisi;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final couleur = choisi ? CouleursSopi.blanc : CouleursSopi.texte;
    return Semantics(
      button: true,
      selected: choisi,
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          width: 56,
          height: 76,
          decoration: BoxDecoration(
            color: choisi ? CouleursSopi.bleu : CouleursSopi.blanc,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(
              color: choisi ? CouleursSopi.bleu : CouleursSopi.bordure,
            ),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                formaterJourSemaine(jour),
                style: theme.textTheme.bodySmall?.copyWith(
                  fontSize: 11,
                  color: choisi
                      ? couleur.withValues(alpha: 0.85)
                      : CouleursSopi.texteSecondaire,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${jour.day}',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: couleur,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BoutonHeure extends StatelessWidget {
  const _BoutonHeure({
    required this.libelle,
    required this.choisi,
    required this.onTap,
  });

  final String libelle;
  final bool choisi;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      button: true,
      selected: choisi,
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          height: 48,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: choisi ? CouleursSopi.texte : CouleursSopi.blanc,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: choisi ? CouleursSopi.texte : CouleursSopi.bordure,
            ),
          ),
          child: Text(
            libelle,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              fontWeight: choisi ? FontWeight.w700 : FontWeight.w600,
              color: choisi ? CouleursSopi.blanc : CouleursSopi.texte,
            ),
          ),
        ),
      ),
    );
  }
}

class _SqueletteHeures extends StatelessWidget {
  const _SqueletteHeures();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (_, contraintes) {
        final largeur = (contraintes.maxWidth - 20) / 3;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (var i = 0; i < 6; i++)
              Squelette(largeur: largeur, hauteur: 48, rayon: 24),
          ],
        );
      },
    );
  }
}
