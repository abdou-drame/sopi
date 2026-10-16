import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/erreurs.dart';
import '../../../core/theme/couleurs.dart';
import '../../../core/widgets/boutons.dart';
import '../../../core/widgets/champ_texte.dart';
import '../../../core/widgets/etats.dart';
import '../depot_reservation.dart';
import '../formats.dart';

const messagePrixIncoherent =
    'Le prix maximum doit être supérieur ou égal au prix minimum.';

/// Ouvre une feuille modale du bas avec titre, contenu, « Effacer » et « Appliquer ».
Future<T?> ouvrirFeuille<T>(
  BuildContext context, {
  required String titre,
  required Widget Function(BuildContext) contenu,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: CouleursSopi.blanc,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (contexte) => Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(contexte).bottom,
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: CouleursSopi.bordureForte,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(titre, style: Theme.of(contexte).textTheme.titleLarge),
            const SizedBox(height: 14),
            contenu(contexte),
          ],
        ),
      ),
    ),
  );
}

/// Ligne de choix unique (rond plein quand elle est choisie).
class LigneChoix extends StatelessWidget {
  const LigneChoix({
    super.key,
    required this.libelle,
    required this.choisie,
    required this.onTap,
  });

  final String libelle;
  final bool choisie;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: choisie,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  libelle,
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    fontWeight: choisie ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
              ),
              Icon(
                choisie
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: choisie ? CouleursSopi.bleu : CouleursSopi.bordureForte,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Boutons « Effacer » (secondaire) et « Appliquer » (principal) en pied de feuille.
class PiedFeuille extends StatelessWidget {
  const PiedFeuille({
    super.key,
    required this.onEffacer,
    required this.onAppliquer,
  });

  final VoidCallback onEffacer;
  final VoidCallback onAppliquer;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Row(
        children: [
          Expanded(
            child: BoutonSecondaire(libelle: 'Effacer', onPressed: onEffacer),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: BoutonPrimaire(libelle: 'Appliquer', onPressed: onAppliquer),
          ),
        ],
      ),
    );
  }
}

/// Résultat d'une feuille : la valeur choisie (null = « pas de filtre »).
class Choix<T> {
  const Choix(this.valeur);

  final T? valeur;
}

/// Choix du quartier : « Tous » ou un quartier de la liste de l'API.
class FeuilleQuartier extends ConsumerStatefulWidget {
  const FeuilleQuartier({super.key, required this.initial});

  final String? initial;

  @override
  ConsumerState<FeuilleQuartier> createState() => _FeuilleQuartierState();
}

class _FeuilleQuartierState extends ConsumerState<FeuilleQuartier> {
  late String? _choix = widget.initial;

  @override
  Widget build(BuildContext context) {
    final quartiers = ref.watch(quartiersProvider);
    return quartiers.when(
      loading: () =>
          const Padding(padding: EdgeInsets.all(32), child: EtatChargement()),
      error: (e, _) => EtatErreur(
        message: ErreurApi.depuis(e).message,
        onReessayer: () => ref.invalidate(quartiersProvider),
      ),
      data: (liste) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          LigneChoix(
            libelle: 'Tous',
            choisie: _choix == null,
            onTap: () => setState(() => _choix = null),
          ),
          for (final q in liste)
            LigneChoix(
              libelle: q,
              choisie: _choix == q,
              onTap: () => setState(() => _choix = q),
            ),
          PiedFeuille(
            onEffacer: () =>
                Navigator.of(context).pop(const Choix<String>(null)),
            onAppliquer: () => Navigator.of(context).pop(Choix<String>(_choix)),
          ),
        ],
      ),
    );
  }
}

/// Seuils de note proposés.
const seuilsNote = <double>[3, 4, 4.5];

class FeuilleNote extends StatefulWidget {
  const FeuilleNote({super.key, required this.initial});

  final double? initial;

  @override
  State<FeuilleNote> createState() => _FeuilleNoteState();
}

class _FeuilleNoteState extends State<FeuilleNote> {
  late double? _choix = widget.initial;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        LigneChoix(
          libelle: 'Toutes les notes',
          choisie: _choix == null,
          onTap: () => setState(() => _choix = null),
        ),
        for (final seuil in seuilsNote)
          LigneChoix(
            libelle: '${formaterNote(seuil).replaceAll(',0', '')} et plus',
            choisie: _choix == seuil,
            onTap: () => setState(() => _choix = seuil),
          ),
        PiedFeuille(
          onEffacer: () => Navigator.of(context).pop(const Choix<double>(null)),
          onAppliquer: () => Navigator.of(context).pop(Choix<double>(_choix)),
        ),
      ],
    );
  }
}

/// Fourchette de prix (FCFA) : deux champs numériques ; le maximum doit être au moins égal au minimum.
class FeuillePrix extends StatefulWidget {
  const FeuillePrix({super.key, required this.min, required this.max});

  final int? min;
  final int? max;

  @override
  State<FeuillePrix> createState() => _FeuillePrixState();
}

class _FeuillePrixState extends State<FeuillePrix> {
  late final _min = TextEditingController(text: widget.min?.toString() ?? '');
  late final _max = TextEditingController(text: widget.max?.toString() ?? '');
  String? _erreur;

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  void _appliquer() {
    final min = int.tryParse(_min.text.trim());
    final max = int.tryParse(_max.text.trim());
    if (min != null && max != null && max < min) {
      setState(() => _erreur = messagePrixIncoherent);
      return;
    }
    Navigator.of(context).pop(Choix<(int?, int?)>((min, max)));
  }

  @override
  Widget build(BuildContext context) {
    final chiffres = [FilteringTextInputFormatter.digitsOnly];
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ChampTexte(
          libelle: 'Prix minimum (FCFA)',
          controleur: _min,
          clavier: TextInputType.number,
          formateurs: chiffres,
          actionClavier: TextInputAction.next,
          onChanged: (_) => setState(() => _erreur = null),
        ),
        const SizedBox(height: 14),
        ChampTexte(
          libelle: 'Prix maximum (FCFA)',
          controleur: _max,
          clavier: TextInputType.number,
          formateurs: chiffres,
          actionClavier: TextInputAction.done,
          erreur: _erreur,
          onChanged: (_) => setState(() => _erreur = null),
          onSubmitted: (_) => _appliquer(),
        ),
        PiedFeuille(
          onEffacer: () => Navigator.of(
            context,
          ).pop(const Choix<(int?, int?)>((null, null))),
          onAppliquer: _appliquer,
        ),
      ],
    );
  }
}

/// Choix du tri (appliqué tout de suite).
class FeuilleTri extends StatelessWidget {
  const FeuilleTri({super.key, required this.initial});

  final TriPrestataires initial;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final t in TriPrestataires.values)
          LigneChoix(
            libelle: t.libelle,
            choisie: t == initial,
            onTap: () => Navigator.of(context).pop(t),
          ),
      ],
    );
  }
}
