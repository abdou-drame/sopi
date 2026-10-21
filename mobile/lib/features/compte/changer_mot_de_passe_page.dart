import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../auth/validateurs.dart';
import 'depot_compte.dart';
import 'mise_en_page_formulaire.dart';

const messageMotDePasseModifie = 'Votre mot de passe a été modifié.';

/// « Changer le mot de passe » (maquette ChangerMotDePasse). L'utilisateur reste connecté : le serveur
/// conserve le jeton courant et révoque seulement les autres.
class ChangerMotDePassePage extends ConsumerStatefulWidget {
  const ChangerMotDePassePage({super.key});

  @override
  ConsumerState<ChangerMotDePassePage> createState() => _EtatChangerMdp();
}

class _EtatChangerMdp extends ConsumerState<ChangerMotDePassePage> {
  static const _cles = [
    'mot_de_passe_actuel',
    'nouveau_mot_de_passe',
    'confirmation',
  ];

  final _actuel = TextEditingController();
  final _nouveau = TextEditingController();
  final _confirmation = TextEditingController();
  bool _enCours = false;
  String? _erreur;
  String? _succes;
  final Map<String, String?> _erreurs = {};

  @override
  void dispose() {
    _actuel.dispose();
    _nouveau.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  void _effacer(String cle) {
    if (_erreurs[cle] != null || _succes != null) {
      setState(() {
        _erreurs[cle] = null;
        _succes = null;
      });
    }
  }

  Future<void> _enregistrer() async {
    final erreurs = <String, String?>{
      'mot_de_passe_actuel': _actuel.text.isEmpty
          ? 'Saisissez votre mot de passe actuel.'
          : null,
      'nouveau_mot_de_passe': ValidateursAcces.motDePasse(_nouveau.text),
      'confirmation': ValidateursAcces.confirmation(
        _nouveau.text,
        _confirmation.text,
      ),
    };
    setState(() {
      _erreurs
        ..clear()
        ..addAll(erreurs);
      _erreur = null;
      _succes = null;
    });
    if (erreurs.values.any((e) => e != null)) return;
    if (_enCours) return;
    setState(() => _enCours = true);
    try {
      await ref
          .read(depotCompteProvider)
          .changerMotDePasse(
            actuel: _actuel.text,
            nouveau: _nouveau.text,
            confirmation: _confirmation.text,
          );
      if (!mounted) return;
      _actuel.clear();
      _nouveau.clear();
      _confirmation.clear();
      setState(() => _succes = messageMotDePasseModifie);
    } catch (e) {
      if (!mounted) return;
      final erreur = ErreurApi.depuis(e);
      setState(() {
        var trouve = false;
        if (erreur is ErreurValidation) {
          for (final cle in _cles) {
            final message = erreur.premierMessage(cle);
            _erreurs[cle] = message;
            if (message != null) trouve = true;
          }
        }
        if (!trouve) _erreur = erreur.message;
      });
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MiseEnPageFormulaire(
      titreLigne1: 'Changer le',
      titreLigne2: 'mot de passe',
      enCours: _enCours,
      erreur: _erreur,
      succes: _succes,
      onEnregistrer: _enregistrer,
      champs: [
        ChampFormulaire(
          libelle: 'Mot de passe actuel',
          controleur: _actuel,
          motDePasse: true,
          actionClavier: TextInputAction.next,
          erreur: _erreurs['mot_de_passe_actuel'],
          onChanged: (_) => _effacer('mot_de_passe_actuel'),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ChampFormulaire(
              libelle: 'Nouveau mot de passe',
              controleur: _nouveau,
              motDePasse: true,
              actionClavier: TextInputAction.next,
              erreur: _erreurs['nouveau_mot_de_passe'],
              onChanged: (_) => _effacer('nouveau_mot_de_passe'),
            ),
            const SizedBox(height: 6),
            Text(
              '8 caractères minimum.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                fontSize: 12,
                color: CouleursSopi.texteSecondaire,
              ),
            ),
          ],
        ),
        ChampFormulaire(
          libelle: 'Confirmer le nouveau mot de passe',
          controleur: _confirmation,
          motDePasse: true,
          actionClavier: TextInputAction.done,
          erreur: _erreurs['confirmation'],
          onChanged: (_) => _effacer('confirmation'),
        ),
      ],
    );
  }
}
