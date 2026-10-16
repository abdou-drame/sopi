import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/format/formateurs.dart';
import 'core/routing/routeur.dart';
import 'core/theme/theme_sopi.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initialiserFormats();
  runApp(const ProviderScope(child: SopiApp()));
}

class SopiApp extends ConsumerWidget {
  const SopiApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp.router(
      title: 'Sopi',
      debugShowCheckedModeBanner: false,
      theme: ThemeSopi.construire(),
      locale: const Locale('fr', 'FR'),
      supportedLocales: const [Locale('fr', 'FR')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: ref.watch(routeurProvider),
    );
  }
}
