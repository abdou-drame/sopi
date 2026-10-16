import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

/// Champ des écrans d'accès, au format des maquettes : libellé 12 px / 600 en bleu-gris à 6 px au-dessus,
/// champ de [hauteur] (52 par défaut, 50 pour l'inscription), 15 px, marge intérieure 16, bordure #d5ddf0.
/// Une erreur passe la bordure au rouge et s'affiche sous le champ.
class ChampFormulaire extends StatefulWidget {
  const ChampFormulaire({
    super.key,
    required this.libelle,
    this.controleur,
    this.indication,
    this.erreur,
    this.motDePasse = false,
    this.clavier,
    this.actionClavier,
    this.onChanged,
    this.onSubmitted,
    this.formateurs,
    this.autofill,
    this.enabled = true,
    this.hauteur = DimensionsSopi.hauteurChampFormulaire,
    this.margeInterne = 16,
    this.erreurSansTexte = false,
  });

  final String libelle;
  final TextEditingController? controleur;
  final String? indication;
  final String? erreur;
  final bool motDePasse;
  final TextInputType? clavier;
  final TextInputAction? actionClavier;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final List<TextInputFormatter>? formateurs;
  final Iterable<String>? autofill;
  final bool enabled;
  final double hauteur;
  final double margeInterne;

  /// Bordure rouge sans message (le message est affiché ailleurs, par exemple dans un bandeau).
  final bool erreurSansTexte;

  @override
  State<ChampFormulaire> createState() => _ChampFormulaireState();
}

class _ChampFormulaireState extends State<ChampFormulaire> {
  bool _masque = true;

  OutlineInputBorder _bordure(Color couleur, [double epaisseur = 1]) =>
      OutlineInputBorder(
        borderRadius: BorderRadius.circular(DimensionsSopi.rayonChamp),
        borderSide: BorderSide(color: couleur, width: epaisseur),
      );

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final aTexteErreur = widget.erreur != null && widget.erreur!.isNotEmpty;
    final enErreur = aTexteErreur || widget.erreurSansTexte;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          widget.libelle,
          style: theme.textTheme.labelMedium?.copyWith(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: CouleursSopi.texteSecondaire,
          ),
        ),
        const SizedBox(height: 6),
        SizedBox(
          height: widget.hauteur,
          child: TextField(
            controller: widget.controleur,
            obscureText: widget.motDePasse && _masque,
            keyboardType: widget.clavier,
            textInputAction: widget.actionClavier,
            onChanged: widget.onChanged,
            onSubmitted: widget.onSubmitted,
            inputFormatters: widget.formateurs,
            autofillHints: widget.autofill,
            enabled: widget.enabled,
            maxLines: 1,
            // Le défilement de l'écran laisse toujours le champ visible au-dessus du clavier.
            scrollPadding: const EdgeInsets.only(bottom: 120),
            style: theme.textTheme.bodyLarge?.copyWith(
              fontSize: 15,
              fontWeight: FontWeight.w400,
              color: CouleursSopi.marine,
            ),
            decoration: InputDecoration(
              hintText: widget.indication,
              isDense: true,
              contentPadding: EdgeInsets.symmetric(
                horizontal: widget.margeInterne,
              ),
              enabledBorder: _bordure(
                enErreur ? CouleursSopi.erreurTexte : CouleursSopi.bordureForte,
              ),
              disabledBorder: _bordure(
                enErreur ? CouleursSopi.erreurTexte : CouleursSopi.bordureForte,
              ),
              focusedBorder: _bordure(
                enErreur ? CouleursSopi.erreurTexte : CouleursSopi.bleu,
                1.5,
              ),
              suffixIcon: widget.motDePasse
                  ? IconButton(
                      tooltip: _masque
                          ? 'Afficher le mot de passe'
                          : 'Masquer le mot de passe',
                      icon: Icon(
                        _masque
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                        size: 22,
                        color: CouleursSopi.texteDiscret,
                      ),
                      onPressed: () => setState(() => _masque = !_masque),
                    )
                  : null,
            ),
          ),
        ),
        if (aTexteErreur)
          Padding(
            padding: const EdgeInsets.only(left: 6, top: 6),
            child: Text(
              widget.erreur!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: CouleursSopi.erreurTexte,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
  }
}
