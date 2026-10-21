import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../../core/widgets/etats.dart';
import 'depot_reservation.dart';
import 'formats.dart';
import 'modeles.dart';
import 'widgets/composants.dart';

/// Données de l'accueil client, chargées ensemble.
class AccueilDonnees {
  const AccueilDonnees({
    required this.prenom,
    required this.metiers,
    required this.decouvrir,
    this.prochaine,
    this.metierProchaine,
  });

  final String prenom;
  final List<Metier> metiers;
  final List<PrestataireResume> decouvrir;
  final ReservationAVenir? prochaine;

  /// Nom du métier de la prochaine réservation (déduit du profil du prestataire : la liste des réservations ne le donne pas).
  final String? metierProchaine;
}

/// Nombre de prestataires proposés sous « Prestataires à découvrir ».
const nombreADecouvrir = 3;

final accueilProvider = FutureProvider.autoDispose<AccueilDonnees>((ref) async {
  final depot = ref.watch(depotReservationProvider);
  final (prenom, metiers, page, prochaine) = await (
    depot.prenomClient(),
    depot.metiers(),
    depot.prestataires(
      filtres: const FiltresPrestataires(tri: TriPrestataires.note),
    ),
    depot.prochaineReservation(),
  ).wait;

  String? metierProchaine;
  if (prochaine != null) {
    try {
      final profil = await depot.profil(prochaine.prestataireId);
      for (final m in metiers) {
        if (m.code == profil.metierCode) metierProchaine = m.nom;
      }
    } catch (_) {
      // Le métier est un détail d'affichage : sans lui, la carte montre seulement le prestataire.
    }
  }

  return AccueilDonnees(
    prenom: prenom,
    metiers: metiers,
    decouvrir: page.elements.take(nombreADecouvrir).toList(),
    prochaine: prochaine,
    metierProchaine: metierProchaine,
  );
});

/// Onglet Accueil du client (maquette Main).
class AccueilClientPage extends ConsumerStatefulWidget {
  const AccueilClientPage({super.key});

  @override
  ConsumerState<AccueilClientPage> createState() => _AccueilClientPageState();
}

class _AccueilClientPageState extends ConsumerState<AccueilClientPage>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // À l'ouverture de l'accueil, le compteur est relu.
    Future.microtask(() {
      if (mounted) ref.invalidate(notificationsNonLuesProvider);
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState etat) {
    if (etat == AppLifecycleState.resumed) {
      ref.invalidate(notificationsNonLuesProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final etat = ref.watch(accueilProvider);
    final donnees = etat.asData?.value;

    Future<void> actualiser() async {
      ref.invalidate(accueilProvider);
      try {
        await ref.read(accueilProvider.future);
      } catch (_) {
        // L'erreur est affichée par l'écran.
      }
    }

    Widget corps;
    if (etat.hasError && donnees == null) {
      corps = _CorpsErreur(
        message: ErreurApi.depuis(etat.error!).message,
        onReessayer: () => ref.invalidate(accueilProvider),
      );
    } else {
      corps = SqueletteVersContenu(
        pret: donnees != null,
        squelette: const _CorpsSquelette(),
        contenu: donnees == null
            ? const SizedBox.shrink()
            : _CorpsAccueil(donnees: donnees),
      );
    }

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: actualiser,
        edgeOffset: MediaQuery.paddingOf(context).top,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: _EnteteAccueil(
                prenom: donnees?.prenom,
                chargement: donnees == null && !etat.hasError,
                prochaine: donnees?.prochaine,
                metierProchaine: donnees?.metierProchaine,
              ),
            ),
            SliverToBoxAdapter(child: corps),
            SliverToBoxAdapter(
              child: SizedBox(height: margeBarreOnglets(context)),
            ),
          ],
        ),
      ),
    );
  }
}

class _EnteteAccueil extends ConsumerWidget {
  const _EnteteAccueil({
    required this.prenom,
    required this.chargement,
    required this.prochaine,
    required this.metierProchaine,
  });

  final String? prenom;
  final bool chargement;
  final ReservationAVenir? prochaine;
  final String? metierProchaine;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    final initiale = (prenom ?? '').trim().isEmpty
        ? ''
        : prenom!.trim()[0].toUpperCase();

    return DescenteDepuisLeHaut(
      child: ClipRRect(
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(DimensionsSopi.rayonEntete),
        ),
        child: FondDegrade(
          tacheBasse: true,
          child: Padding(
            padding: EdgeInsets.fromLTRB(20, haut + 12, 20, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _PilulePleine(
                      libelle: 'Menu',
                      icone: Icons.menu_rounded,
                      onTap: () => context.go(Chemins.profil),
                    ),
                    Row(
                      children: [
                        _RondBlanc(
                          libelle: 'Notifications',
                          icone: Icons.notifications_none_rounded,
                          pastille:
                              (ref.watch(notificationsNonLuesProvider).value ??
                                  0) >
                              0,
                          onTap: () => context.push(Chemins.notifications),
                        ),
                        const SizedBox(width: 10),
                        Semantics(
                          button: true,
                          label: 'Mon profil',
                          child: GestureDetector(
                            onTap: () => context.go(Chemins.profil),
                            child: Container(
                              width: 44,
                              height: 44,
                              alignment: Alignment.center,
                              decoration: const BoxDecoration(
                                color: CouleursSopi.bleu,
                                shape: BoxShape.circle,
                              ),
                              child: Text(
                                initiale,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  color: CouleursSopi.blanc,
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.only(left: 4),
                        child: Apparition(
                          delai: apresEntete(0),
                          child: chargement
                              ? const Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Squelette(largeur: 120, hauteur: 28),
                                    SizedBox(height: 8),
                                    Squelette(largeur: 170, hauteur: 28),
                                  ],
                                )
                              : Text.rich(
                                  TextSpan(
                                    text: 'Bonjour\n',
                                    children: [
                                      TextSpan(
                                        text: prenom == null || prenom!.isEmpty
                                            ? '!'
                                            : '$prenom !',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ],
                                  ),
                                  style: theme.textTheme.headlineMedium
                                      ?.copyWith(
                                        fontSize:
                                            DimensionsSopi.tailleTitreEcran,
                                        height:
                                            DimensionsSopi
                                                .interligneTitreEcran /
                                            DimensionsSopi.tailleTitreEcran,
                                        fontWeight: FontWeight.w400,
                                      ),
                                ),
                        ),
                      ),
                    ),
                    _PilulePleine(
                      libelle: 'Rechercher',
                      icone: Icons.search_rounded,
                      onTap: () => context.push(Chemins.prestataires),
                    ),
                  ],
                ),
                if (prochaine != null) ...[
                  const SizedBox(height: 26),
                  ApparitionApresEntete(
                    rang: 1,
                    child: _CarteProchaine(
                      reservation: prochaine!,
                      metier: metierProchaine,
                      maintenant: ref.read(horlogeProvider)(),
                    ),
                  ),
                ] else
                  const SizedBox(height: 12),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PilulePleine extends StatelessWidget {
  const _PilulePleine({
    required this.libelle,
    required this.icone,
    required this.onTap,
  });

  final String libelle;
  final IconData icone;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: libelle,
      child: Material(
        color: CouleursSopi.texte,
        borderRadius: BorderRadius.circular(22),
        child: InkWell(
          borderRadius: BorderRadius.circular(22),
          onTap: onTap,
          child: SizedBox(
            width: DimensionsSopi.largeurRetour,
            height: DimensionsSopi.hauteurRetour,
            child: Icon(icone, size: 22, color: CouleursSopi.blanc),
          ),
        ),
      ),
    );
  }
}

class _RondBlanc extends StatelessWidget {
  const _RondBlanc({
    required this.libelle,
    required this.icone,
    required this.onTap,
    this.pastille = false,
  });

  final String libelle;
  final IconData icone;
  final VoidCallback onTap;

  /// Petit point bleu : des notifications attendent d'être lues.
  final bool pastille;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: libelle,
      child: Material(
        color: CouleursSopi.blanc,
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: SizedBox(
            width: 44,
            height: 44,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Icon(icone, size: 20, color: CouleursSopi.texte),
                if (pastille)
                  Positioned(
                    top: 11,
                    right: 12,
                    child: Container(
                      key: const ValueKey('pastille-notifications'),
                      width: 9,
                      height: 9,
                      decoration: BoxDecoration(
                        color: CouleursSopi.bleu,
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: CouleursSopi.blanc,
                          width: 1.5,
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

class _CarteProchaine extends StatelessWidget {
  const _CarteProchaine({
    required this.reservation,
    required this.metier,
    required this.maintenant,
  });

  final ReservationAVenir reservation;
  final String? metier;
  final DateTime maintenant;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final detail = [?metier, reservation.prestataireNom].join(' · ');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
      decoration: BoxDecoration(
        color: CouleursSopi.bleu,
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Prochaine réservation',
            style: theme.textTheme.labelMedium?.copyWith(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: CouleursSopi.blanc.withValues(alpha: 0.85),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      libelleProchaineReservation(
                        reservation.debut,
                        maintenant,
                      ),
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 22,
                        height: 28 / 22,
                        fontWeight: FontWeight.w700,
                        color: CouleursSopi.blanc,
                      ),
                    ),
                    Text(
                      detail,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 13,
                        color: CouleursSopi.blanc.withValues(alpha: 0.9),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              BoutonCompact(
                libelle: 'Voir',
                ton: TonBoutonCompact.clair,
                hauteur: 44,
                taille: 14,
                onPressed: () =>
                    context.push(Chemins.detailReservation(reservation.id)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CorpsAccueil extends StatelessWidget {
  const _CorpsAccueil({required this.donnees});

  final AccueilDonnees donnees;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ApparitionApresEntete(
            rang: 2,
            child: _RangeeMetiers(metiers: donnees.metiers),
          ),
          const SizedBox(height: 20),
          ApparitionApresEntete(
            rang: 3,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: TitreSection(
                titre: 'Prestataires à découvrir',
                lien: 'Tout voir',
                onLien: () => context.push(Chemins.prestataires),
              ),
            ),
          ),
          const SizedBox(height: 10),
          if (donnees.decouvrir.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(child: Text('Aucun prestataire pour le moment.')),
            )
          else
            ApparitionEnCascade(
              delaiInitial: apresEntete(4),
              espacement: 10,
              children: [
                for (final p in donnees.decouvrir)
                  _CarteDecouverte(
                    prestataire: p,
                    metier: _nomMetier(donnees.metiers, p.metierCode),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

String _nomMetier(List<Metier> metiers, String code) {
  for (final m in metiers) {
    if (m.code == code) return m.nom;
  }
  return code;
}

class _RangeeMetiers extends StatelessWidget {
  const _RangeeMetiers({required this.metiers});

  final List<Metier> metiers;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, contraintes) {
        // Trois tuiles tiennent exactement dans la largeur ; au-delà, la rangée défile.
        final largeur = (contraintes.maxWidth - 20) / 3;
        return SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              for (var i = 0; i < metiers.length; i++) ...[
                if (i > 0) const SizedBox(width: 10),
                SizedBox(
                  width: largeur,
                  child: _TuileMetier(metier: metiers[i]),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _TuileMetier extends StatelessWidget {
  const _TuileMetier({required this.metier});

  final Metier metier;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push(
          Uri(
            path: Chemins.prestataires,
            queryParameters: {'metier': metier.code},
          ).toString(),
        ),
        child: Container(
          height: 112,
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Icon(
                iconeMetier(metier.code),
                size: 24,
                color: CouleursSopi.bleu,
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Réserver',
                    style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
                  ),
                  Text(
                    metier.nom,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CarteDecouverte extends StatelessWidget {
  const _CarteDecouverte({required this.prestataire, required this.metier});

  final PrestataireResume prestataire;
  final String metier;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
        side: const BorderSide(color: CouleursSopi.bordure),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push(Chemins.fichePrestataire(prestataire.id)),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              PastilleMetier(code: prestataire.metierCode),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      prestataire.nomEntreprise,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      '$metier · ${prestataire.quartier}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(fontSize: 12),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              NotePrestataire(prestataire: prestataire),
            ],
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
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              for (var i = 0; i < 3; i++) ...[
                if (i > 0) const SizedBox(width: 10),
                const Expanded(child: Squelette(hauteur: 112, rayon: 22)),
              ],
            ],
          ),
          const SizedBox(height: 20),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 4),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Squelette(largeur: 180, hauteur: 17),
            ),
          ),
          const SizedBox(height: 14),
          for (var i = 0; i < 3; i++) ...[
            if (i > 0) const SizedBox(height: 10),
            const SqueletteCartePrestataire(compacte: true),
          ],
        ],
      ),
    );
  }
}

class _CorpsErreur extends StatelessWidget {
  const _CorpsErreur({required this.message, required this.onReessayer});

  final String message;
  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 24),
      child: EtatErreur(message: message, onReessayer: onReessayer),
    );
  }
}
