import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_ecran.dart';
import '../auth/session.dart';
import '../auth/utilisateur.dart';
import '../../core/format/formateurs.dart';

/// Version de l'application (package_info_plus). Surchargeable dans les tests.
final versionAppProvider = FutureProvider<String>((ref) async {
  final info = await PackageInfo.fromPlatform();
  return info.version;
});

/// Onglet « Profil » du client (maquette Profil).
class ProfilClientPage extends ConsumerWidget {
  const ProfilClientPage({super.key});

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
    final version = ref.watch(versionAppProvider).value;
    final prenom = u?.prenom ?? '';
    final nomFamille = u?.nom ?? '';
    final telephone = u?.telephone ?? '';
    final email = u?.email ?? '';
    final initiale = prenom.isNotEmpty
        ? prenom.substring(0, 1).toUpperCase()
        : (nomFamille.isNotEmpty
              ? nomFamille.substring(0, 1).toUpperCase()
              : '?');
    final contact = [
      if (telephone.isNotEmpty) formaterTelephone(telephone),
      if (email.isNotEmpty) email,
    ].join(' · ');

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
                    MediaQuery.paddingOf(context).top + 48,
                    24,
                    36,
                  ),
                  child: Column(
                    children: [
                      Container(
                        width: 96,
                        height: 96,
                        alignment: Alignment.center,
                        decoration: const BoxDecoration(
                          color: CouleursSopi.bleu,
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          initiale,
                          style: theme.textTheme.headlineMedium?.copyWith(
                            fontSize: 38,
                            fontWeight: FontWeight.w800,
                            color: CouleursSopi.blanc,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        u?.nomComplet ?? '',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (contact.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          contact,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontSize: 13,
                            color: CouleursSopi.texteSecondaire,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              DimensionsSopi.margeCarte,
              20,
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
                    icone: Icons.flag_outlined,
                    libelle: 'Mes signalements',
                    onTap: () => context.push(Chemins.signalements),
                  ),
                  _Ligne(
                    icone: Icons.help_outline_rounded,
                    libelle: 'Aide et support',
                    derniere: true,
                    onTap: () => context.push(Chemins.aide),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              DimensionsSopi.margeCarte,
              20,
              DimensionsSopi.margeCarte,
              0,
            ),
            child: BoutonSecondaire(
              libelle: 'Se déconnecter',
              onPressed: () => _deconnecter(context, ref),
            ),
          ),
          if (version != null)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: Text(
                "Version de l'application $version",
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: CouleursSopi.texteSecondaire,
                ),
              ),
            ),
          SizedBox(height: DimensionsSopi.hauteurBarreOnglets + 48),
        ],
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  const _Ligne({
    required this.icone,
    required this.libelle,
    required this.onTap,
    this.derniere = false,
  });

  final IconData icone;
  final String libelle;
  final VoidCallback onTap;
  final bool derniere;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 60,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        decoration: BoxDecoration(
          border: derniere
              ? null
              : const Border(bottom: BorderSide(color: CouleursSopi.bordure)),
        ),
        child: Row(
          children: [
            Icon(icone, size: 20, color: CouleursSopi.bleu),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                libelle,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: CouleursSopi.texteSecondaire,
            ),
          ],
        ),
      ),
    );
  }
}
