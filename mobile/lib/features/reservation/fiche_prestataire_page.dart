import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import 'depot_reservation.dart';
import 'formats.dart';
import 'modeles.dart';
import 'widgets/composants.dart';
import 'widgets/photo_prestataire.dart';

final profilPrestataireProvider = FutureProvider.autoDispose
    .family<ProfilPrestataire, String>(
      retry: sansRelance,
      (ref, id) => ref.watch(depotReservationProvider).profil(id),
    );

/// Premiers avis affichés sur la fiche (la liste complète est derrière « Tout voir »).
final avisRecentsProvider = FutureProvider.autoDispose.family<PageAvis, String>(
  retry: sansRelance,
  (ref, id) => ref.watch(depotReservationProvider).avis(id),
);

/// Nombre d'avis montrés sur la fiche.
const avisSurLaFiche = 3;

/// Cinq étoiles bleues, pleines jusqu'à [note].
class Etoiles extends StatelessWidget {
  const Etoiles({super.key, required this.note, this.taille = 14});

  final int note;
  final double taille;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$note sur 5',
      child: ExcludeSemantics(
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (var i = 1; i <= 5; i++)
              Icon(
                i <= note ? Icons.star_rounded : Icons.star_outline_rounded,
                size: taille,
                color: CouleursSopi.bleu,
              ),
          ],
        ),
      ),
    );
  }
}

/// Une ligne d'avis (carte blanche : auteur, étoiles, commentaire).
class CarteAvis extends StatelessWidget {
  const CarteAvis({super.key, required this.avis});

  final Avis avis;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  avis.auteur,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Etoiles(note: avis.note),
            ],
          ),
          if (avis.commentaire != null && avis.commentaire!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              avis.commentaire!,
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 13,
                height: 19 / 13,
                color: CouleursSopi.texteSecondaire,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class FichePrestatairePage extends ConsumerStatefulWidget {
  const FichePrestatairePage({super.key, required this.prestataireId});

  final String prestataireId;

  @override
  ConsumerState<FichePrestatairePage> createState() =>
      _FichePrestatairePageState();
}

class _FichePrestatairePageState extends ConsumerState<FichePrestatairePage> {
  String? _prestationId;

  Future<void> _actualiser() async {
    ref.invalidate(profilPrestataireProvider(widget.prestataireId));
    ref.invalidate(avisRecentsProvider(widget.prestataireId));
    try {
      await ref.read(profilPrestataireProvider(widget.prestataireId).future);
    } catch (_) {
      // L'erreur est affichée par l'écran.
    }
  }

  @override
  Widget build(BuildContext context) {
    final profil = ref.watch(profilPrestataireProvider(widget.prestataireId));
    final donnees = profil.asData?.value;
    final prestation = donnees?.prestations
        .where((p) => p.id == _prestationId)
        .firstOrNull;
    final metiers = ref.watch(metiersProvider).asData?.value ?? const [];
    final nomMetier = donnees == null
        ? null
        : metiers
                  .where((m) => m.code == donnees.metierCode)
                  .map((m) => m.nom)
                  .firstOrNull ??
              donnees.metierCode;
    final bas = MediaQuery.paddingOf(context).bottom;

    Widget corps;
    if (profil.hasError && donnees == null) {
      corps = SizedBox(
        height: 360,
        child: EtatErreur(
          message: ErreurApi.depuis(profil.error!).message,
          onReessayer: () =>
              ref.invalidate(profilPrestataireProvider(widget.prestataireId)),
        ),
      );
    } else {
      corps = SqueletteVersContenu(
        pret: donnees != null,
        squelette: const _CorpsSquelette(),
        contenu: donnees == null
            ? const SizedBox.shrink()
            : _CorpsFiche(
                profil: donnees,
                prestationId: _prestationId,
                onChoisir: (id) => setState(() => _prestationId = id),
              ),
      );
    }

    return Scaffold(
      body: Stack(
        children: [
          RefreshIndicator(
            onRefresh: _actualiser,
            edgeOffset: MediaQuery.paddingOf(context).top,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: _Entete(
                    profil: donnees,
                    metier: nomMetier,
                    chargement: donnees == null && !profil.hasError,
                  ),
                ),
                SliverToBoxAdapter(child: corps),
                SliverToBoxAdapter(child: SizedBox(height: 64 + 20 + bas + 24)),
              ],
            ),
          ),
          if (donnees != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 20 + bas,
              child: ApparitionApresEntete(
                rang: 4,
                child: _BarreChoix(
                  prestation: prestation,
                  onContinuer: prestation == null
                      ? null
                      : () => context.push(
                          '${Chemins.creneau(widget.prestataireId)}'
                          '?prestation=${prestation.id}',
                        ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _Entete extends StatelessWidget {
  const _Entete({
    required this.profil,
    required this.metier,
    required this.chargement,
  });

  final ProfilPrestataire? profil;
  final String? metier;
  final bool chargement;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = MediaQuery.paddingOf(context).top;
    return DescenteDepuisLeHaut(
      child: ClipRRect(
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(DimensionsSopi.rayonEntete),
        ),
        child: FondDegrade(
          child: Padding(
            padding: EdgeInsets.fromLTRB(20, haut + 24, 20, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    BoutonRetour(onPressed: () => context.pop()),
                    Expanded(
                      child: Text(
                        'Fiche prestataire',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 64),
                  ],
                ),
                const SizedBox(height: 20),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Row(
                    children: [
                      Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          color: CouleursSopi.bleu,
                          borderRadius: BorderRadius.circular(24),
                        ),
                        child: Icon(
                          iconeMetier(profil?.metierCode ?? ''),
                          size: 34,
                          color: CouleursSopi.blanc,
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: profil == null
                            ? const Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Squelette(largeur: 160, hauteur: 24),
                                  SizedBox(height: 8),
                                  Squelette(largeur: 110, hauteur: 13),
                                ],
                              )
                            : Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    profil!.nomEntreprise,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: theme.textTheme.headlineSmall
                                        ?.copyWith(
                                          fontSize: 24,
                                          height: 30 / 24,
                                          fontWeight: FontWeight.w800,
                                        ),
                                  ),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: [
                                      const Icon(
                                        Icons.location_on_outlined,
                                        size: 13,
                                        color: CouleursSopi.texteSecondaire,
                                      ),
                                      const SizedBox(width: 4),
                                      Expanded(
                                        child: Text(
                                          '${profil!.quartier} · $metier',
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: theme.textTheme.bodySmall
                                              ?.copyWith(
                                                fontSize: 13,
                                                color: CouleursSopi
                                                    .texteSecondaire,
                                              ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 22),
                _BarreChiffres(profil: profil),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Barre marine : note moyenne et nombre d'avis.
class _BarreChiffres extends StatelessWidget {
  const _BarreChiffres({required this.profil});

  final ProfilPrestataire? profil;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget cellule(String valeur, String libelle) => Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          valeur,
          style: theme.textTheme.titleLarge?.copyWith(
            fontSize: 22,
            fontWeight: FontWeight.w800,
            color: CouleursSopi.blanc,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          libelle,
          style: theme.textTheme.bodySmall?.copyWith(
            fontSize: 11,
            color: CouleursSopi.blanc.withValues(alpha: 0.7),
          ),
        ),
      ],
    );
    final p = profil;
    return Container(
      height: 92,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      decoration: BoxDecoration(
        color: CouleursSopi.marine,
        borderRadius: BorderRadius.circular(26),
      ),
      child: p == null
          ? const Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                Squelette(largeur: 60, hauteur: 30),
                Squelette(largeur: 60, hauteur: 30),
              ],
            )
          : Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                cellule(
                  p.noteMoyenne == null ? '—' : formaterNote(p.noteMoyenne!),
                  'Note',
                ),
                Container(
                  width: 1,
                  height: 40,
                  color: CouleursSopi.blanc.withValues(alpha: 0.18),
                ),
                cellule('${p.nombreAvis}', 'Avis'),
              ],
            ),
    );
  }
}

class _CorpsSquelette extends StatelessWidget {
  const _CorpsSquelette();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.fromLTRB(16, 24, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: EdgeInsets.only(left: 4),
            child: Squelette(largeur: 90, hauteur: 18),
          ),
          SizedBox(height: 14),
          Squelette(hauteur: 70, rayon: 22),
          SizedBox(height: 10),
          Squelette(hauteur: 70, rayon: 22),
          SizedBox(height: 10),
          Squelette(hauteur: 70, rayon: 22),
          SizedBox(height: 24),
          Padding(
            padding: EdgeInsets.only(left: 4),
            child: Squelette(largeur: 120, hauteur: 18),
          ),
          SizedBox(height: 14),
          Squelette(hauteur: 90, rayon: 22),
        ],
      ),
    );
  }
}

class _CorpsFiche extends ConsumerWidget {
  const _CorpsFiche({
    required this.profil,
    required this.prestationId,
    required this.onChoisir,
  });

  final ProfilPrestataire profil;
  final String? prestationId;
  final ValueChanged<String> onChoisir;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final avis = ref.watch(avisRecentsProvider(profil.id));
    var rang = 0;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 22, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (profil.photos.isNotEmpty) ...[
            ApparitionApresEntete(
              rang: rang++,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(left: 4),
                    child: Text(
                      'Photos',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: 17,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    height: 120,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: profil.photos.length,
                      separatorBuilder: (_, _) => const SizedBox(width: 10),
                      itemBuilder: (_, i) => PhotoReseau(
                        url: profil.photos[i].url,
                        largeur: 160,
                        hauteur: 120,
                      ),
                    ),
                  ),
                  const SizedBox(height: 22),
                ],
              ),
            ),
          ],
          ApparitionApresEntete(
            rang: rang++,
            child: Padding(
              padding: const EdgeInsets.only(left: 4),
              child: Text(
                'Services',
                style: theme.textTheme.titleMedium?.copyWith(fontSize: 17),
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (profil.prestations.isEmpty)
            const EtatVide(
              message: 'Ce prestataire ne propose pas encore de service.',
            )
          else
            for (final p in profil.prestations)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: ApparitionApresEntete(
                  rang: rang++,
                  child: _CarteService(
                    prestation: p,
                    choisie: p.id == prestationId,
                    onTap: () => onChoisir(p.id),
                  ),
                ),
              ),
          const SizedBox(height: 14),
          ApparitionApresEntete(
            rang: rang++,
            child: TitreSection(
              titre: 'Avis récents',
              lien: avis.asData?.value.elements.isNotEmpty == true
                  ? 'Tout voir'
                  : null,
              onLien: () => context.push(Chemins.avisPrestataire(profil.id)),
            ),
          ),
          const SizedBox(height: 12),
          avis.when(
            loading: () => const Squelette(hauteur: 90, rayon: 22),
            error: (e, _) => EtatErreur(
              message: ErreurApi.depuis(e).message,
              onReessayer: () => ref.invalidate(avisRecentsProvider(profil.id)),
            ),
            data: (page) => page.elements.isEmpty
                ? Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 4,
                      vertical: 8,
                    ),
                    child: Text(
                      'Aucun avis pour le moment.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: CouleursSopi.texteDiscret,
                      ),
                    ),
                  )
                : Column(
                    children: [
                      for (final a in page.elements.take(avisSurLaFiche))
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: CarteAvis(avis: a),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class _CarteService extends StatelessWidget {
  const _CarteService({
    required this.prestation,
    required this.choisie,
    required this.onTap,
  });

  final Prestation prestation;
  final bool choisie;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      button: true,
      selected: choisie,
      child: Material(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(22),
        child: InkWell(
          borderRadius: BorderRadius.circular(22),
          onTap: onTap,
          child: Container(
            // Bordure de 2 px quand le service est choisi : on retire 1 px de marge pour ne pas faire sauter la mise en page.
            padding: EdgeInsets.symmetric(
              horizontal: choisie ? 15 : 16,
              vertical: choisie ? 13 : 14,
            ),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(22),
              border: Border.all(
                color: choisie ? CouleursSopi.bleu : CouleursSopi.bordure,
                width: choisie ? 2 : 1,
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        prestation.nom,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Durée : ${formaterDuree(prestation.dureeMinutes)}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 12,
                          color: CouleursSopi.texteDiscret,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerRight,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          formaterArgent(prestation.prix),
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Acompte ${formaterArgent(prestation.montantAcompte)}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: CouleursSopi.bleu,
                          ),
                        ),
                      ],
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

/// Barre bleue du bas : service choisi + prix, et bouton « Choisir un créneau » (inactif sans service).
class _BarreChoix extends StatelessWidget {
  const _BarreChoix({required this.prestation, required this.onContinuer});

  final Prestation? prestation;
  final VoidCallback? onContinuer;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final p = prestation;
    return Container(
      height: 64,
      padding: const EdgeInsets.fromLTRB(22, 0, 10, 0),
      decoration: BoxDecoration(
        color: CouleursSopi.bleu,
        borderRadius: BorderRadius.circular(32),
      ),
      child: Row(
        children: [
          Expanded(
            child: p == null
                ? Text(
                    'Choisissez un service',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: CouleursSopi.blanc,
                    ),
                  )
                : Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        p.nom,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 11,
                          color: CouleursSopi.blanc.withValues(alpha: 0.85),
                        ),
                      ),
                      Text(
                        formaterArgent(p.prix),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: CouleursSopi.blanc,
                        ),
                      ),
                    ],
                  ),
          ),
          const SizedBox(width: 8),
          Flexible(
            flex: 2,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: BoutonCompact(
                libelle: 'Choisir un créneau',
                ton: TonBoutonCompact.clair,
                hauteur: 46,
                taille: 15,
                margeHorizontale: 24,
                onPressed: onContinuer,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
