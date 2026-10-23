import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/theme/disposition.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/etats.dart';
import '../../core/widgets/pastille_statut.dart';
import '../reservation/depot_reservation.dart';
import '../reservation/formats.dart';
import '../reservation/modeles.dart' show StatutReservation;
import 'demandes_controleur.dart';
import 'modeles.dart';

/// Nombre de pages de `?groupe=a_venir` chargées d'office (20 lignes chacune) ; au-delà, bouton « Charger plus ».
const pagesAgendaAuto = 5;

const _joursAffiches = 5;

/// Jour calendaire en UTC (Africa/Dakar est à UTC+0 toute l'année).
DateTime _jour(DateTime date) {
  final d = date.toUtc();
  return DateTime.utc(d.year, d.month, d.day);
}

/// « Samedi 24 octobre ». Nécessite `initialiserFormats`.
String _titreJour(DateTime jour) {
  final texte = DateFormat('EEEE d MMMM', 'fr_FR').format(jour);
  return '${texte[0].toUpperCase()}${texte.substring(1)}';
}

/// Agenda du prestataire (maquette Agenda) : les rendez-vous à venir, jour par jour.
class AgendaPage extends ConsumerStatefulWidget {
  const AgendaPage({super.key});

  @override
  ConsumerState<AgendaPage> createState() => _AgendaPageState();
}

class _AgendaPageState extends ConsumerState<AgendaPage> {
  /// Jour choisi par le prestataire ; tant qu'il n'a rien touché : le premier jour avec un rendez-vous.
  DateTime? _choisi;

  DemandesControleur get _controleur =>
      ref.read(listeDemandesProvider(OngletDemandes.aVenir).notifier);

  @override
  Widget build(BuildContext context) {
    final etat = ref.watch(listeDemandesProvider(OngletDemandes.aVenir));
    final aujourdhui = _jour(ref.watch(horlogeProvider)());

    // On enchaîne les pages une à une jusqu'à 5 (100 lignes) ; ensuite c'est le bouton « Charger plus ».
    final page = etat.pagination?.page ?? 1;
    if (!etat.chargement &&
        etat.erreur == null &&
        etat.erreurSuite == null &&
        !etat.chargementSuite &&
        etat.encore &&
        page < pagesAgendaAuto) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _controleur.chargerSuite();
      });
    }

    final parJour = <DateTime, List<DemandeReservation>>{};
    for (final d in etat.elements) {
      parJour.putIfAbsent(_jour(d.debut), () => []).add(d);
    }
    for (final liste in parJour.values) {
      liste.sort((a, b) => a.debut.compareTo(b.debut));
    }
    final premier = parJour.keys.isEmpty
        ? null
        : (parJour.keys.toList()..sort()).first;
    final selection = _choisi ?? premier ?? aujourdhui;
    final jours = [
      for (var i = 0; i < _joursAffiches; i++)
        DateTime.utc(aujourdhui.year, aujourdhui.month, aujourdhui.day + i),
    ];
    final duJour = parJour[selection] ?? const <DemandeReservation>[];

    Widget corps;
    if (etat.chargement) {
      corps = const _CorpsSquelette();
    } else if (etat.erreur != null) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(
          message: etat.erreur!,
          onReessayer: () => _controleur.charger(),
        ),
      );
    } else {
      corps = _CorpsJour(
        jour: selection,
        rendezVous: duJour,
        etat: etat,
        onSuite: () => _controleur.chargerSuite(),
      );
    }

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => _controleur.charger(),
        edgeOffset: MediaQuery.paddingOf(context).top,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.zero,
          children: [
            _EnTeteAgenda(
              jours: jours,
              selection: selection,
              onChoix: (j) => setState(() => _choisi = j),
            ),
            corps,
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

class _EnTeteAgenda extends StatelessWidget {
  const _EnTeteAgenda({
    required this.jours,
    required this.selection,
    required this.onChoix,
  });

  final List<DateTime> jours;
  final DateTime selection;
  final ValueChanged<DateTime> onChoix;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(16, hautEcran(context) + 16, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Mon',
                  style: theme.textTheme.headlineMedium?.copyWith(
                    fontSize: DimensionsSopi.tailleTitreEcran,
                    height:
                        DimensionsSopi.interligneTitreEcran /
                        DimensionsSopi.tailleTitreEcran,
                    fontWeight: FontWeight.w400,
                  ),
                ),
                Text(
                  'agenda',
                  style: theme.textTheme.headlineMedium?.copyWith(
                    fontSize: DimensionsSopi.tailleTitreEcran,
                    height:
                        DimensionsSopi.interligneTitreEcran /
                        DimensionsSopi.tailleTitreEcran,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 38),
          Row(
            children: [
              for (var i = 0; i < jours.length; i++) ...[
                if (i > 0) const SizedBox(width: 8),
                Expanded(
                  child: _PuceJour(
                    jour: jours[i],
                    actif: jours[i] == selection,
                    onTap: () => onChoix(jours[i]),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _PuceJour extends StatelessWidget {
  const _PuceJour({
    required this.jour,
    required this.actif,
    required this.onTap,
  });

  final DateTime jour;
  final bool actif;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final nom = formaterJourSemaine(jour);
    return Semantics(
      button: true,
      selected: actif,
      label: '$nom ${jour.day}',
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: actif ? CouleursSopi.bleu : CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(22),
          boxShadow: actif ? null : OmbresSopi.carte,
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(22),
          onTap: onTap,
          child: SizedBox(
            height: 76,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  nom,
                  style: theme.textTheme.labelSmall?.copyWith(
                    fontSize: 11,
                    color: actif
                        ? CouleursSopi.blanc.withValues(alpha: 0.85)
                        : CouleursSopi.texteSecondaire,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${jour.day}',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: actif ? CouleursSopi.blanc : CouleursSopi.texte,
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

class _CorpsSquelette extends StatelessWidget {
  const _CorpsSquelette();

  @override
  Widget build(BuildContext context) {
    Widget ligne() => Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(24),
        boxShadow: OmbresSopi.carte,
      ),
      child: const Row(
        children: [
          Squelette(largeur: 48, hauteur: 32),
          SizedBox(width: 20),
          Expanded(child: Squelette(largeur: 160, hauteur: 32)),
        ],
      ),
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 42, 16, 0),
      child: Column(children: [ligne(), const SizedBox(height: 10), ligne()]),
    );
  }
}

class _CorpsJour extends StatelessWidget {
  const _CorpsJour({
    required this.jour,
    required this.rendezVous,
    required this.etat,
    required this.onSuite,
  });

  final DateTime jour;
  final List<DemandeReservation> rendezVous;
  final EtatDemandes etat;
  final VoidCallback onSuite;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final n = rendezVous.length;
    final nombre = n == 0 ? 'Aucun rendez-vous' : '$n rendez-vous';
    final page = etat.pagination?.page ?? 1;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 42, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Text(
              '${_titreJour(jour)} · $nombre',
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 13,
                color: CouleursSopi.texteSecondaire,
              ),
            ),
          ),
          const SizedBox(height: 14),
          for (var i = 0; i < rendezVous.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Apparition.indexee(
                index: i,
                child: LigneRendezVous(demande: rendezVous[i]),
              ),
            ),
          if (etat.chargementSuite)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Center(
                child: SizedBox(
                  width: 28,
                  height: 28,
                  child: CircularProgressIndicator(strokeWidth: 3),
                ),
              ),
            ),
          if (etat.erreurSuite != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                children: [
                  Text(
                    etat.erreurSuite!,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 8),
                  BoutonCompact(
                    libelle: 'Réessayer',
                    ton: TonBoutonCompact.clair,
                    onPressed: onSuite,
                  ),
                ],
              ),
            )
          else if (etat.encore &&
              !etat.chargementSuite &&
              page >= pagesAgendaAuto)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Center(
                child: BoutonCompact(
                  libelle: 'Charger plus',
                  ton: TonBoutonCompact.contour,
                  onPressed: onSuite,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Ligne d'un rendez-vous : heure et durée, trait de couleur, client, prestation et prix, statut.
class LigneRendezVous extends StatelessWidget {
  const LigneRendezVous({super.key, required this.demande});

  final DemandeReservation demande;

  @override
  Widget build(BuildContext context) {
    final d = demande;
    final theme = Theme.of(context);
    final confirme =
        d.statut == StatutReservation.confirmee ||
        d.statut == StatutReservation.enCours;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(24),
        boxShadow: OmbresSopi.carte,
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(24),
        onTap: () => context.push(Chemins.reservationPrestataire(d.id)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  width: 56,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        formaterHeure(d.debut),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        formaterDuree(d.dureeMinutes),
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 11,
                          color: CouleursSopi.texteSecondaire,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Container(
                  width: 3,
                  decoration: BoxDecoration(
                    color: confirme
                        ? CouleursSopi.bleu
                        : CouleursSopi.iconeInactive,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        d.clientAffiche,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        '${d.prestationNom} · ${formaterArgent(d.prix)}',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 13,
                          color: CouleursSopi.texteSecondaire,
                        ),
                      ),
                      const SizedBox(height: 6),
                      BadgeStatutReservation(statut: d.statut),
                    ],
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

class BadgeStatutReservation extends StatelessWidget {
  const BadgeStatutReservation({super.key, required this.statut});

  final StatutReservation statut;

  @override
  Widget build(BuildContext context) {
    if (statut == StatutReservation.enAttente) {
      // Demande pas encore traitée : elle figure aussi dans l'agenda, mais il reste à répondre.
      return Container(
        height: 24,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: CouleursSopi.ambrePastel,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          'À traiter',
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: CouleursSopi.ambreProfond,
          ),
        ),
      );
    }
    final (libelle, ton) = switch (statut) {
      StatutReservation.confirmee => ('Confirmée', TonStatut.info),
      StatutReservation.acceptee => (
        'En attente de paiement',
        TonStatut.ardoise,
      ),
      StatutReservation.enCours => ('En cours', TonStatut.info),
      _ => (statut.libelle, TonStatut.neutre),
    };
    return PastilleStatut(libelle: libelle, ton: ton);
  }
}
