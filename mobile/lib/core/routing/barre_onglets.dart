import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

class OngletSopi {
  const OngletSopi({required this.libelle, required this.icone});

  final String libelle;
  final IconData icone;
}

/// Barre d'onglets flottante : pilule blanche à ombre douce (68 de haut, rayon 34), onglet actif en pilule bleue.
class BarreOnglets extends StatelessWidget {
  const BarreOnglets({
    super.key,
    required this.onglets,
    required this.indexActif,
    required this.onSelection,
  });

  final List<OngletSopi> onglets;
  final int indexActif;
  final ValueChanged<int> onSelection;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          DimensionsSopi.margeEcran,
          0,
          DimensionsSopi.margeEcran,
          14,
        ),
        child: Container(
          height: DimensionsSopi.hauteurBarreOnglets,
          padding: const EdgeInsets.symmetric(horizontal: 8),
          decoration: BoxDecoration(
            color: CouleursSopi.blanc,
            boxShadow: OmbresSopi.barreBas,
            borderRadius: BorderRadius.circular(
              DimensionsSopi.rayonBarreOnglets,
            ),
          ),
          child: Row(
            children: [
              for (var i = 0; i < onglets.length; i++)
                Expanded(
                  child: _Onglet(
                    onglet: onglets[i],
                    actif: i == indexActif,
                    onTap: () => onSelection(i),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Onglet extends StatelessWidget {
  const _Onglet({
    required this.onglet,
    required this.actif,
    required this.onTap,
  });

  final OngletSopi onglet;
  final bool actif;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: actif,
      label: onglet.libelle,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: Center(
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            height: 48,
            padding: EdgeInsets.symmetric(horizontal: actif ? 14 : 10),
            decoration: BoxDecoration(
              color: actif ? CouleursSopi.bleu : Colors.transparent,
              borderRadius: BorderRadius.circular(24),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  onglet.icone,
                  size: 22,
                  color: actif
                      ? CouleursSopi.blanc
                      : CouleursSopi.iconeInactive,
                ),
                if (actif) ...[
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      onglet.libelle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: CouleursSopi.blanc,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
