import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/router.dart';

void main() {
  runApp(const ProviderScope(child: SopiApp()));
}

class SopiApp extends StatelessWidget {
  const SopiApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Sopi',
      theme: ThemeData(colorSchemeSeed: Colors.indigo, useMaterial3: true),
      routerConfig: routeur,
    );
  }
}
