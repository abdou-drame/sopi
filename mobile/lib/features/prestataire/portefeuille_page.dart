import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/chargement.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/liste_infinie.dart';
import '../reservation/widgets/composants.dart';
import 'depot_prestataire.dart';
import 'modeles_portefeuille.dart';

/// « 1 000 » (montant sans la devise).
String formaterNombre(num montant) =>
    formaterArgent(montant).replaceFirst(RegExp(r'\s*FCFA$'), '');

/// Onglet Portefeuille du prestataire (maquette Portefeuille) : solde, limites de retrait et historique des mouvements.
class PortefeuillePage extends ConsumerStatefulWidget {
  const PortefeuillePage({super.key});

  @override
  ConsumerState<PortefeuillePage> createState() => _PortefeuillePageState();
}

class _PortefeuillePageState extends ConsumerState<PortefeuillePage> {
  final _controleur = ControleurListe<MouvementPortefeuille>();
  bool _premierChargement = true;

  Future<PageListe<MouvementPortefeuille>> _charger(int page) async {
    // « Tirer pour actualiser » relit aussi le solde (le premier chargement l'a déjà fait en affichant la carte).
    if (page == 1 && !_premierChargement) ref.invalidate(soldeProvider);
    _premierChargement = false;
    final reponse = await ref
        .read(depotPrestataireProvider)
        .mouvements(page: page);
    return PageListe(
      elements: reponse.elements,
      page: reponse.pagination.page,
      derniere: reponse.pagination.derniere,
    );
  }

  @override
  Widget build(BuildContext context) {
    // Un retrait ou un crédit vient d'avoir lieu ailleurs : l'historique est relu.
    ref.listen(versionPortefeuilleProvider, (_, _) => _controleur.recharger());
    return Scaffold(
      body: ListeInfinie<MouvementPortefeuille>(
        controleur: _controleur,
        titreLigne1: 'Mon',
        titreLigne2: 'portefeuille',
        avecRetour: false,
        enfantEntete: const _CarteSoldePortefeuille(),
        charger: _charger,
        construire: (context, m) => LigneMouvement(mouvement: m),
        messageVide: 'Aucun mouvement pour le moment.',
        iconeVide: Icons.account_balance_wallet_outlined,
        margeBasse: margeBarreOnglets(context),
        enTete: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
          child: Text(
            'Historique',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              fontSize: 17,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}

/// Carte bleue de la maquette Portefeuille : solde, limites d'un retrait (données du serveur) et bouton « Retirer ».
class _CarteSoldePortefeuille extends ConsumerWidget {
  const _CarteSoldePortefeuille();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final solde = ref.watch(soldeProvider);
    final limites = solde.value;
    final texteLimites = limites?.minimum != null && limites?.maximum != null
        ? 'Retrait de ${formaterNombre(limites!.minimum!)} à ${formaterArgent(limites.maximum!)}'
        : null;

    Widget montant;
    if (solde.hasValue) {
      final texte = formaterArgent(solde.value!.solde);
      final coupure = texte.lastIndexOf(' ');
      montant = Text.rich(
        TextSpan(
          text: texte.substring(0, coupure + 1),
          children: [
            TextSpan(
              text: texte.substring(coupure + 1),
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
            ),
          ],
        ),
        style: theme.textTheme.headlineMedium?.copyWith(
          color: CouleursSopi.blanc,
          fontSize: 36,
          height: 42 / 36,
          fontWeight: FontWeight.w800,
        ),
      );
    } else if (solde.hasError) {
      montant = InkWell(
        onTap: () => ref.invalidate(soldeProvider),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 44),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Solde indisponible. Toucher pour réessayer.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: CouleursSopi.blanc,
                fontSize: 13,
                fontWeight: FontWeight.w700,
                decoration: TextDecoration.underline,
                decorationColor: CouleursSopi.blanc,
              ),
            ),
          ),
        ),
      );
    } else {
      montant = const Squelette(largeur: 170, hauteur: 38);
    }

    final discret = theme.textTheme.bodySmall?.copyWith(
      color: CouleursSopi.blanc.withValues(alpha: 0.85),
      fontSize: 12,
    );

    return Container(
      key: const ValueKey('carte-solde-portefeuille'),
      height: 164,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: CouleursSopi.bleu,
        borderRadius: BorderRadius.circular(28),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            'Solde disponible',
            style: discret?.copyWith(fontWeight: FontWeight.w500),
          ),
          montant,
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(texteLimites ?? '', style: discret, maxLines: 2),
              ),
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
                      padding: const EdgeInsets.symmetric(horizontal: 20),
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

/// Une ligne de l'historique : icône, titre, détail (service ou statut, date) et montant signé.
class LigneMouvement extends StatelessWidget {
  const LigneMouvement({super.key, required this.mouvement});

  final MouvementPortefeuille mouvement;

  String get _titre {
    final m = mouvement;
    switch (m.type) {
      case TypeMouvement.credit:
        return m.prestation != null
            ? 'Prestation terminée'
            : (m.libelle ?? 'Crédit');
      case TypeMouvement.retrait:
        return m.operateur == null
            ? 'Retrait'
            : 'Retrait ${m.operateur!.libelle}';
      case TypeMouvement.remboursementRetrait:
      case TypeMouvement.inconnu:
        return m.libelle ?? 'Mouvement';
    }
  }

  String get _detail {
    final m = mouvement;
    final date = formaterJourMois(m.date);
    switch (m.type) {
      case TypeMouvement.credit:
        return m.prestation != null ? '${m.prestation} · $date' : date;
      case TypeMouvement.retrait:
        final statut = switch (m.statutRetrait) {
          StatutRetrait.reussi => 'Effectué',
          StatutRetrait.enCours => 'En cours',
          StatutRetrait.echoue => 'Échoué',
          null => null,
        };
        return statut == null ? date : '$statut · $date';
      case TypeMouvement.remboursementRetrait:
      case TypeMouvement.inconnu:
        return date;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final m = mouvement;
    final augmente = m.type.augmenteLeSolde;

    final (Color fond, Color couleur, IconData icone) = switch (m.type) {
      TypeMouvement.credit || TypeMouvement.remboursementRetrait => (
        CouleursSopi.bleuPastel,
        CouleursSopi.bleu,
        Icons.south_west_rounded,
      ),
      TypeMouvement.retrait when m.statutRetrait == StatutRetrait.enCours => (
        CouleursSopi.ardoisePastel,
        CouleursSopi.ardoiseTexte,
        Icons.schedule_rounded,
      ),
      TypeMouvement.retrait when m.statutRetrait == StatutRetrait.echoue => (
        CouleursSopi.erreurFond,
        CouleursSopi.erreurTexte,
        Icons.error_outline_rounded,
      ),
      _ => (
        CouleursSopi.indigoPastel,
        CouleursSopi.indigoTexte,
        Icons.north_east_rounded,
      ),
    };

    return Semantics(
      container: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: CouleursSopi.bordure),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: fond,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icone, size: 20, color: couleur),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _titre,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    _detail,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 12,
                      color: CouleursSopi.texteDiscret,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(
              '${augmente ? '+' : '−'} ${formaterArgent(m.montant)}',
              style: theme.textTheme.titleSmall?.copyWith(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: augmente ? CouleursSopi.bleu : CouleursSopi.marine,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
