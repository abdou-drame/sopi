import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import 'depot_inscription.dart';
import 'formulaire_inscription.dart';
import 'mise_en_page_etape.dart';
import 'modeles.dart';
import 'selecteur_justificatif.dart';
import 'stockage_jeton_suivi.dart';

const _champsEtape1 = {'nom_entreprise', 'metier', 'quartier', 'adresse'};
const _champsEtape2 = {
  'prenom',
  'nom',
  'telephone',
  'email',
  'mot_de_passe',
  'mot_de_passe_confirmation',
};

/// Étape 3 sur 3 : « Vos justificatifs » et envoi du dossier.
class EtapeJustificatifsPage extends ConsumerStatefulWidget {
  const EtapeJustificatifsPage({super.key});

  @override
  ConsumerState<EtapeJustificatifsPage> createState() =>
      _EtapeJustificatifsPageState();
}

class _EtapeJustificatifsPageState
    extends ConsumerState<EtapeJustificatifsPage> {
  final Map<TypeJustificatif, String> _erreursLignes = {};
  String? _erreurGenerale;
  bool _enCours = false;

  Future<void> _ajouter(TypeJustificatif type) async {
    if (_enCours) return;
    final source = await showModalBottomSheet<SourceJustificatif>(
      context: context,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      barrierColor: CouleursSopi.texte.withValues(alpha: .6),
      builder: (_) => const _ChoixSource(),
    );
    if (source == null || !mounted) return;
    final FichierJustificatif? fichier;
    try {
      fichier = await ref.read(selecteurJustificatifProvider)(source);
    } catch (_) {
      if (!mounted) return;
      setState(
        () => _erreursLignes[type] = switch (source) {
          SourceJustificatif.appareil =>
            "Impossible d'ouvrir l'appareil photo.",
          SourceJustificatif.galerie => "Impossible d'ouvrir la galerie.",
          SourceJustificatif.fichier =>
            "Impossible d'ouvrir le sélecteur de fichiers.",
        },
      );
      return;
    }
    if (fichier == null || !mounted) return;
    final probleme = controlerJustificatif(fichier);
    if (probleme != null) {
      setState(() => _erreursLignes[type] = probleme);
      return;
    }
    ref
        .read(formulaireInscriptionProvider.notifier)
        .definirFichier(type, fichier);
    setState(() {
      _erreursLignes.remove(type);
      _erreurGenerale = null;
    });
  }

  void _retour() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go(Chemins.inscriptionInformations);
    }
  }

  Future<void> _envoyer() async {
    if (_enCours) return;
    final dossier = ref.read(formulaireInscriptionProvider);
    final manquants = {
      for (final t in TypeJustificatif.values)
        if (t.obligatoire && !dossier.fichiers.containsKey(t))
          t: 'Ajoutez ce document pour envoyer votre dossier.',
    };
    if (manquants.isNotEmpty) {
      setState(() {
        _erreursLignes
          ..clear()
          ..addAll(manquants);
        _erreurGenerale = null;
      });
      return;
    }
    setState(() {
      _enCours = true;
      _erreurGenerale = null;
    });
    try {
      final jeton = await ref.read(depotInscriptionProvider).inscrire(dossier);
      // Le jeton n'est donné qu'une seule fois : on le range avant toute navigation.
      await ref.read(jetonSuiviProvider.notifier).definir(jeton);
      if (!mounted) return;
      final formulaire = ref.read(formulaireInscriptionProvider.notifier);
      final erreursServeur = ref.read(
        erreursServeurInscriptionProvider.notifier,
      );
      context.go(Chemins.suiviDossier);
      // Plus rien ne doit rester en mémoire, surtout le mot de passe.
      formulaire.reinitialiser();
      erreursServeur.definir(const {});
    } catch (e) {
      if (!mounted) return;
      _traiterErreur(ErreurApi.depuis(e));
    }
  }

  void _traiterErreur(ErreurApi erreur) {
    if (erreur is! ErreurValidation) {
      setState(() {
        _enCours = false;
        _erreurGenerale = erreur.message;
      });
      return;
    }
    final parChamp = <String, String>{};
    String? autre;
    for (final entree in erreur.champs.entries) {
      final message = entree.value.isEmpty ? null : entree.value.first;
      if (message == null) continue;
      if (_champsEtape1.contains(entree.key) ||
          _champsEtape2.contains(entree.key)) {
        parChamp[entree.key] = message;
      } else {
        // `justificatifs` (dossier incomplet), `justificatifs.0.fichier`… : affichés ici.
        autre ??= message;
      }
    }
    setState(() {
      _enCours = false;
      _erreurGenerale = autre ?? (parChamp.isEmpty ? erreur.message : null);
    });
    if (parChamp.isEmpty) return;
    ref.read(erreursServeurInscriptionProvider.notifier).definir(parChamp);
    // Retour à la première étape concernée, avec l'étape précédente sous elle.
    final routeur = GoRouter.of(context);
    routeur.go(Chemins.inscriptionPrestataire);
    if (!parChamp.keys.any(_champsEtape1.contains)) {
      routeur.push(Chemins.inscriptionInformations);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dossier = ref.watch(formulaireInscriptionProvider);
    return MiseEnPageEtape(
      etape: 3,
      titreLigne1: 'Vos',
      titreLigne2: 'justificatifs',
      libelleBouton: 'Envoyer mon dossier',
      onContinuer: _envoyer,
      onRetour: _retour,
      enCours: _enCours,
      erreur: _erreurGenerale,
      contenu: [
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 14),
          child: Text(
            'Ajoutez une photo nette ou un PDF de chaque document. Un administrateur les vérifie sous quelques jours.',
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 13,
              height: 19 / 13,
              color: CouleursSopi.texteSecondaire,
            ),
          ),
        ),
        for (final type in TypeJustificatif.values)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: _LigneJustificatif(
              type: type,
              fichier: dossier.fichiers[type],
              erreur: _erreursLignes[type],
              desactive: _enCours,
              onTap: () => _ajouter(type),
            ),
          ),
      ],
    );
  }
}

class _LigneJustificatif extends StatelessWidget {
  const _LigneJustificatif({
    required this.type,
    required this.fichier,
    required this.erreur,
    required this.desactive,
    required this.onTap,
  });

  final TypeJustificatif type;
  final FichierJustificatif? fichier;
  final String? erreur;
  final bool desactive;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final facultatif = !type.obligatoire;
    final ajoute = fichier != null;
    final sousTitre = ajoute
        ? '${fichier!.nom} · ajouté'
        : facultatif
        ? 'Photo de votre local ou registre (facultatif)'
        : 'À ajouter';
    final pointille = facultatif && !ajoute;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomPaint(
          foregroundPainter: pointille ? const _BordureTiretee() : null,
          child: Container(
            key: ValueKey('ligne-${type.valeurApi}'),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: pointille ? CouleursSopi.champ : CouleursSopi.blanc,
              borderRadius: BorderRadius.circular(24),
              border: erreur != null
                  ? Border.all(color: CouleursSopi.erreurTexte, width: 1.5)
                  : null,
              boxShadow: pointille ? null : OmbresSopi.carte,
            ),
            child: Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: pointille
                        ? CouleursSopi.blanc
                        : CouleursSopi.bleuPastel,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Icon(switch (type) {
                    TypeJustificatif.cni => Icons.badge_outlined,
                    TypeJustificatif.diplome => Icons.school_outlined,
                    TypeJustificatif.attestation => Icons.storefront_outlined,
                  }, color: CouleursSopi.bleu),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        type.libelle,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: CouleursSopi.texte,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        sousTitre,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontSize: 12,
                          fontWeight: ajoute
                              ? FontWeight.w600
                              : FontWeight.w400,
                          color: ajoute
                              ? CouleursSopi.bleu
                              : CouleursSopi.texteSecondaire,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                SizedBox(
                  height: 36,
                  child: TextButton(
                    onPressed: desactive ? null : onTap,
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      backgroundColor: CouleursSopi.fondSecondaire,
                      shape: const StadiumBorder(),
                      foregroundColor: CouleursSopi.texte,
                      textStyle: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    child: Text(ajoute ? 'Changer' : 'Ajouter'),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (erreur != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
            child: Text(
              erreur!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.erreurTexte,
              ),
            ),
          ),
      ],
    );
  }
}

class _ChoixSource extends StatelessWidget {
  const _ChoixSource();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget ligne(IconData icone, String libelle, SourceJustificatif source) =>
        ListTile(
          leading: CircleAvatar(
            backgroundColor: CouleursSopi.bleuPastel,
            child: Icon(icone, color: CouleursSopi.bleu),
          ),
          title: Text(
            libelle,
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          onTap: () => Navigator.of(context).pop(source),
        );
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 14, 12, 16),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(36),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ligne(
              Icons.photo_library_outlined,
              'Galerie',
              SourceJustificatif.galerie,
            ),
            ligne(
              Icons.photo_camera_outlined,
              'Appareil photo',
              SourceJustificatif.appareil,
            ),
            ligne(
              Icons.insert_drive_file_outlined,
              'Fichier',
              SourceJustificatif.fichier,
            ),
          ],
        ),
      ),
    );
  }
}

/// Bordure en tirets de 2 px (#8a90a2), rayon 24 : le justificatif facultatif encore vide.
class _BordureTiretee extends CustomPainter {
  const _BordureTiretee();

  @override
  void paint(Canvas canvas, Size size) {
    final trace = Paint()
      ..color = const Color(0xFF8A90A2)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    final chemin = Path()
      ..addRRect(
        RRect.fromRectAndRadius(
          Offset.zero & size,
          const Radius.circular(24),
        ).deflate(1),
      );
    for (final mesure in chemin.computeMetrics()) {
      for (var d = 0.0; d < mesure.length; d += 8) {
        canvas.drawPath(mesure.extractPath(d, d + 4), trace);
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter ancien) => false;
}
