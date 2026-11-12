import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/storage/preferences.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/data/auth_repository.dart';
import '../../notifications/push/push_coordinator.dart';

/// Settings and language: language, theme, digits, notification
/// preferences, push status, sign out.
class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final settings = ref.watch(settingsProvider);
    final notifier = ref.read(settingsProvider.notifier);
    final push = ref.watch(pushServiceProvider);
    final config = ref.watch(appConfigProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.settingsTitle)),
      body: ListView(
        padding: const EdgeInsets.all(AppSizes.pagePadding),
        children: [
          SectionTitle(l.language),
          SegmentedButton<String>(
            key: const Key('language-selector'),
            segments: [
              ButtonSegment(value: 'ar', label: Text(l.arabic)),
              ButtonSegment(value: 'en', label: Text(l.english)),
            ],
            selected: {settings.locale.languageCode},
            showSelectedIcon: false,
            onSelectionChanged: (value) {
              final code = value.first;
              unawaited(notifier.setLocale(Locale(code)));
              // Server texts and push notifications follow the account locale.
              unawaited(
                ref
                    .read(authRepositoryProvider)
                    .updateLocale(code)
                    .catchError((Object _) {}),
              );
            },
          ),
          const SizedBox(height: AppSizes.gap),
          SectionTitle(l.theme),
          SegmentedButton<ThemeMode>(
            segments: [
              ButtonSegment(
                value: ThemeMode.system,
                label: Text(l.themeSystem),
                icon: const Icon(Icons.brightness_auto_outlined),
              ),
              ButtonSegment(
                value: ThemeMode.light,
                label: Text(l.themeLight),
                icon: const Icon(Icons.light_mode_outlined),
              ),
              ButtonSegment(
                value: ThemeMode.dark,
                label: Text(l.themeDark),
                icon: const Icon(Icons.dark_mode_outlined),
              ),
            ],
            selected: {settings.themeMode},
            showSelectedIcon: false,
            onSelectionChanged: (value) => notifier.setThemeMode(value.first),
          ),
          const SizedBox(height: AppSizes.gap),
          SectionTitle(l.digits),
          SegmentedButton<DigitStyle>(
            segments: [
              ButtonSegment(
                value: DigitStyle.western,
                label: Text(l.digitsWestern),
              ),
              ButtonSegment(
                value: DigitStyle.arabicIndic,
                label: Text(l.digitsArabicIndic),
              ),
            ],
            selected: {settings.digits},
            showSelectedIcon: false,
            onSelectionChanged: (value) => notifier.setDigits(value.first),
          ),
          const SizedBox(height: AppSizes.gap),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.notifications_active_outlined),
                  title: Text(l.notificationPreferences),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push(Routes.notificationPreferences),
                ),
                ListTile(
                  leading: const Icon(Icons.face_retouching_natural_outlined),
                  title: Text(l.faceTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push(Routes.face),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSizes.gap),
          if (!push.available)
            InlineBanner(message: l.pushDisabled, tone: Tone.info),
          const SizedBox(height: AppSizes.gap),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(foregroundColor: t.actionDanger),
            onPressed: () async {
              final ok = await confirmDialog(
                context,
                title: l.logout,
                message: l.logoutConfirm,
                confirmLabel: l.logout,
                danger: true,
              );
              if (ok) await ref.read(authControllerProvider.notifier).logout();
            },
            icon: const Icon(Icons.logout_rounded),
            label: Text(l.logout),
          ),
          const SizedBox(height: 24),
          Center(
            child: Text(
              l.appVersion(config.appVersion),
              style: AppType.captionMd.copyWith(color: t.textTertiary),
            ),
          ),
        ],
      ),
    );
  }
}
