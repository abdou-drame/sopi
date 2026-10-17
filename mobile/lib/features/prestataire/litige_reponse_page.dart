import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/animation/chargement.dart';
import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import '../compte/modeles_litige.dart' show libelleDecision;
import 'depot_prestataire.dart';
import 'modeles_litiges.dart';
import 'widgets/composants_agenda.dart';

/// « Répondre au signalement » (maquette PrestaLitigeReponse) : ce que dit le client, ses preuves, puis la réponse du
/// prestataire. Une seule réponse par litige : une fois envoyée (ou si le litige est clos), l'écran est en lecture seule.
class LitigeReponsePage extends ConsumerStatefulWidget {
  const LitigeReponsePage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<LitigeReponsePage> createState() => _LitigeReponsePageState();
}

class _LitigeReponsePageState extends ConsumerState<LitigeReponsePage> {
  final _reponse = TextEditingController();
  bool _enCours = false;
  String? _erreurChamp;
  String? _echec;

  @override
  void dispose() {
    _reponse.dispose();
    super.dispose();
  }

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.litigesRecus);

  Future<void> _envoyer() async {
    if (_enCours) return;
    final texte = _reponse.text.trim();
    if (texte.isEmpty) {
      setState(() => _erreurChamp = 'Votre réponse est obligatoire.');
      return;
    }
    setState(() {
      _enCours = true;
      _erreurChamp = null;
      _echec = null;
    });
    try {
      await ref.read(depotPrestataireProvider).repondreLitige(widget.id, texte);
      ref.invalidate(litigesRecusProvider);
      ref.invalidate(litigeRecuProvider(widget.id));
      if (!mounted) return;
      setState(() => _enCours = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Réponse envoyée.')));
    } catch (e) {
      final erreur = ErreurApi.depuis(e);
      if (!mounted) return;
      final message = erreur is ErreurValidation
          ? messageServeur(erreur, 'reponse')
          : null;
      // Un refus (409 : déjà répondu, litige clos…) change l'état du litige : on le relit.
      if (message == null) {
        ref.invalidate(litigesRecusProvider);
        ref.invalidate(litigeRecuProvider(widget.id));
      }
      setState(() {
        _enCours = false;
        _erreurChamp = message;
        _echec = message == null ? erreur.message : null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final litige = ref.watch(litigeRecuProvider(widget.id));

    Widget corps;
    if (litige.isLoading && !litige.hasValue) {
      corps = const Padding(
        padding: EdgeInsets.fromLTRB(16, 16, 16, 0),
        child: Squelette(hauteur: 260, rayon: 30),
      );
    } else if (litige.hasError && !litige.hasValue) {
      corps = SizedBox(
        height: 320,
        child: EtatErreur(
          message: ErreurApi.depuis(litige.error!).message,
          onReessayer: () => ref.invalidate(litigeRecuProvider(widget.id)),
        ),
      );
    } else {
      corps = _Contenu(
        litige: litige.requireValue,
        reponse: _reponse,
        enCours: _enCours,
        erreurChamp: _erreurChamp,
        echec: _echec,
        onChange: () => setState(() {
          _erreurChamp = null;
          _echec = null;
        }),
      );
    }

    final peutRepondre =
        litige.hasValue &&
        !litige.requireValue.cloture &&
        !litige.requireValue.aRepondu;

    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: RefreshIndicator(
              onRefresh: () =>
                  ref.refresh(litigeRecuProvider(widget.id).future),
              edgeOffset: MediaQuery.paddingOf(context).top,
              child: ListView(
                padding: EdgeInsets.zero,
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  EnTeteDegrade(
                    titreLigne1: 'Répondre au',
                    titreLigne2: 'signalement',
                    onRetour: _retour,
                  ),
                  corps,
                  const SizedBox(height: 16),
                ],
              ),
            ),
          ),
          if (peutRepondre)
            Padding(
              padding: EdgeInsets.fromLTRB(
                16,
                8,
                16,
                MediaQuery.paddingOf(context).bottom + 24,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  BoutonPrimaire(
                    libelle: 'Envoyer ma réponse',
                    chargement: _enCours,
                    onPressed: _enCours ? null : _envoyer,
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Un administrateur examinera les deux versions.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      fontSize: 12,
                      color: CouleursSopi.texteDiscret,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _Contenu extends StatelessWidget {
  const _Contenu({
    required this.litige,
    required this.reponse,
    required this.enCours,
    required this.erreurChamp,
    required this.echec,
    required this.onChange,
  });

  final LitigeRecu litige;
  final TextEditingController reponse;
  final bool enCours;
  final String? erreurChamp;
  final String? echec;
  final VoidCallback onChange;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final etiquette = theme.textTheme.labelMedium?.copyWith(
      fontSize: 12,
      fontWeight: FontWeight.w600,
      color: CouleursSopi.texteSecondaire,
    );
    final (fond, texte) = switch (litige.onglet) {
      OngletLitiges.aTraiter => (
        CouleursSopi.ambrePastel,
        CouleursSopi.ambreProfond,
      ),
      OngletLitiges.enExamen => (
        CouleursSopi.indigoPastel,
        CouleursSopi.indigoTexte,
      ),
      OngletLitiges.clos => (
        CouleursSopi.bordure,
        CouleursSopi.texteSecondaire,
      ),
    };

    Widget bloc(String titre, Widget enfant) => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(titre, style: etiquette),
        const SizedBox(height: 6),
        enfant,
      ],
    );

    final carteSignalement = Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  litige.clientAffiche,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Container(
                height: 26,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: fond,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Text(
                  litige.onglet.libelle,
                  style: theme.textTheme.labelMedium?.copyWith(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: texte,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            '${litige.prestationNom} · ${formaterDate(litige.dateRendezVous)}',
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 13,
              color: CouleursSopi.texteDiscret,
            ),
          ),
          const SizedBox(height: 12),
          bloc(
            'Motif',
            Align(
              alignment: Alignment.centerLeft,
              child: Container(
                height: 34,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: CouleursSopi.marine,
                  borderRadius: BorderRadius.circular(17),
                ),
                child: Text(
                  litige.motif,
                  style: theme.textTheme.labelLarge?.copyWith(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: CouleursSopi.blanc,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          bloc(
            'Ce que dit le client',
            Text(
              litige.description,
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 14,
                height: 20 / 14,
              ),
            ),
          ),
          if (litige.preuves.isNotEmpty) ...[
            const SizedBox(height: 12),
            bloc(
              'Preuves du client',
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  for (final p in litige.preuves)
                    _Preuve(litigeId: litige.id, preuveId: p),
                ],
              ),
            ),
          ],
        ],
      ),
    );

    Widget? suite;
    if (litige.aRepondu) {
      // Réponse déjà envoyée (ou litige clos après réponse) : lecture seule.
      suite = Container(
        width: double.infinity,
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(30),
          border: Border.all(color: CouleursSopi.bordure),
        ),
        child: bloc(
          'Votre réponse',
          Text(
            litige.reponsePrestataire!,
            key: const ValueKey('reponse-envoyee'),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontSize: 14,
              height: 20 / 14,
            ),
          ),
        ),
      );
    } else if (!litige.cloture) {
      suite = Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: CouleursSopi.blanc,
          borderRadius: BorderRadius.circular(30),
          border: Border.all(color: CouleursSopi.bordure),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Votre réponse', style: etiquette),
            const SizedBox(height: 6),
            SizedBox(
              height: 88,
              child: TextField(
                key: const ValueKey('champ-reponse'),
                controller: reponse,
                enabled: !enCours,
                expands: true,
                maxLines: null,
                minLines: null,
                textAlignVertical: TextAlignVertical.top,
                keyboardType: TextInputType.multiline,
                onChanged: (_) => onChange(),
                style: theme.textTheme.bodyLarge?.copyWith(
                  fontSize: 14,
                  height: 20 / 14,
                ),
                decoration: InputDecoration(
                  filled: true,
                  fillColor: CouleursSopi.champ,
                  contentPadding: const EdgeInsets.all(14),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(18),
                    borderSide: BorderSide(
                      color: erreurChamp != null
                          ? CouleursSopi.erreurTexte
                          : CouleursSopi.bordureForte,
                    ),
                  ),
                  disabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(18),
                    borderSide: const BorderSide(
                      color: CouleursSopi.bordureForte,
                    ),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(18),
                    borderSide: BorderSide(
                      color: erreurChamp != null
                          ? CouleursSopi.erreurTexte
                          : CouleursSopi.bleu,
                      width: 1.5,
                    ),
                  ),
                ),
              ),
            ),
            if (erreurChamp != null) MessageChamp(erreurChamp!),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 0),
      child: Transform.translate(
        offset: Offset.zero,
        child: Column(
          children: [
            const SizedBox(height: 16),
            carteSignalement,
            if (litige.cloture) ...[
              const SizedBox(height: 16),
              BandeauInfo(
                message: litige.motifDecision == null
                    ? libelleDecision(litige.decision)
                    : '${libelleDecision(litige.decision)}. ${litige.motifDecision}',
              ),
            ],
            if (suite != null) ...[const SizedBox(height: 16), suite],
            if (echec != null) ...[
              const SizedBox(height: 16),
              BandeauErreur.alerte(message: echec!),
            ],
          ],
        ),
      ),
    );
  }
}

/// Vignette d'une preuve du client : les octets sont lus avec le jeton, puis affichés (64 × 64 comme la maquette).
class _Preuve extends ConsumerWidget {
  const _Preuve({required this.litigeId, required this.preuveId});

  final String litigeId;
  final String preuveId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(
      preuveLitigeProvider((litigeId: litigeId, preuveId: preuveId)),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        width: 64,
        height: 64,
        child: async.when(
          data: (octets) => Image.memory(
            octets,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => const _PreuveIndisponible(),
          ),
          error: (_, _) => const _PreuveIndisponible(),
          loading: () => const ColoredBox(color: CouleursSopi.champ),
        ),
      ),
    );
  }
}

class _PreuveIndisponible extends StatelessWidget {
  const _PreuveIndisponible();

  @override
  Widget build(BuildContext context) => const ColoredBox(
    color: CouleursSopi.champ,
    child: Icon(Icons.broken_image_outlined, color: CouleursSopi.texteDiscret),
  );
}
