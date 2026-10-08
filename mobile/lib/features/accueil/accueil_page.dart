import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'etat_api.dart';

class AccueilPage extends ConsumerWidget {
  const AccueilPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final etat = ref.watch(etatApiProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Sopi')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  "État de l'API",
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 16),
                etat.when(
                  loading: () => const Text("Chargement de l'état de l'API…"),
                  error: (erreur, _) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        "L'API est indisponible. $erreur",
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: () => ref.invalidate(etatApiProvider),
                        child: const Text('Réessayer'),
                      ),
                    ],
                  ),
                  data: (donnees) => Column(
                    children: [
                      _Ligne(libelle: 'API', ok: donnees.apiOk),
                      const SizedBox(height: 8),
                      _Ligne(libelle: 'Base de données', ok: donnees.baseOk),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Version'),
                          Text(donnees.version),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  const _Ligne({required this.libelle, required this.ok});

  final String libelle;
  final bool ok;

  @override
  Widget build(BuildContext context) {
    final couleur = ok ? Colors.green.shade700 : Colors.red.shade700;
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(libelle),
        Chip(
          label: Text(ok ? 'OK' : 'Erreur'),
          labelStyle: TextStyle(color: couleur, fontWeight: FontWeight.w600),
          backgroundColor: couleur.withValues(alpha: 0.12),
          side: BorderSide.none,
        ),
      ],
    );
  }
}
