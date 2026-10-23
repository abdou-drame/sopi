import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/apparition.dart';
import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/theme/disposition.dart';
import '../../core/widgets/boutons.dart';
import '../auth/session.dart';
import '../reservation/depot_reservation.dart';
import '../reservation/widgets/composants.dart';
import 'agenda_page.dart' show LigneRendezVous;
import 'depot_prestataire.dart';
import 'widgets/composants_prestataire.dart';

/// Nombre de demandes montrées sous « Nouvelles demandes » (la liste complète est derrière « Tout voir »).
const nombreNouvellesDemandes = 3;

/// Onglet Accueil du prestataire (maquette Tableau).
class TableauPage extends ConsumerStatefulWidget {
  const TableauPage({super.key});

  @override
  ConsumerState<TableauPage> createState() => _TableauPageState();
}

class _TableauPageState extends ConsumerState<TableauPage>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
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
      ref.invalidate(soldeProvider);
      ref.invalidate(demandesATraiterProvider);
      ref.invalidate(tableauPrestataireProvider);
    }
  }

  Future<void> _actualiser() async {
    ref.invalidate(soldeProvider);
    ref.invalidate(demandesATraiterProvider);
    ref.invalidate(tableauPrestataireProvider);
    ref.invalidate(notificationsNonLuesProvider);
    try {
      await ref.read(demandesATraiterProvider.future);
    } catch (_) {
      // L'erreur est affichée par le bloc concerné.
    }
  }

  @override
  Widget build(BuildContext context) {
    final etat = ref.watch(sessionProvider);
    final prenom = etat is Connecte ? etat.utilisateur.prenom : null;
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _actualiser,
        edgeOffset: MediaQuery.paddingOf(context).top,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(child: _EnteteTableau(prenom: prenom)),
            const SliverToBoxAdapter(child: _CorpsTableau()),
            SliverToBoxAdapter(
              child: SizedBox(height: margeBarreOnglets(context)),
            ),
          ],
        ),
      ),
    );
  }
}

class _EnteteTableau extends ConsumerWidget {
  const _EnteteTableau({required this.prenom});

  final String? prenom;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final haut = hautEcran(context);
    final nom = (prenom ?? '').trim();
    final initiale = nom.isEmpty ? '' : nom[0].toUpperCase();

    return ColoredBox(
      color: CouleursSopi.fond,
      child: Padding(
        padding: EdgeInsets.fromLTRB(20, haut, 20, 0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                PilulePleine(
                  libelle: 'Menu',
                  icone: Icons.menu_rounded,
                  onTap: () => context.go(Chemins.profilPrestataire),
                ),
                Row(
                  children: [
                    RondBlanc(
                      libelle: 'Notifications',
                      icone: Icons.notifications_none_rounded,
                      pastille:
                          (ref.watch(notificationsNonLuesProvider).value ?? 0) >
                          0,
                      onTap: () => context.push(Chemins.notifications),
                    ),
                    const SizedBox(width: 10),
                    Semantics(
                      button: true,
                      label: 'Mon profil',
                      child: GestureDetector(
                        onTap: () => context.go(Chemins.profilPrestataire),
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
            Padding(
              padding: const EdgeInsets.only(left: 4),
              child: Apparition(
                delai: apresEntete(0),
                child: Text.rich(
                  TextSpan(
                    text: 'Bonjour\n',
                    children: [
                      TextSpan(
                        text: nom.isEmpty ? '!' : '$nom !',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                  style: theme.textTheme.headlineMedium?.copyWith(
                    fontSize: DimensionsSopi.tailleTitreEcran,
                    height:
                        DimensionsSopi.interligneTitreEcran /
                        DimensionsSopi.tailleTitreEcran,
                    fontWeight: FontWeight.w400,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 26),
            const ApparitionApresEntete(rang: 1, child: _CarteSolde()),
          ],
        ),
      ),
    );
  }
}

/// Carte bleue du solde disponible ; « Retirer » ouvre la demande de retrait.
class _CarteSolde extends ConsumerWidget {
  const _CarteSolde();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final solde = ref.watch(soldeProvider);

    Widget montant;
    if (solde.hasValue) {
      final texte = formaterArgent(solde.value!.solde);
      final coupure = texte.lastIndexOf(' ');
      montant = Text.rich(
        TextSpan(
          text: texte.substring(0, coupure + 1),
          children: [
            TextSpan(
              text: texte.substring(coupure + 1),
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
          ],
        ),
        style: theme.textTheme.headlineMedium?.copyWith(
          color: CouleursSopi.blanc,
          fontSize: DimensionsSopi.tailleTitreEcran,
          height:
              DimensionsSopi.interligneTitreEcran /
              DimensionsSopi.tailleTitreEcran,
          fontWeight: FontWeight.w800,
        ),
      );
    } else if (solde.hasError) {
      montant = InkWell(
        onTap: () => ref.invalidate(soldeProvider),
        child: Text(
          'Solde indisponible. Toucher pour réessayer.',
          style: theme.textTheme.bodyMedium?.copyWith(
            color: CouleursSopi.blanc,
            fontSize: 13,
          ),
        ),
      );
    } else {
      montant = const Squelette(largeur: 150, hauteur: 32);
    }

    return Container(
      height: 124,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
      decoration: BoxDecoration(
        color: CouleursSopi.bleu,
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            'Solde disponible',
            style: theme.textTheme.bodySmall?.copyWith(
              color: CouleursSopi.blanc.withValues(alpha: 0.85),
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(child: montant),
              const SizedBox(width: 8),
              Semantics(
                button: true,
                label: 'Retirer',
                excludeSemantics: true,
                child: Material(
                  key: const ValueKey('bouton-retirer'),
                  color: CouleursSopi.blanc,
                  borderRadius: BorderRadius.circular(22),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(22),
                    onTap: () => context.push(Chemins.retrait),
                    child: Container(
                      height: 44,
                      padding: const EdgeInsets.symmetric(horizontal: 18),
                      alignment: Alignment.center,
                      child: Text(
                        'Retirer',
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: CouleursSopi.bleu,
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CorpsTableau extends ConsumerWidget {
  const _CorpsTableau();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final demandes = ref.watch(demandesATraiterProvider);
    final page = demandes.asData?.value;
    final tableau = ref.watch(tableauPrestataireProvider);
    final chiffres = tableau.asData?.value;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 36, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ApparitionApresEntete(
            rang: 2,
            child: Row(
              children: [
                Expanded(
                  child: _CarteIndicateur(
                    libelle: 'Demandes à traiter',
                    cleValeur: const ValueKey('compteur-a-traiter'),
                    valeur: page?.pagination.total,
                    erreur: demandes.hasError,
                    couleur: CouleursSopi.bleu,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _CarteIndicateur(
                    libelle: 'Rendez-vous aujourd’hui',
                    cleValeur: const ValueKey('compteur-rdv-aujourdhui'),
                    valeur: chiffres?.rendezVousAujourdhui,
                    erreur: tableau.hasError,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _CarteIndicateur(
                    libelle: 'Terminées ce mois',
                    cleValeur: const ValueKey('compteur-terminees-mois'),
                    valeur: chiffres?.terminesCeMois,
                    erreur: tableau.hasError,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: ApparitionApresEntete(
              rang: 3,
              child: TitreSection(
                titre: 'Planning du jour',
                lien: 'Agenda',
                onLien: () => context.go(Chemins.agenda),
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (tableau.hasError && chiffres == null)
            _BlocErreur(
              message: ErreurApi.depuis(tableau.error!).message,
              onReessayer: () => ref.invalidate(tableauPrestataireProvider),
            )
          else if (chiffres == null)
            const Squelette(hauteur: 88, rayon: 24)
          else if (chiffres.planningDuJour.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text(
                'Aucun rendez-vous aujourd’hui',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
            )
          else
            for (final (i, d) in chiffres.planningDuJour.indexed)
              Padding(
                padding: EdgeInsets.only(top: i == 0 ? 0 : 10),
                child: LigneRendezVous(demande: d),
              ),
          const SizedBox(height: 24),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: ApparitionApresEntete(
              rang: 4,
              child: TitreSection(
                titre: 'Nouvelles demandes',
                lien: 'Tout voir',
                onLien: () => context.push(Chemins.reservationsPrestataire),
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (demandes.hasError && page == null)
            _BlocErreur(
              message: ErreurApi.depuis(demandes.error!).message,
              onReessayer: () => ref.invalidate(demandesATraiterProvider),
            )
          else if (page == null)
            const _SqueletteDemandes()
          else if (page.elements.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 24),
              child: Text(
                'Aucune demande à traiter pour le moment.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
            )
          else
            for (final (i, d)
                in page.elements.take(nombreNouvellesDemandes).indexed)
              Padding(
                padding: EdgeInsets.only(top: i == 0 ? 0 : 10),
                child: ApparitionApresEntete(
                  rang: 5 + i,
                  child: CarteNouvelleDemande(demande: d),
                ),
              ),
        ],
      ),
    );
  }
}

class _SqueletteDemandes extends StatelessWidget {
  const _SqueletteDemandes();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (var i = 0; i < 2; i++)
          Padding(
            padding: EdgeInsets.only(top: i == 0 ? 0 : 10),
            child: const Squelette(hauteur: 118, rayon: 24),
          ),
      ],
    );
  }
}

class _BlocErreur extends StatelessWidget {
  const _BlocErreur({required this.message, required this.onReessayer});

  final String message;
  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          message,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 12),
        BoutonCompact(
          libelle: 'Réessayer',
          ton: TonBoutonCompact.contour,
          onPressed: onReessayer,
        ),
      ],
    );
  }
}

/// Carte d'indicateur : libellé en haut, valeur en bas (squelette pendant le chargement, « — » en cas d'erreur).
class _CarteIndicateur extends StatelessWidget {
  const _CarteIndicateur({
    required this.libelle,
    required this.cleValeur,
    required this.valeur,
    required this.erreur,
    this.couleur = CouleursSopi.texte,
  });

  final String libelle;
  final Key cleValeur;
  final int? valeur;
  final bool erreur;
  final Color couleur;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = theme.textTheme.headlineMedium?.copyWith(
      fontSize: 26,
      fontWeight: FontWeight.w800,
    );
    return Container(
      height: 96,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(22),
        boxShadow: OmbresSopi.carte,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            libelle,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 11,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
          if (valeur != null)
            Text(
              '$valeur',
              key: cleValeur,
              style: style?.copyWith(color: couleur),
            )
          else if (erreur)
            Text(
              '—',
              style: style?.copyWith(color: CouleursSopi.texteSecondaire),
            )
          else
            const Squelette(largeur: 40, hauteur: 26),
        ],
      ),
    );
  }
}
