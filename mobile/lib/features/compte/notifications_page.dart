import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/erreurs.dart';
import '../../core/theme/couleurs.dart';
import '../../core/widgets/boutons.dart';
import '../../core/widgets/liste_infinie.dart';
import '../reservation/depot_reservation.dart';
import 'depot_compte.dart';
import 'modeles_notification.dart';

const messageAucuneNotification = 'Aucune notification pour le moment.';

/// Notifications dans l'application (maquette Notifications), ouvertes depuis la cloche de l'accueil.
class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({super.key});

  @override
  ConsumerState<NotificationsPage> createState() => _EtatNotifications();
}

class _EtatNotifications extends ConsumerState<NotificationsPage> {
  final _controleur = ControleurListe<NotificationClient>();
  bool _toutEnCours = false;

  void _afficherErreur(Object e) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(ErreurApi.depuis(e).message)));
  }

  Future<void> _toutMarquer() async {
    setState(() => _toutEnCours = true);
    try {
      await ref.read(depotCompteProvider).toutMarquerLu();
      _controleur.transformerTous((n) => n.copieLue());
      ref.invalidate(notificationsNonLuesProvider);
    } catch (e) {
      _afficherErreur(e);
    } finally {
      if (mounted) setState(() => _toutEnCours = false);
    }
  }

  Future<void> _ouvrir(NotificationClient n) async {
    final destination = destinationNotification(n);
    if (!n.lue) {
      _controleur.modifier((x) => x.id == n.id, (x) => x.copieLue());
      try {
        await ref.read(depotCompteProvider).marquerLue(n.id);
        ref.invalidate(notificationsNonLuesProvider);
      } catch (_) {
        // Échec silencieux : la notification reste lue à l'écran et le serveur la renverra non lue au prochain rechargement.
      }
    }
    if (!mounted || destination == null) return;
    context.push(destination);
  }

  @override
  Widget build(BuildContext context) {
    final depot = ref.watch(depotCompteProvider);
    final maintenant = ref.watch(horlogeProvider);
    return Scaffold(
      body: ListeInfinie<NotificationClient>(
        titreLigne1: 'Notifications',
        controleur: _controleur,
        charger: depot.notifications,
        messageVide: messageAucuneNotification,
        iconeVide: Icons.notifications_none_rounded,
        action: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 250),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: BoutonCompact(
              libelle: 'Tout marquer comme lu',
              ton: TonBoutonCompact.clair,
              chargement: _toutEnCours,
              onPressed: _toutEnCours ? null : _toutMarquer,
            ),
          ),
        ),
        construire: (context, n) => _CarteNotification(
          notification: n,
          maintenant: maintenant(),
          onTap: () => _ouvrir(n),
        ),
      ),
    );
  }
}

class _CarteNotification extends StatelessWidget {
  const _CarteNotification({
    required this.notification,
    required this.maintenant,
    required this.onTap,
  });

  final NotificationClient notification;
  final DateTime maintenant;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final n = notification;
    final theme = Theme.of(context);
    final fortIcone = !n.lue && n.type == 'reservation_confirmee';
    final (fondIcone, couleurIcone) = n.lue
        ? (CouleursSopi.ardoisePastel, CouleursSopi.ardoiseTexte)
        : fortIcone
        ? (CouleursSopi.bleu, CouleursSopi.blanc)
        : (CouleursSopi.bleuPastel, CouleursSopi.bleu);
    return Material(
      color: CouleursSopi.blanc,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
        side: BorderSide(
          color: n.lue ? CouleursSopi.bordure : CouleursSopi.enteteTacheBleue,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: fondIcone,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  iconeNotification(n.type),
                  size: 20,
                  color: couleurIcone,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      n.titre,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontSize: 14,
                        fontWeight: n.lue ? FontWeight.w500 : FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      n.corps,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 13,
                        height: 18 / 13,
                        color: CouleursSopi.texteSecondaire,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      dateRelative(n.dateCreation, maintenant),
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 11,
                        color: CouleursSopi.texteDiscret,
                      ),
                    ),
                  ],
                ),
              ),
              if (!n.lue)
                Semantics(
                  label: 'Non lue',
                  child: Container(
                    key: const ValueKey('pastille-non-lue'),
                    width: 10,
                    height: 10,
                    margin: const EdgeInsets.only(top: 6, left: 12),
                    decoration: const BoxDecoration(
                      color: CouleursSopi.bleu,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
