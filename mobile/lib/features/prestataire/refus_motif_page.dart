import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/en_tete_degrade.dart';
import 'actions_demande.dart';
import 'depot_prestataire.dart';

const motifMin = 3;
const motifMax = 500;

/// Motif du refus (maquette RefusMotif) : 3 à 500 caractères, montré au client.
class RefusMotifPage extends ConsumerStatefulWidget {
  const RefusMotifPage({super.key, required this.id});

  final String id;

  @override
  ConsumerState<RefusMotifPage> createState() => _RefusMotifPageState();
}

class _RefusMotifPageState extends ConsumerState<RefusMotifPage> {
  final _controleur = TextEditingController();
  bool _enCours = false;

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  String get _motif => _controleur.text.trim();
  bool get _valide => _motif.length >= motifMin && _motif.length <= motifMax;

  void _retour() =>
      context.canPop() ? context.pop() : context.go(Chemins.tableau);

  Future<void> _confirmer() async {
    setState(() => _enCours = true);
    final ok = await refuserDemande(context, ref, widget.id, _motif);
    if (!mounted) return;
    setState(() => _enCours = false);
    // Refusée : on revient. Sinon (409…), le message est affiché et la demande relue.
    if (ok) {
      // Retour à l'écran d'où l'on venait (détail ou liste) ; le détail se relit.
      _retour();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final demande = ref.watch(detailDemandeProvider(widget.id)).value;
    final longueur = _controleur.text.trim().length;

    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteDegrade(
                  titreLigne1: 'Refuser',
                  titreLigne2: 'la demande',
                  onRetour: _retour,
                ),
                if (demande != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 18,
                        vertical: 16,
                      ),
                      decoration: BoxDecoration(
                        color: CouleursSopi.blanc,
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: CouleursSopi.bordure),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  demande.clientAffiche,
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                              Text(
                                formaterDate(demande.debut),
                                style: theme.textTheme.bodySmall?.copyWith(
                                  fontSize: 12,
                                  color: CouleursSopi.texteDiscret,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${demande.prestationNom} · ${formaterArgent(demande.prix)}',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 13,
                              color: CouleursSopi.texteSecondaire,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                  child: Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: CouleursSopi.blanc,
                      borderRadius: BorderRadius.circular(30),
                      border: Border.all(color: CouleursSopi.bordure),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Motif du refus (obligatoire)',
                          style: theme.textTheme.labelLarge?.copyWith(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: CouleursSopi.texteSecondaire,
                          ),
                        ),
                        const SizedBox(height: 8),
                        SizedBox(
                          height: 130,
                          child: TextField(
                            controller: _controleur,
                            enabled: !_enCours,
                            expands: true,
                            maxLines: null,
                            minLines: null,
                            maxLength: motifMax,
                            textAlignVertical: TextAlignVertical.top,
                            keyboardType: TextInputType.multiline,
                            onChanged: (_) => setState(() {}),
                            style: theme.textTheme.bodyLarge?.copyWith(
                              fontSize: 15,
                              height: 21 / 15,
                            ),
                            buildCounter:
                                (
                                  _, {
                                  required currentLength,
                                  required isFocused,
                                  maxLength,
                                }) => null,
                            decoration: InputDecoration(
                              filled: true,
                              fillColor: CouleursSopi.champ,
                              contentPadding: const EdgeInsets.all(14),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(18),
                                borderSide: const BorderSide(
                                  color: CouleursSopi.bordureForte,
                                  width: 1.5,
                                ),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(18),
                                borderSide: const BorderSide(
                                  color: CouleursSopi.bleu,
                                  width: 1.5,
                                ),
                              ),
                              disabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(18),
                                borderSide: const BorderSide(
                                  color: CouleursSopi.bordureForte,
                                  width: 1.5,
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                '$motifMin caractères minimum',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  fontSize: 12,
                                  color: CouleursSopi.texteDiscret,
                                ),
                              ),
                            ),
                            Text(
                              '$longueur / $motifMax',
                              key: const ValueKey('compteur-motif'),
                              style: theme.textTheme.bodySmall?.copyWith(
                                fontSize: 12,
                                color: CouleursSopi.texteDiscret,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.fromLTRB(16, 16, 16, 24),
                  child: BandeauInfo(
                    message:
                        'Le client verra ce motif et pourra choisir un autre créneau.',
                  ),
                ),
              ],
            ),
          ),
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
                BoutonMarine(
                  libelle: 'Confirmer le refus',
                  chargement: _enCours,
                  onPressed: _valide && !_enCours ? _confirmer : null,
                ),
                const SizedBox(height: 10),
                BoutonCompact(
                  libelle: 'Retour',
                  ton: TonBoutonCompact.contour,
                  hauteur: 50,
                  taille: 14,
                  pleineLargeur: true,
                  onPressed: _enCours ? null : _retour,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
