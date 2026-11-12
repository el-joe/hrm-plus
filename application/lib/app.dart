import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/l10n/l10n.dart';
import 'core/providers.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/application/auth_controller.dart';
import 'features/notifications/push/push_coordinator.dart';

class HrmPlusApp extends ConsumerStatefulWidget {
  const HrmPlusApp({super.key});

  @override
  ConsumerState<HrmPlusApp> createState() => _HrmPlusAppState();
}

class _HrmPlusAppState extends ConsumerState<HrmPlusApp> {
  StreamSubscription<String>? _routes;

  @override
  void initState() {
    super.initState();
    final push = ref.read(pushCoordinatorProvider);
    unawaited(push.start());
    _routes = push.routes.listen((route) {
      if (ref.read(authControllerProvider).isAuthenticated) {
        unawaited(ref.read(appRouterProvider).push(route));
      }
    });
  }

  @override
  void dispose() {
    unawaited(_routes?.cancel());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(settingsProvider);
    final router = ref.watch(appRouterProvider);

    // Open the notification that launched the app once signed in.
    ref.listen<AuthState>(authControllerProvider, (previous, next) {
      if (next.isAuthenticated && previous?.isAuthenticated != true) {
        final route = ref.read(pushCoordinatorProvider).takePendingRoute();
        if (route != null) unawaited(router.push(route));
      }
    });

    return MaterialApp.router(
      onGenerateTitle: (context) => context.l10n.appName,
      debugShowCheckedModeBanner: false,
      routerConfig: router,
      locale: settings.locale,
      supportedLocales: supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: settings.themeMode,
    );
  }
}
