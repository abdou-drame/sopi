import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/erreurs.dart';
import '../../core/format/formateurs.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/champ_formulaire.dart';
import '../auth/session.dart';
import '../auth/validateurs.dart';
import 'depot_compte.dart';
import 'mise_en_page_formulaire.dart';

const messageProfilModifie = 'Votre profil a été mis à jour.';

/// « Modifier mon profil » (maquette ModifierProfil) : prénom, nom, téléphone. L'e-mail n'est pas modifiable.
class ModifierProfilPage extends ConsumerStatefulWidget {
  const ModifierProfilPage({super.key});

  @override
  ConsumerState<ModifierProfilPage> createState() => _EtatModifierProfil();
}

class _EtatModifierProfil extends ConsumerState<ModifierProfilPage> {
  late final TextEditingController _prenom;
  late final TextEditingController _nom;
  late final TextEditingController _telephone;
  bool _enCours = false;
  String? _erreur;
  String? _succes;
  final Map<String, String?> _erreurs = {};

  @override
  void initState() {
    super.initState();
    final etat = ref.read(sessionProvider);
    final u = etat is Connecte ? etat.utilisateur : null;
    _prenom = TextEditingController(text: u?.prenom ?? '');
    _nom = TextEditingController(text: u?.nom ?? '');
    _telephone = TextEditingController(
      text: formaterTelephone(u?.telephone ?? ''),
    );
  }

  @override
  void dispose() {
    _prenom.dispose();
    _nom.dispose();
    _telephone.dispose();
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
      'prenom': ValidateursAcces.nom(_prenom.text, 'Le prénom'),
      'nom': ValidateursAcces.nom(_nom.text, 'Le nom'),
      'telephone': ValidateursAcces.telephone(_telephone.text),
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
      final utilisateur = await ref
          .read(depotCompteProvider)
          .modifierProfil(
            prenom: _prenom.text.trim(),
            nom: _nom.text.trim(),
            telephone: ValidateursAcces.telephoneApi(_telephone.text)!,
          );
      if (!mounted) return;
      ref.read(sessionProvider.notifier).mettreAJourUtilisateur(utilisateur);
      setState(() => _succes = messageProfilModifie);
    } catch (e) {
      if (!mounted) return;
      final erreur = ErreurApi.depuis(e);
      setState(() {
        var trouve = false;
        if (erreur is ErreurValidation) {
          for (final cle in ['prenom', 'nom', 'telephone']) {
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
      titreLigne1: 'Modifier',
      titreLigne2: 'mon profil',
      enCours: _enCours,
      erreur: _erreur,
      succes: _succes,
      onEnregistrer: _enregistrer,
      champs: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: ChampFormulaire(
                libelle: 'Prénom',
                controleur: _prenom,
                hauteur: 50,
                margeInterne: 14,
                actionClavier: TextInputAction.next,
                erreur: _erreurs['prenom'],
                onChanged: (_) => _effacer('prenom'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ChampFormulaire(
                libelle: 'Nom',
                controleur: _nom,
                hauteur: 50,
                margeInterne: 14,
                actionClavier: TextInputAction.next,
                erreur: _erreurs['nom'],
                onChanged: (_) => _effacer('nom'),
              ),
            ),
          ],
        ),
        ChampFormulaire(
          libelle: 'Téléphone',
          controleur: _telephone,
          hauteur: 50,
          margeInterne: 14,
          clavier: TextInputType.phone,
          actionClavier: TextInputAction.done,
          erreur: _erreurs['telephone'],
          onChanged: (_) => _effacer('telephone'),
        ),
        Text(
          "L'adresse e-mail ne peut pas être modifiée ici. Pour la changer, contactez le support Sopi.",
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            fontSize: 12,
            height: 17 / 12,
            color: CouleursSopi.texteDiscret,
          ),
        ),
      ],
    );
  }
}
