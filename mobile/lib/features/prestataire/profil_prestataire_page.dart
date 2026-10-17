import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../reservation/formats.dart' show formaterNote;
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../auth/session.dart';
import '../auth/utilisateur.dart';
import 'depot_prestataire.dart';

/// Onglet « Profil » du prestataire (maquette PrestaProfil). L'identité vient du bloc `prestataire` de `/me`.
/// Les signalements reçus n'ont pas de pastille de nombre (décision de la maquette corrigée).
class ProfilPrestaPage extends ConsumerWidget {
  const ProfilPrestaPage({super.key});

  Future<void> _deconnecter(BuildContext context, WidgetRef ref) async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Se déconnecter ?'),
        content: const Text('Vous devrez vous reconnecter pour continuer.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Se déconnecter'),
          ),
        ],
      ),
    );
    if (confirme == true) {
      await ref.read(sessionProvider.notifier).deconnecter();
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final etat = ref.watch(sessionProvider);
    final Utilisateur? u = etat is Connecte ? etat.utilisateur : null;
    final identite = u?.prestataire;
    final entreprise = (identite?.nomEntreprise ?? '').isNotEmpty
        ? identite!.nomEntreprise
        : (u?.nomComplet ?? '');
    final sousTitre = [
      identite?.metier,
      identite?.quartier,
    ].whereType<String>().where((s) => s.isNotEmpty).join(' · ');
    final initiale = entreprise.isNotEmpty
        ? entreprise.substring(0, 1).toUpperCase()
        : '?';
    final note = ref.watch(noteMoyennePrestataireProvider).value;

    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          Stack(
            children: [
              const Positioned.fill(
                child: DescenteDepuisLeHaut(
                  child: ClipRRect(
                    borderRadius: BorderRadius.vertical(
                      bottom: Radius.circular(DimensionsSopi.rayonEntete),
                    ),
                    child: FondDegrade(child: SizedBox.expand()),
                  ),
                ),
              ),
              SizedBox(
                width: double.infinity,
                child: Padding(
                  padding: EdgeInsets.fromLTRB(
                    24,
                    MediaQuery.paddingOf(context).top + 36,
                    24,
                    32,
                  ),
                  child: Column(
                    children: [
                      Container(
                        width: 80,
                        height: 80,
                        alignment: Alignment.center,
                        decoration: const BoxDecoration(
                          color: CouleursSopi.bleu,
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          initiale,
                          style: theme.textTheme.headlineMedium?.copyWith(
                            fontSize: 32,
                            fontWeight: FontWeight.w800,
                            color: CouleursSopi.blanc,
                          ),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        entreprise,
                        textAlign: TextAlign.center,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontSize: 21,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (sousTitre.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          sousTitre,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontSize: 13,
                            color: CouleursSopi.texteSecondaire,
                          ),
                        ),
                      ],
                      if (u?.statutCompte == 'actif') ...[
                        const SizedBox(height: 6),
                        Container(
                          key: const ValueKey('pastille-compte'),
                          height: 26,
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: CouleursSopi.succesFond,
                            borderRadius: BorderRadius.circular(13),
                          ),
                          child: Text(
                            'Compte actif',
                            style: theme.textTheme.labelMedium?.copyWith(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: CouleursSopi.succesTexte,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
          _Groupe(
            lignes: [
              _Ligne(
                icone: Icons.diamond_outlined,
                libelle: 'Mes services et tarifs',
                onTap: () => context.push(Chemins.services),
              ),
              _Ligne(
                icone: Icons.schedule_rounded,
                libelle: 'Mes disponibilités',
                onTap: () => context.push(Chemins.disponibilites),
              ),
              _Ligne(
                icone: Icons.photo_outlined,
                libelle: "Photos de l'entreprise",
                onTap: () => context.push(Chemins.photos),
              ),
              _Ligne(
                icone: Icons.star_border_rounded,
                libelle: 'Avis reçus',
                complement: note == null ? null : formaterNote(note),
                onTap: () => context.push(Chemins.avisRecus),
              ),
              _Ligne(
                icone: Icons.warning_amber_rounded,
                libelle: 'Signalements reçus',
                onTap: () => context.push(Chemins.litigesRecus),
              ),
            ],
          ),
          _Groupe(
            lignes: [
              _Ligne(
                icone: Icons.person_outline_rounded,
                libelle: 'Modifier mon profil',
                onTap: () => context.push(Chemins.profilModifier),
              ),
              _Ligne(
                icone: Icons.lock_outline_rounded,
                libelle: 'Changer le mot de passe',
                onTap: () => context.push(Chemins.motDePasseChanger),
              ),
              _Ligne(
                icone: Icons.notifications_none_rounded,
                libelle: 'Notifications',
                onTap: () => context.push(Chemins.notifications),
              ),
              _Ligne(
                icone: Icons.help_outline_rounded,
                libelle: 'Aide et support',
                onTap: () => context.push(Chemins.aide),
              ),
              _Ligne(
                icone: Icons.logout_rounded,
                libelle: 'Se déconnecter',
                danger: true,
                onTap: () => _deconnecter(context, ref),
              ),
            ],
          ),
          SizedBox(height: DimensionsSopi.hauteurBarreOnglets + 48),
        ],
      ),
    );
  }
}

/// Carte blanche à bord arrondi (28) qui regroupe des lignes de 50 px.
class _Groupe extends StatelessWidget {
  const _Groupe({required this.lignes});

  final List<_Ligne> lignes;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        DimensionsSopi.margeCarte,
        12,
        DimensionsSopi.margeCarte,
        0,
      ),
      child: Container(
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: CouleursSopi.bordure),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          children: [
            for (var i = 0; i < lignes.length; i++)
              lignes[i].avecSeparateur(i < lignes.length - 1),
          ],
        ),
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  const _Ligne({
    required this.icone,
    required this.libelle,
    required this.onTap,
    this.complement,
    this.danger = false,
    this.separateur = false,
  });

  final IconData icone;
  final String libelle;
  final VoidCallback onTap;
  final String? complement;
  final bool danger;
  final bool separateur;

  _Ligne avecSeparateur(bool valeur) => _Ligne(
    icone: icone,
    libelle: libelle,
    onTap: onTap,
    complement: complement,
    danger: danger,
    separateur: valeur,
  );

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final couleur = danger ? CouleursSopi.erreurTexte : CouleursSopi.marine;
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 50,
        padding: const EdgeInsets.symmetric(horizontal: 18),
        decoration: BoxDecoration(
          border: separateur
              ? const Border(
                  bottom: BorderSide(color: CouleursSopi.separateurLigne),
                )
              : null,
        ),
        child: Row(
          children: [
            Icon(
              icone,
              size: 20,
              color: danger ? CouleursSopi.erreurTexte : CouleursSopi.bleu,
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                libelle,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: couleur,
                ),
              ),
            ),
            if (complement != null)
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: Text(
                  complement!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12,
                    color: CouleursSopi.texteDiscret,
                  ),
                ),
              ),
            if (!danger)
              const Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: CouleursSopi.desactive,
              ),
          ],
        ),
      ),
    );
  }
}
