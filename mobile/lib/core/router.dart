import 'package:go_router/go_router.dart';

import '../features/accueil/accueil_page.dart';

final routeur = GoRouter(
  routes: [
    GoRoute(path: '/', builder: (context, state) => const AccueilPage()),
  ],
);
