import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';

/// Brand header + card used by login, forgot password and OTP
/// (DESIGN_REFERENCE "Employee login": card on surface/brand, radius 24).
class AuthLayout extends ConsumerWidget {
  const AuthLayout({
    super.key,
    required this.title,
    required this.subtitle,
    required this.child,
    this.showBack = false,
  });

  final String title;
  final String subtitle;
  final Widget child;
  final bool showBack;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = context.tokens;
    final l = context.l10n;
    final locale = ref.watch(settingsProvider).locale;
    return Scaffold(
      backgroundColor: t.surfaceBrand,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: t.textOnPrimary,
        automaticallyImplyLeading: showBack,
        actions: [
          TextButton.icon(
            key: const Key('auth-language'),
            style: TextButton.styleFrom(foregroundColor: t.textOnPrimary),
            onPressed: () => ref
                .read(settingsProvider.notifier)
                .setLocale(Locale(locale.languageCode == 'ar' ? 'en' : 'ar')),
            icon: const Icon(Icons.translate_rounded, size: 18),
            label: Text(l.switchLanguage),
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSizes.pagePadding),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(Icons.badge_outlined, size: 48, color: t.textOnPrimary),
                  const SizedBox(height: 12),
                  Text(
                    l.appName,
                    textAlign: TextAlign.center,
                    style: AppType.headingH3.copyWith(color: t.textOnPrimary),
                  ),
                  const SizedBox(height: 24),
                  Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: t.surfaceDefault,
                      borderRadius: BorderRadius.circular(AppSizes.radius2xl),
                      boxShadow: AppShadows.md,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          title,
                          style: AppType.headingH3.copyWith(
                            color: t.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          subtitle,
                          style: AppType.bodyMd.copyWith(
                            color: t.textSecondary,
                          ),
                        ),
                        const SizedBox(height: 20),
                        child,
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
