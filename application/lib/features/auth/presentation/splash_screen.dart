import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/ui.dart';
import '../application/auth_controller.dart';
import '../data/auth_repository.dart';

/// Restores the stored session (`auth/me`) and checks the app config
/// (minimum version). The router guard moves on once the state is known.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  bool _updateRequired = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _boot());
  }

  Future<void> _boot() async {
    final config = await ref.read(remoteConfigProvider.future);
    if (!mounted) return;
    if (config.updateRequired(ref.read(appConfigProvider).appVersion)) {
      setState(() => _updateRequired = true);
      return;
    }
    await ref.read(authControllerProvider.notifier).restore();
  }

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final l = context.l10n;
    return Scaffold(
      backgroundColor: t.surfaceBrand,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 88,
                  height: 88,
                  decoration: BoxDecoration(
                    color: t.textOnPrimary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(AppSizes.radius2xl),
                  ),
                  child: Icon(
                    Icons.badge_outlined,
                    size: 44,
                    color: t.textOnPrimary,
                  ),
                ),
                const SizedBox(height: 20),
                Text(
                  l.appName,
                  style: AppType.headingH2.copyWith(color: t.textOnPrimary),
                ),
                const SizedBox(height: 4),
                Text(
                  l.appTagline,
                  style: AppType.bodyMd.copyWith(color: t.textOnBrandMuted),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 32),
                if (_updateRequired)
                  InlineBanner(
                    title: l.updateRequiredTitle,
                    message: l.updateRequiredMessage,
                    tone: Tone.warning,
                    icon: Icons.system_update_rounded,
                  )
                else
                  SizedBox(
                    width: 28,
                    height: 28,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.6,
                      color: t.textOnPrimary,
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
