import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../reservation/depot_reservation.dart';
import '../../core/widgets/champ_formulaire.dart';
import 'formulaire_inscription.dart';
import 'mise_en_page_etape.dart';
import 'validation_etapes.dart';

/// Étape 1 sur 3 : « Votre activité » (entreprise, métier, quartier, adresse).
class EtapeActivitePage extends ConsumerStatefulWidget {
  const EtapeActivitePage({super.key});

  @override
  ConsumerState<EtapeActivitePage> createState() => _EtapeActivitePageState();
}

class _EtapeActivitePageState extends ConsumerState<EtapeActivitePage> {
  late final TextEditingController _entreprise;
  late final TextEditingController _adresse;
  late final TextEditingController _autreQuartier;
  Map<String, String> _erreurs = {};
  bool _tentative = false;

  @override
  void initState() {
    super.initState();
    final d = ref.read(formulaireInscriptionProvider);
    _entreprise = TextEditingController(text: d.nomEntreprise);
    _adresse = TextEditingController(text: d.adresse);
    _autreQuartier = TextEditingController(
      text: d.quartierAutre ? (d.quartier ?? '') : '',
    );
    // Erreurs du serveur (422) renvoyées depuis l'étape 3 : affichées sous les bons champs.
    const cles = {'nom_entreprise', 'metier', 'quartier', 'adresse'};
    final serveur = ref.read(erreursServeurInscriptionProvider);
    _erreurs = {
      for (final e in serveur.entries)
        if (cles.contains(e.key)) e.key: e.value,
    };
    _tentative = _erreurs.isNotEmpty;
    if (_tentative) {
      Future.microtask(() {
        if (mounted) {
          ref.read(erreursServeurInscriptionProvider.notifier).prendre(cles);
        }
      });
    }
  }

  @override
  void dispose() {
    _entreprise.dispose();
    _adresse.dispose();
    _autreQuartier.dispose();
    super.dispose();
  }

  /// Liste vide ou en erreur : le prestataire saisit son quartier, il n'est jamais bloqué.
  static bool _listeIndisponible(AsyncValue<List<String>> quartiers) =>
      quartiers.hasError ||
      (quartiers.hasValue && quartiers.requireValue.isEmpty);

  Map<String, String> _validerEtape() => ValidationEtapes.etape1(
    ref.read(formulaireInscriptionProvider),
    saisieLibre: _listeIndisponible(ref.read(quartiersInscriptionProvider)),
  );

  /// Revalide en direct une fois que l'utilisateur a essayé de continuer.
  void _apresModification() {
    if (!_tentative) return;
    setState(() => _erreurs = _validerEtape());
  }

  void _continuer() {
    final erreurs = _validerEtape();
    setState(() {
      _tentative = true;
      _erreurs = erreurs;
    });
    if (erreurs.isEmpty) context.push(Chemins.inscriptionInformations);
  }

  void _retour() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go(Chemins.connexion);
    }
  }

  Future<void> _choisirQuartier(List<String> quartiers) async {
    final d = ref.read(formulaireInscriptionProvider);
    final choix = await showModalBottomSheet<_ChoixQuartier>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      barrierColor: CouleursSopi.texte.withValues(alpha: .6),
      builder: (_) => _FeuilleQuartiers(
        quartiers: quartiers,
        actuel: d.quartierAutre ? null : d.quartier,
        autreChoisi: d.quartierAutre,
      ),
    );
    if (choix == null || !mounted) return;
    ref
        .read(formulaireInscriptionProvider.notifier)
        .mettreAJour(
          (x) => choix.autre
              ? x.copie(quartier: _autreQuartier.text, quartierAutre: true)
              : x.copie(quartier: choix.nom, quartierAutre: false),
        );
    _apresModification();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final d = ref.watch(formulaireInscriptionProvider);
    final notifier = ref.read(formulaireInscriptionProvider.notifier);
    final metiers = ref.watch(metiersProvider);
    final quartiers = ref.watch(quartiersInscriptionProvider);

    Widget libelle(String texte) => Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(
        texte,
        style: theme.textTheme.labelMedium?.copyWith(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: CouleursSopi.texteSecondaire,
        ),
      ),
    );

    Widget erreur(String? message) => message == null
        ? const SizedBox.shrink()
        : Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              message,
              style: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.erreurTexte,
              ),
            ),
          );

    final blocMetier = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        libelle('Métier'),
        metiers.when(
          loading: () => const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            ),
          ),
          error: (_, _) => Row(
            children: [
              const Expanded(child: Text('Impossible de charger les métiers.')),
              TextButton(
                onPressed: () => ref.invalidate(metiersProvider),
                child: const Text('Réessayer'),
              ),
            ],
          ),
          data: (liste) => Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final m in liste)
                _PuceMetier(
                  libelle: m.nom,
                  selectionnee: d.metierCode == m.code,
                  onTap: () {
                    notifier.mettreAJour((x) => x.copie(metierCode: m.code));
                    _apresModification();
                  },
                ),
            ],
          ),
        ),
        erreur(_erreurs['metier']),
      ],
    );

    void saisirQuartier(String v) {
      notifier.mettreAJour((x) => x.copie(quartier: v, quartierAutre: true));
      _apresModification();
    }

    final Widget blocQuartier;
    if (quartiers.isLoading) {
      blocQuartier = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          libelle('Quartier'),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            ),
          ),
        ],
      );
    } else if (_listeIndisponible(quartiers)) {
      // Liste vide ou appel en échec : saisie directe, sans bloquer l'inscription.
      blocQuartier = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ChampFormulaire(
            key: const ValueKey('champ-autre-quartier'),
            libelle: 'Quartier',
            controleur: _autreQuartier,
            erreur: _erreurs['quartier'],
            actionClavier: TextInputAction.next,
            hauteur: 50,
            onChanged: saisirQuartier,
          ),
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              'La liste des quartiers est indisponible. Saisissez le vôtre.',
              key: const ValueKey('liste-quartiers-indisponible'),
              style: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.texteSecondaire,
              ),
            ),
          ),
        ],
      );
    } else {
      blocQuartier = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          libelle('Quartier'),
          InkWell(
            key: const ValueKey('champ-quartier'),
            borderRadius: BorderRadius.circular(18),
            onTap: () => _choisirQuartier(quartiers.requireValue),
            child: Container(
              height: 50,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                color: CouleursSopi.champ,
                borderRadius: BorderRadius.circular(18),
                border: _erreurs['quartier'] != null && !d.quartierAutre
                    ? Border.all(color: CouleursSopi.erreurTexte, width: 1.5)
                    : null,
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      d.quartierAutre
                          ? _libelleAutreQuartier
                          : d.quartier ?? 'Choisir un quartier',
                      style: theme.textTheme.bodyLarge?.copyWith(
                        fontSize: 15,
                        color: !d.quartierAutre && d.quartier == null
                            ? CouleursSopi.texteSecondaire
                            : CouleursSopi.texte,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    color: CouleursSopi.texteSecondaire,
                  ),
                ],
              ),
            ),
          ),
          if (d.quartierAutre) ...[
            const SizedBox(height: 12),
            ChampFormulaire(
              key: const ValueKey('champ-autre-quartier'),
              libelle: 'Votre quartier',
              controleur: _autreQuartier,
              erreur: _erreurs['quartier'],
              actionClavier: TextInputAction.next,
              hauteur: 50,
              onChanged: saisirQuartier,
            ),
          ] else
            erreur(_erreurs['quartier']),
        ],
      );
    }

    return MiseEnPageEtape(
      etape: 1,
      titreLigne1: 'Votre',
      titreLigne2: 'activité',
      libelleBouton: 'Continuer',
      onContinuer: _continuer,
      onRetour: _retour,
      contenu: [
        CarteEtape(
          enfants: [
            ChampFormulaire(
              libelle: "Nom de l'entreprise",
              controleur: _entreprise,
              erreur: _erreurs['nom_entreprise'],
              actionClavier: TextInputAction.next,
              hauteur: 50,
              onChanged: (v) {
                notifier.mettreAJour((x) => x.copie(nomEntreprise: v));
                _apresModification();
              },
            ),
            blocMetier,
            blocQuartier,
            ChampFormulaire(
              libelle: 'Adresse',
              controleur: _adresse,
              erreur: _erreurs['adresse'],
              actionClavier: TextInputAction.done,
              hauteur: 50,
              onChanged: (v) {
                notifier.mettreAJour((x) => x.copie(adresse: v));
                _apresModification();
              },
              onSubmitted: (_) => _continuer(),
            ),
          ],
        ),
      ],
    );
  }
}

class _PuceMetier extends StatelessWidget {
  const _PuceMetier({
    required this.libelle,
    required this.selectionnee,
    required this.onTap,
  });

  final String libelle;
  final bool selectionnee;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selectionnee,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Container(
          height: 40,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selectionnee
                ? CouleursSopi.bleu
                : CouleursSopi.fondSecondaire,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            libelle,
            style: TextStyle(
              fontSize: 13,
              fontWeight: selectionnee ? FontWeight.w700 : FontWeight.w600,
              color: selectionnee ? CouleursSopi.blanc : CouleursSopi.texte,
            ),
          ),
        ),
      ),
    );
  }
}

/// Dernier choix de la liste : le prestataire saisit lui-même son quartier.
const _libelleAutreQuartier = 'Autre quartier';

/// Choix fait dans la feuille : un quartier de la liste, ou « Autre quartier ».
class _ChoixQuartier {
  const _ChoixQuartier.quartier(String this.nom) : autre = false;
  const _ChoixQuartier.autre() : nom = null, autre = true;

  final String? nom;
  final bool autre;
}

class _FeuilleQuartiers extends StatelessWidget {
  const _FeuilleQuartiers({
    required this.quartiers,
    required this.actuel,
    required this.autreChoisi,
  });

  final List<String> quartiers;
  final String? actuel;
  final bool autreChoisi;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget ligne(String texte, bool choisi, _ChoixQuartier choix) => ListTile(
      title: Text(
        texte,
        style: theme.textTheme.titleSmall?.copyWith(
          fontWeight: choisi ? FontWeight.w800 : FontWeight.w600,
        ),
      ),
      trailing: choisi
          ? const Icon(Icons.check_rounded, color: CouleursSopi.bleu)
          : null,
      onTap: () => Navigator.of(context).pop(choix),
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * .7,
        ),
        padding: const EdgeInsets.fromLTRB(12, 14, 12, 16),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(36),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
              child: Text(
                'Votre quartier',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: CouleursSopi.texte,
                ),
              ),
            ),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                children: [
                  for (final q in quartiers)
                    ligne(q, q == actuel, _ChoixQuartier.quartier(q)),
                  ligne(
                    _libelleAutreQuartier,
                    autreChoisi,
                    const _ChoixQuartier.autre(),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
