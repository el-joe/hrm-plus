import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app.dart';
import 'core/providers.dart';
import 'features/notifications/push/push_coordinator.dart';
import 'features/notifications/push/push_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();
  // Push is optional: without Firebase files the app runs with push disabled.
  final push = await FirebasePushService.tryCreate();

  runApp(
    ProviderScope(
      // Screens show their own error state with a retry button; no silent
      // automatic retries of failed requests.
      retry: (_, _) => null,
      overrides: [
        sharedPreferencesProvider.overrideWithValue(prefs),
        pushServiceProvider.overrideWithValue(push),
      ],
      child: const HrmPlusApp(),
    ),
  );
}
