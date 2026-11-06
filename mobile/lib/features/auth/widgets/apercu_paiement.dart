import 'package:flutter/material.dart';

import '../../../core/format/formateurs.dart';
import '../../../core/theme/couleurs.dart';

/// Chiffres de la démonstration (règles métier : acompte 30 %, commission 10 %, crédit = acompte − commission),
/// pour un tarif de 15 000 FCFA.
const _acompte = 4500;
const _credit = 3000;

const _vert = Color(0xFF16A34A);

double _etape(
  double t,
  double debut,
  double fin, [
  Curve c = Curves.easeOutCubic,
]) {
  if (t <= debut) return 0;
  if (t >= fin) return 1;
  return c.transform((t - debut) / (fin - debut));
}

/// Aperçu décoratif côté client : l'acompte monte jusqu'à 4 500 FCFA, puis « Payer » devient « Payé ✓ ».
/// [boucle] (0 → 1) rejoue la scène ; sans animation, l'état final est affiché.
class ApercuPaiementClient extends StatelessWidget {
  const ApercuPaiementClient({super.key, this.boucle});

  final Animation<double>? boucle;

  @override
  Widget build(BuildContext context) {
    final b = boucle;
    if (b == null) return _contenu(1, 1, 1);
    return AnimatedBuilder(
      animation: b,
      builder: (context, _) {
        final t = b.value;
        // Pause, montée du montant, appui sur le bouton, puis retour à zéro juste avant la boucle.
        final montant = _etape(t, 0.08, 0.42, Curves.easeOutQuart);
        final paye = _etape(t, 0.52, 0.58, Curves.easeOut);
        final reprise = _etape(t, 0.94, 1.0, Curves.easeIn);
        return _contenu(montant * (1 - reprise), paye * (1 - reprise), t);
      },
    );
  }

  Widget _contenu(double montant, double paye, double t) {
    final appui = (t > 0.5 && t < 0.56) ? 0.94 : 1.0;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: CouleursSopi.bleuPale,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 6, 10, 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Acompte 30 %',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: CouleursSopi.texteSecondaire,
              ),
            ),
            const SizedBox(height: 2),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                formaterArgent(_acompte * montant),
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: CouleursSopi.texte,
                  fontFeatures: [FontFeature.tabularFigures()],
                ),
              ),
            ),
            const SizedBox(height: 6),
            Transform.scale(
              scale: appui,
              child: Container(
                height: 24,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Color.lerp(CouleursSopi.bleu, _vert, paye),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  paye >= 0.5 ? 'Payé ✓' : 'Payer',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: CouleursSopi.blanc,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Aperçu décoratif côté artisan : une fois l'acompte payé, le portefeuille est crédité de 3 000 FCFA
/// (acompte moins commission).
class ApercuCreditArtisan extends StatelessWidget {
  const ApercuCreditArtisan({super.key, this.boucle});

  final Animation<double>? boucle;

  @override
  Widget build(BuildContext context) {
    final b = boucle;
    if (b == null) return _contenu(1);
    return AnimatedBuilder(
      animation: b,
      builder: (context, _) {
        final t = b.value;
        final credit = _etape(t, 0.6, 0.82, Curves.easeOutQuart);
        final reprise = _etape(t, 0.94, 1.0, Curves.easeIn);
        return _contenu(credit * (1 - reprise));
      },
    );
  }

  Widget _contenu(double credit) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: CouleursSopi.succesFond,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 6, 10, 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Portefeuille',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: CouleursSopi.texteSecondaire,
              ),
            ),
            const SizedBox(height: 2),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                '+${formaterArgent(_credit * credit)}',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: CouleursSopi.succesTexte,
                  fontFeatures: [FontFeature.tabularFigures()],
                ),
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Net de commission',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color: CouleursSopi.succesTexte,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
