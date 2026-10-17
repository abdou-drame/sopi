import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/routing/chemins.dart';
import '../../core/widgets/champ_formulaire.dart';
import 'formulaire_inscription.dart';
import 'mise_en_page_etape.dart';
import 'modeles.dart';
import 'validation_etapes.dart';

/// Étape 2 sur 3 : « Vos informations » (identité, contact, mot de passe).
class EtapeInformationsPage extends ConsumerStatefulWidget {
  const EtapeInformationsPage({super.key});

  @override
  ConsumerState<EtapeInformationsPage> createState() =>
      _EtapeInformationsPageState();
}

class _EtapeInformationsPageState extends ConsumerState<EtapeInformationsPage> {
  late final TextEditingController _prenom;
  late final TextEditingController _nom;
  late final TextEditingController _telephone;
  late final TextEditingController _email;
  late final TextEditingController _motDePasse;
  late final TextEditingController _confirmation;
  Map<String, String> _erreurs = {};
  bool _tentative = false;

  @override
  void initState() {
    super.initState();
    final d = ref.read(formulaireInscriptionProvider);
    _prenom = TextEditingController(text: d.prenom);
    _nom = TextEditingController(text: d.nom);
    _telephone = TextEditingController(text: d.telephone);
    _email = TextEditingController(text: d.email);
    _motDePasse = TextEditingController(text: d.motDePasse);
    _confirmation = TextEditingController(text: d.confirmation);
    // Erreurs du serveur (422) renvoyées depuis l'étape 3 : affichées sous les bons champs.
    const cles = {
      'prenom',
      'nom',
      'telephone',
      'email',
      'mot_de_passe',
      'mot_de_passe_confirmation',
    };
    final serveur = ref.read(erreursServeurInscriptionProvider);
    _erreurs = {
      for (final e in serveur.entries)
        if (cles.contains(e.key)) e.key: e.value,
    };
    _tentative = _erreurs.isNotEmpty;
    if (_tentative) {
      Future.microtask(() {
        if (mounted) {
          ref.read(erreursServeurInscriptionProvider.notifier).prendre(cles);
        }
      });
    }
  }

  @override
  void dispose() {
    for (final c in [
      _prenom,
      _nom,
      _telephone,
      _email,
      _motDePasse,
      _confirmation,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  void _modifier(DossierInscription Function(DossierInscription) f) {
    ref.read(formulaireInscriptionProvider.notifier).mettreAJour(f);
    if (!_tentative) return;
    setState(
      () => _erreurs = ValidationEtapes.etape2(
        ref.read(formulaireInscriptionProvider),
      ),
    );
  }

  void _continuer() {
    final erreurs = ValidationEtapes.etape2(
      ref.read(formulaireInscriptionProvider),
    );
    setState(() {
      _tentative = true;
      _erreurs = erreurs;
    });
    if (erreurs.isEmpty) context.push(Chemins.inscriptionJustificatifs);
  }

  void _retour() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go(Chemins.inscriptionPrestataire);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MiseEnPageEtape(
      etape: 2,
      titreLigne1: 'Vos',
      titreLigne2: 'informations',
      libelleBouton: 'Continuer',
      onContinuer: _continuer,
      onRetour: _retour,
      contenu: [
        CarteEtape(
          enfants: [
            ChampFormulaire(
              libelle: 'Prénom',
              controleur: _prenom,
              erreur: _erreurs['prenom'],
              actionClavier: TextInputAction.next,
              hauteur: 50,
              onChanged: (v) => _modifier((d) => d.copie(prenom: v)),
            ),
            ChampFormulaire(
              libelle: 'Nom',
              controleur: _nom,
              erreur: _erreurs['nom'],
              actionClavier: TextInputAction.next,
              hauteur: 50,
              onChanged: (v) => _modifier((d) => d.copie(nom: v)),
            ),
            ChampFormulaire(
              libelle: 'Téléphone',
              controleur: _telephone,
              erreur: _erreurs['telephone'],
              clavier: TextInputType.phone,
              actionClavier: TextInputAction.next,
              hauteur: 50,
              onChanged: (v) => _modifier((d) => d.copie(telephone: v)),
            ),
            ChampFormulaire(
              libelle: 'E-mail (pour le suivi de votre dossier)',
              controleur: _email,
              erreur: _erreurs['email'],
              clavier: TextInputType.emailAddress,
              actionClavier: TextInputAction.next,
              hauteur: 50,
              onChanged: (v) => _modifier((d) => d.copie(email: v)),
            ),
            ChampFormulaire(
              libelle: 'Mot de passe',
              controleur: _motDePasse,
              erreur: _erreurs['mot_de_passe'],
              motDePasse: true,
              actionClavier: TextInputAction.next,
              hauteur: 50,
              onChanged: (v) => _modifier((d) => d.copie(motDePasse: v)),
            ),
            ChampFormulaire(
              libelle: 'Confirmer le mot de passe',
              controleur: _confirmation,
              erreur: _erreurs['mot_de_passe_confirmation'],
              motDePasse: true,
              actionClavier: TextInputAction.done,
              hauteur: 50,
              onChanged: (v) => _modifier((d) => d.copie(confirmation: v)),
              onSubmitted: (_) => _continuer(),
            ),
          ],
        ),
      ],
    );
  }
}
