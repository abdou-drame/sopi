import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/animation/apparition.dart';
import '../../core/config/support.dart';
import '../../core/theme/couleurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import 'session.dart';
import 'widgets/mise_en_page_acces.dart';

/// Nature de la restriction : donnée par `statutCompte` du 403 de connexion, ou déduite du message du serveur en secours.
enum TypeRestriction {
  suspendu(
    titre: 'suspendu',
    phrase: 'Votre accès est temporairement limité suite à une sanction.',
  ),
  refuse(
    titre: 'refusé',
    phrase: "Votre demande d'inscription n'a pas été acceptée.",
  ),
  banni(titre: 'banni', phrase: 'Votre accès à Sopi a été retiré.'),
  enAttente(
    titre: 'en attente',
    phrase: "Votre compte est en attente de validation par l'équipe Sopi.",
  );

  const TypeRestriction({required this.titre, required this.phrase});

  /// Seconde ligne du titre : « Compte / suspendu ».
  final String titre;
  final String phrase;

  /// Statut envoyé par le serveur ; null si absent ou inconnu.
  static TypeRestriction? depuisStatut(String? statut) => switch (statut) {
    'suspendu' => suspendu,
    'refuse' => refuse,
    'banni' => banni,
    'en_attente' => enAttente,
    _ => null,
  };

  static TypeRestriction depuisMessage(String message) {
    final m = message.toLowerCase();
    if (m.contains('banni')) return banni;
    if (m.contains('refus')) return refuse;
    return suspendu;
  }

  /// Le statut du serveur d'abord, le texte du message en secours.
  static TypeRestriction pour(Restreint? restreint) =>
      depuisStatut(restreint?.statutCompte) ??
      depuisMessage(restreint?.message ?? '');
}

/// Écran « compte suspendu / refusé / banni » (maquette Suspendu).
class CompteRestreintPage extends ConsumerWidget {
  const CompteRestreintPage({super.key});

  Future<void> _contacter(BuildContext context, WidgetRef ref) async {
    final ouvert = await contacterSupport(ref);
    if (!ouvert && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Impossible d'ouvrir votre messagerie.")),
      );
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final etat = ref.watch(sessionProvider);
    final restreint = etat is Restreint ? etat : null;
    final type = TypeRestriction.pour(restreint);
    final motif = restreint?.motif?.trim();
    final aMotif = motif != null && motif.isNotEmpty;
    final fin = restreint?.finSuspension;
    final aSupport = ref.watch(supportEmailProvider).isNotEmpty;
    final haut = MediaQuery.paddingOf(context).top;

    return Scaffold(
      body: EcranAcces(
        margePied: 24,
        entete: DescenteDepuisLeHaut(
          child: ClipRRect(
            borderRadius: const BorderRadius.vertical(
              bottom: Radius.circular(DimensionsSopi.rayonEnteteRestreint),
            ),
            child: FondDegrade(
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 380),
                child: Padding(
                  padding: EdgeInsets.fromLTRB(36, haut + 60, 36, 40),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Apparition(
                        delai: DescenteDepuisLeHaut.duree,
                        child: Container(
                          width: 104,
                          height: 104,
                          decoration: const BoxDecoration(
                            color: CouleursSopi.marine,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.lock_rounded,
                            size: 46,
                            color: CouleursSopi.blanc,
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Apparition(
                        delai: delaiApresEntete(1),
                        child: TitreDeuxLignes(
                          ligne1: 'Compte',
                          ligne2: type.titre,
                          taille: 28,
                          interligne: 34,
                          centre: true,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Apparition(
                        delai: delaiApresEntete(2),
                        child: Text(
                          type.phrase,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontSize: 14,
                            height: 20 / 14,
                            fontWeight: FontWeight.w400,
                            color: CouleursSopi.texteSecondaire,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
        corps: [
          ApparitionAcces(
            rang: 0,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(
                DimensionsSopi.margeCarte,
                28,
                DimensionsSopi.margeCarte,
                0,
              ),
              child: _CarteMotif(
                libelle: aMotif ? 'Motif' : 'Information',
                texte: aMotif ? motif : (restreint?.message ?? ''),
                fin: fin,
              ),
            ),
          ),
        ],
        pied: ApparitionAcces(
          rang: 1,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (aSupport) ...[
                BoutonPrimaire(
                  libelle: 'Contacter le support',
                  onPressed: () => _contacter(context, ref),
                ),
                const SizedBox(height: 10),
              ],
              BoutonSecondaire(
                libelle: 'Se déconnecter',
                onPressed: () =>
                    ref.read(sessionProvider.notifier).quitterRestriction(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Carte bleu pastel : motif, puis (s'il y a une fin) la date de fin de la restriction.
class _CarteMotif extends StatelessWidget {
  const _CarteMotif({required this.libelle, required this.texte, this.fin});

  final String libelle;
  final String texte;
  final DateTime? fin;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: CouleursSopi.bleuPastel,
        borderRadius: BorderRadius.circular(DimensionsSopi.rayonEncadreMotif),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            libelle,
            style: theme.textTheme.labelMedium?.copyWith(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: CouleursSopi.bleu,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            texte,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              height: 20 / 14,
              fontWeight: FontWeight.w400,
              color: CouleursSopi.bleuProfond,
            ),
          ),
          if (fin != null) ...[
            const SizedBox(height: 10),
            Container(
              height: 1,
              color: CouleursSopi.bleu.withValues(alpha: 0.2),
            ),
            const SizedBox(height: 10),
            Wrap(
              alignment: WrapAlignment.spaceBetween,
              runSpacing: 4,
              children: [
                Text(
                  "Jusqu'au",
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 13,
                    fontWeight: FontWeight.w400,
                    color: CouleursSopi.bleuProfond,
                  ),
                ),
                Text(
                  // Africa/Dakar est à UTC+0 : on affiche la date UTC, quel que soit le fuseau du téléphone.
                  DateFormat('d MMM y', 'fr_FR').format(fin!.toUtc()),
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: CouleursSopi.bleuProfond,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
