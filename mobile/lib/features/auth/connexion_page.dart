import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import '../../core/theme/dimensions.dart';
import '../../core/widgets/bandeaux.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/champ_texte.dart';
import 'session.dart';

/// Écran de connexion PROVISOIRE (étape 4b-0) : sert à valider la chaîne API → session → navigation.
/// Il sera remplacé par la maquette validée dans un prompt d'écrans.
class ConnexionPage extends ConsumerStatefulWidget {
  const ConnexionPage({super.key});

  @override
  ConsumerState<ConnexionPage> createState() => _ConnexionPageState();
}

class _ConnexionPageState extends ConsumerState<ConnexionPage> {
  final _identifiant = TextEditingController();
  final _motDePasse = TextEditingController();
  bool _envoi = false;
  String? _erreurGlobale;
  ErreurValidation? _validation;

  @override
  void dispose() {
    _identifiant.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  Future<void> _envoyer() async {
    setState(() {
      _envoi = true;
      _erreurGlobale = null;
      _validation = null;
    });
    try {
      await ref
          .read(sessionProvider.notifier)
          .connecter(_identifiant.text.trim(), _motDePasse.text);
    } on ErreurValidation catch (e) {
      if (mounted) setState(() => _validation = e);
    } on ErreurApi catch (e) {
      if (mounted) setState(() => _erreurGlobale = e.message);
    } finally {
      if (mounted) setState(() => _envoi = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(DimensionsSopi.margeEcran),
          children: [
            const SizedBox(height: 32),
            Text(
              'Connexion',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 24),
            if (_erreurGlobale != null) ...[
              BandeauErreur(message: _erreurGlobale!),
              const SizedBox(height: 16),
            ],
            ChampTexte(
              libelle: 'E-mail ou téléphone',
              controleur: _identifiant,
              erreur: _validation?.premierMessage('identifiant'),
            ),
            const SizedBox(height: 16),
            ChampTexte(
              libelle: 'Mot de passe',
              controleur: _motDePasse,
              motDePasse: true,
              erreur: _validation?.premierMessage('password'),
            ),
            const SizedBox(height: 24),
            BoutonPrimaire(
              libelle: 'Se connecter',
              chargement: _envoi,
              onPressed: _envoi ? null : _envoyer,
            ),
          ],
        ),
      ),
    );
  }
}
