import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/config/environnement.dart';
import '../../core/config/support.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/disposition.dart';
import '../../core/widgets/boutons.dart';
import '../auth/session.dart';
import '../auth/widgets/mise_en_page_acces.dart';
import '../reservation/depot_reservation.dart';
import '../reservation/modeles.dart';
import 'depot_inscription.dart';
import 'modeles.dart';
import 'stockage_jeton_suivi.dart';

/// Dossier de l'inscription en cours, relu à chaque actualisation. Null s'il n'y a aucun jeton de suivi.
final suiviDossierProvider = FutureProvider.autoDispose<SuiviDossier?>(
  retry: sansRelance,
  (ref) async {
    final jeton = ref.watch(jetonSuiviProvider);
    if (jeton == null) return null;
    return ref.watch(depotInscriptionProvider).suivi(jeton);
  },
);

/// Sort de l'écran de suivi vers la connexion. [oublier] efface le jeton (dossier accepté ou introuvable).
Future<void> _retourConnexion(
  BuildContext context,
  WidgetRef ref, {
  bool oublier = false,
}) async {
  final session = ref.read(sessionProvider.notifier);
  final jeton = ref.read(jetonSuiviProvider.notifier);
  final routeur = GoRouter.of(context);
  session.quitterRestriction();
  if (oublier) await jeton.oublier();
  routeur.go(Chemins.connexion);
}

/// Suivi du dossier (maquettes SuiviDossier et DossierRefuse) : la page choisit son contenu selon le statut
/// renvoyé par `GET /suivi/{jeton}`.
class SuiviDossierPage extends ConsumerWidget {
  const SuiviDossierPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final suivi = ref.watch(suiviDossierProvider);
    final metiers = ref.watch(metiersProvider).value ?? const <Metier>[];
    final jeton = ref.watch(jetonSuiviProvider);
    final lien = jeton == null ? null : '${urlApi()}/suivi/$jeton';

    String nomMetier(String code) {
      for (final m in metiers) {
        if (m.code == code) return m.nom;
      }
      return code.isEmpty ? '' : code[0].toUpperCase() + code.substring(1);
    }

    return suivi.when(
      loading: () =>
          const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) {
        final erreur = ErreurApi.depuis(e);
        final introuvable = erreur is ErreurServeur && erreur.statut == 404;
        return _EcranDossier(
          icone: introuvable
              ? Icons.search_off_rounded
              : Icons.wifi_off_rounded,
          titre1: 'Dossier',
          titre2: introuvable ? 'introuvable' : 'indisponible',
          texte: erreur.message,
          boutons: [
            if (!introuvable)
              BoutonPrimaire(
                libelle: 'Réessayer',
                onPressed: () => ref.invalidate(suiviDossierProvider),
              ),
            _BoutonRetour(
              onPressed: () =>
                  _retourConnexion(context, ref, oublier: introuvable),
            ),
          ],
        );
      },
      data: (dossier) {
        if (dossier == null) {
          return _EcranDossier(
            icone: Icons.folder_off_outlined,
            titre1: 'Aucun dossier',
            titre2: 'à suivre',
            texte:
                "Nous n'avons pas de dossier d'inscription enregistré sur ce téléphone.",
            boutons: [
              _BoutonRetour(onPressed: () => _retourConnexion(context, ref)),
            ],
          );
        }
        if (dossier.refuse) {
          return _DossierRefuse(dossier: dossier, lien: lien);
        }
        if (dossier.accepte) {
          return _EcranDossier(
            icone: Icons.check_rounded,
            titre1: 'Dossier',
            titre2: 'accepté',
            texte:
                'Félicitations, votre dossier a été accepté. Vous pouvez maintenant vous connecter avec votre e-mail et votre mot de passe.',
            lien: lien,
            boutons: [
              BoutonPrimaire(
                libelle: 'Retour à la connexion',
                onPressed: () => _retourConnexion(context, ref, oublier: true),
              ),
            ],
          );
        }
        if (dossier.enAttente) {
          return _EcranDossier(
            icone: Icons.schedule_rounded,
            titre1: 'Dossier',
            titre2: "en cours d'examen",
            texte:
                "Nous vérifions vos justificatifs. Vous serez prévenu par e-mail dès qu'une décision est prise.",
            carte: _CarteDetails(
              dossier: dossier,
              metier: nomMetier(dossier.metier),
              avecDetails: true,
            ),
            lien: lien,
            boutons: [
              BoutonPrimaire(
                libelle: 'Actualiser',
                chargement: suivi.isRefreshing,
                onPressed: () => ref.invalidate(suiviDossierProvider),
              ),
              _BoutonRetour(onPressed: () => _retourConnexion(context, ref)),
            ],
          );
        }
        // Suspendu, banni ou statut inconnu : le dossier n'a plus rien à montrer.
        return _EcranDossier(
          icone: Icons.lock_outline_rounded,
          titre1: 'Compte',
          titre2: 'indisponible',
          texte:
              "Ce compte n'est pas utilisable pour le moment. Contactez le support pour en savoir plus.",
          boutons: [
            _BoutonRetour(onPressed: () => _retourConnexion(context, ref)),
          ],
        );
      },
    );
  }
}

class _DossierRefuse extends ConsumerWidget {
  const _DossierRefuse({required this.dossier, this.lien});

  final SuiviDossier dossier;
  final String? lien;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final aSupport = ref.watch(supportEmailProvider).isNotEmpty;
    return _EcranDossier(
      icone: Icons.close_rounded,
      erreurIcone: true,
      titre1: 'Dossier',
      titre2: 'non retenu',
      texte: "Votre demande d'inscription n'a pas été acceptée.",
      motif: dossier.motifRefus,
      carte: _CarteDetails(dossier: dossier, metier: '', avecDetails: false),
      lien: lien,
      boutons: [
        if (aSupport)
          BoutonPrimaire(
            libelle: 'Contacter le support',
            onPressed: () async {
              final messenger = ScaffoldMessenger.of(context);
              final ouvert = await contacterSupport(ref);
              if (!ouvert) {
                messenger.showSnackBar(
                  const SnackBar(
                    content: Text("Impossible d'ouvrir la messagerie."),
                  ),
                );
              }
            },
          ),
        _BoutonRetour(onPressed: () => _retourConnexion(context, ref)),
      ],
    );
  }
}

class _BoutonRetour extends StatelessWidget {
  const _BoutonRetour({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) =>
      BoutonSecondaire(libelle: 'Retour à la connexion', onPressed: onPressed);
}

/// Mise en page commune : pastille d'icône, titre centré, texte, contenu, boutons en bas.
class _EcranDossier extends StatelessWidget {
  const _EcranDossier({
    required this.icone,
    required this.titre1,
    required this.titre2,
    required this.texte,
    required this.boutons,
    this.motif,
    this.carte,
    this.lien,
    this.erreurIcone = false,
  });

  final IconData icone;
  final String titre1;
  final String titre2;
  final String texte;
  final String? motif;
  final Widget? carte;

  /// Lien de suivi personnel, affiché dans une carte sous les détails ; null : pas de carte.
  final String? lien;
  final bool erreurIcone;
  final List<Widget> boutons;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final haut = hautEcran(context) - hautMaquette;
    return Scaffold(
      body: Stack(
        children: [
          Column(
            children: [
              Expanded(
                child: ListView(
                  padding: EdgeInsets.only(top: haut + 80, bottom: 16),
                  children: [
                    Center(
                      child: Container(
                        width: 104,
                        height: 104,
                        decoration: BoxDecoration(
                          color: erreurIcone
                              ? CouleursSopi.erreurPale
                              : CouleursSopi.bleuPale,
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          icone,
                          size: erreurIcone ? 44 : 46,
                          color: erreurIcone
                              ? CouleursSopi.erreurIcone
                              : CouleursSopi.bleu,
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    TitreDeuxLignes(
                      ligne1: titre1,
                      ligne2: titre2,
                      taille: 28,
                      interligne: 34,
                      centre: true,
                    ),
                    const SizedBox(height: 20),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 36),
                      child: Text(
                        texte,
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 14,
                          height: 20 / 14,
                          color: CouleursSopi.texteSecondaire,
                        ),
                      ),
                    ),
                    const SizedBox(height: 76),
                    if (motif != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        child: Container(
                          key: const ValueKey('motif-refus'),
                          padding: const EdgeInsets.all(18),
                          decoration: BoxDecoration(
                            color: CouleursSopi.bleuPastel,
                            borderRadius: BorderRadius.circular(26),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Motif du refus',
                                style: theme.textTheme.labelMedium?.copyWith(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: CouleursSopi.bleu,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                motif!,
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  fontSize: 14,
                                  height: 20 / 14,
                                  color: CouleursSopi.bleuProfond,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    if (carte != null)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: carte,
                      ),
                    if (lien != null)
                      Padding(
                        padding: EdgeInsets.fromLTRB(
                          16,
                          carte != null ? 12 : 0,
                          16,
                          0,
                        ),
                        child: _CarteLienSuivi(lien: lien!),
                      ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var i = 0; i < boutons.length; i++) ...[
                      if (i > 0) const SizedBox(height: 10),
                      boutons[i],
                    ],
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Carte blanche : entreprise (+ métier et date de dépôt si [avecDetails]) puis un statut par document.
class _CarteDetails extends StatelessWidget {
  const _CarteDetails({
    required this.dossier,
    required this.metier,
    required this.avecDetails,
  });

  final SuiviDossier dossier;
  final String metier;
  final bool avecDetails;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget ligne(String libelle, String valeur) => Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          libelle,
          style: theme.textTheme.bodyMedium?.copyWith(
            fontSize: 14,
            color: CouleursSopi.texteSecondaire,
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            valeur,
            textAlign: TextAlign.right,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: CouleursSopi.texte,
            ),
          ),
        ),
      ],
    );

    final date = dossier.dateSoumission;
    final enfants = <Widget>[
      ligne('Entreprise', dossier.nomEntreprise),
      if (avecDetails && metier.isNotEmpty) ligne('Métier', metier),
      if (avecDetails && date != null)
        ligne('Déposé le', formaterDateCourte(date)),
      if (avecDetails && dossier.justificatifs.isNotEmpty)
        const Divider(height: 1, color: CouleursSopi.separateur),
      for (final j in dossier.justificatifs)
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              key: ValueKey('document-${j.type?.valeurApi ?? 'autre'}'),
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: Text(
                    j.type?.libelle ?? 'Document',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 14,
                      color: CouleursSopi.texte,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                _PastilleStatut(statut: j.statut),
              ],
            ),
            if (j.statut == StatutJustificatif.rejete && j.motifRejet != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  j.motifRejet!,
                  key: ValueKey(
                    'motif-document-${j.type?.valeurApi ?? 'autre'}',
                  ),
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontSize: 13,
                    height: 18 / 13,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
              ),
          ],
        ),
    ];
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(26),
        boxShadow: OmbresSopi.carte,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < enfants.length; i++) ...[
            if (i > 0) const SizedBox(height: 12),
            enfants[i],
          ],
        ],
      ),
    );
  }
}

/// Carte blanche « Votre lien de suivi personnel » : l'URL complète (tronquée) et un bouton qui la copie.
class _CarteLienSuivi extends StatelessWidget {
  const _CarteLienSuivi({required this.lien});

  final String lien;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      key: const ValueKey('carte-lien-suivi'),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(26),
        boxShadow: OmbresSopi.carte,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Votre lien de suivi personnel',
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: CouleursSopi.texte,
            ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Text(
                  lien,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              BoutonSecondaire(
                libelle: 'Copier',
                pleineLargeur: false,
                onPressed: () async {
                  final messenger = ScaffoldMessenger.of(context);
                  await Clipboard.setData(ClipboardData(text: lien));
                  messenger.showSnackBar(
                    const SnackBar(content: Text('Lien copié')),
                  );
                },
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PastilleStatut extends StatelessWidget {
  const _PastilleStatut({required this.statut});

  final StatutJustificatif statut;

  @override
  Widget build(BuildContext context) {
    final (fond, texte) = switch (statut) {
      StatutJustificatif.enAttente => (
        CouleursSopi.ardoisePastel,
        CouleursSopi.ardoiseTexte,
      ),
      StatutJustificatif.valide => (CouleursSopi.bleuPastel, CouleursSopi.bleu),
      StatutJustificatif.rejete => (CouleursSopi.bleu, CouleursSopi.blanc),
    };
    return Container(
      height: 26,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Text(
        statut.libelle,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: texte,
        ),
      ),
    );
  }
}
