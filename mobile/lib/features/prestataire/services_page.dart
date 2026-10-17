import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/routing/chemins.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/en_tete_degrade.dart';
import '../../core/widgets/etats.dart';
import '../reservation/formats.dart';
import 'depot_prestataire.dart';
import 'modeles.dart';

/// Mes services et tarifs (maquette Services) : actifs et désactivés, modification, ajout.
/// Pas de suppression : un service se désactive depuis son formulaire.
class ServicesPage extends ConsumerWidget {
  const ServicesPage({super.key});

  void _retour(BuildContext context) =>
      context.canPop() ? context.pop() : context.go(Chemins.tableau);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final services = ref.watch(prestationsProvider);
    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                EnTeteDegrade(
                  titreLigne1: 'Mes services',
                  titreLigne2: 'et tarifs',
                  onRetour: () => _retour(context),
                ),
                const SizedBox(height: 24),
                ...services.when(
                  loading: () => [
                    const SizedBox(height: 240, child: EtatChargement()),
                  ],
                  error: (e, _) => [
                    SizedBox(
                      height: 280,
                      child: EtatErreur(
                        message: ErreurApi.depuis(e).message,
                        onReessayer: () => ref.invalidate(prestationsProvider),
                      ),
                    ),
                  ],
                  data: (liste) => liste.isEmpty
                      ? [
                          const SizedBox(
                            height: 280,
                            child: EtatVide(
                              titre: 'Aucun service',
                              message:
                                  'Ajoutez votre premier service pour être réservable.',
                              icone: Icons.content_cut_rounded,
                            ),
                          ),
                        ]
                      : [
                          for (final service in liste)
                            Padding(
                              padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                              child: _CarteService(service: service),
                            ),
                        ],
                ),
                const SizedBox(height: 14),
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
            child: BoutonPrimaire(
              libelle: 'Ajouter un service',
              icone: Icons.add_rounded,
              onPressed: () => context.push(Chemins.serviceNouveau),
            ),
          ),
        ],
      ),
    );
  }
}

class _CarteService extends StatelessWidget {
  const _CarteService({required this.service});

  final PrestationService service;

  @override
  Widget build(BuildContext context) {
    final actif = service.actif;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: actif ? CouleursSopi.blanc : CouleursSopi.ardoisePastel,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: CouleursSopi.bordure),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  service.nom,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: actif
                        ? CouleursSopi.marine
                        : CouleursSopi.texteDiscret,
                  ),
                ),
                Text(
                  actif ? formaterDuree(service.dureeMinutes) : 'Désactivé',
                  style: TextStyle(
                    fontSize: 12,
                    color: actif
                        ? CouleursSopi.texteDiscret
                        : CouleursSopi.texteDiscret,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text(
            formaterArgent(service.prix),
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: actif ? CouleursSopi.marine : CouleursSopi.texteDiscret,
            ),
          ),
          const SizedBox(width: 12),
          Semantics(
            button: true,
            label: 'Modifier ${service.nom}',
            excludeSemantics: true,
            child: Material(
              color: actif ? CouleursSopi.enteteFond : CouleursSopi.blanc,
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: () => context.push(Chemins.serviceModifier(service.id)),
                child: SizedBox(
                  width: 40,
                  height: 40,
                  child: Icon(
                    Icons.edit_outlined,
                    size: 18,
                    color: actif
                        ? CouleursSopi.bleu
                        : CouleursSopi.texteDiscret,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
