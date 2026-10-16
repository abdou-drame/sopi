import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/couleurs.dart';

/// Champ de saisie : libellé au-dessus, message d'erreur rouge dessous. Avec [motDePasse], un œil affiche ou
/// masque la saisie.
class ChampTexte extends StatefulWidget {
  const ChampTexte({
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
    this.maxLines = 1,
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
  final int maxLines;

  @override
  State<ChampTexte> createState() => _ChampTexteState();
}

class _ChampTexteState extends State<ChampTexte> {
  bool _masque = true;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final aErreur = widget.erreur != null && widget.erreur!.isNotEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            widget.libelle,
            style: theme.textTheme.labelLarge?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        TextField(
          controller: widget.controleur,
          obscureText: widget.motDePasse && _masque,
          keyboardType: widget.clavier,
          textInputAction: widget.actionClavier,
          onChanged: widget.onChanged,
          onSubmitted: widget.onSubmitted,
          inputFormatters: widget.formateurs,
          autofillHints: widget.autofill,
          enabled: widget.enabled,
          maxLines: widget.motDePasse ? 1 : widget.maxLines,
          style: theme.textTheme.bodyLarge,
          decoration: InputDecoration(
            hintText: widget.indication,
            // Contour rouge quand une erreur est affichée sous le champ.
            enabledBorder: aErreur
                ? theme.inputDecorationTheme.errorBorder
                : theme.inputDecorationTheme.enabledBorder,
            focusedBorder: aErreur
                ? theme.inputDecorationTheme.focusedErrorBorder
                : theme.inputDecorationTheme.focusedBorder,
            suffixIcon: widget.motDePasse
                ? IconButton(
                    tooltip: _masque
                        ? 'Afficher le mot de passe'
                        : 'Masquer le mot de passe',
                    icon: Icon(
                      _masque
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                      color: CouleursSopi.texteDiscret,
                    ),
                    onPressed: () => setState(() => _masque = !_masque),
                  )
                : null,
          ),
        ),
        if (aErreur)
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
