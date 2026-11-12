import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/states.dart';
import '../../auth/application/auth_controller.dart';

/// "My account" tab: entry points to the remaining screens.
class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final user = ref.watch(authControllerProvider).user;

    final groups = [
      [
        (Icons.person_outline_rounded, l.profileTitle, Routes.profile),
        (Icons.folder_open_rounded, l.documentsTitle, Routes.documents),
        (Icons.face_retouching_natural_outlined, l.faceTitle, Routes.face),
      ],
      [
        (Icons.support_agent_rounded, l.hrRequestsTitle, Routes.requests),
        (Icons.payments_outlined, l.loansTitle, Routes.loans),
        (Icons.campaign_outlined, l.announcementsTitle, Routes.announcements),
        (
          Icons.notifications_none_rounded,
          l.notificationsTitle,
          Routes.notifications,
        ),
      ],
      [(Icons.settings_outlined, l.settingsTitle, Routes.settings)],
    ];

    return Scaffold(
      appBar: AppBar(title: Text(l.navAccount)),
      body: ListView(
        padding: const EdgeInsets.all(AppSizes.pagePadding),
        children: [
          if (user != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: t.actionPrimarySubtle,
                    child: Icon(Icons.person_rounded, color: t.actionPrimary),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user.name,
                          style: AppType.headingH5.copyWith(
                            color: t.textPrimary,
                          ),
                        ),
                        Text(
                          user.email,
                          textDirection: TextDirection.ltr,
                          style: AppType.captionMd.copyWith(
                            color: t.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          for (final group in groups) ...[
            Card(
              child: Column(
                children: [
                  for (final (icon, label, route) in group)
                    ListTile(
                      leading: Icon(icon),
                      title: Text(label),
                      trailing: const Icon(Icons.chevron_right_rounded),
                      onTap: () => context.push(route),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],
          OutlinedButton.icon(
            key: const Key('logout'),
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
        ],
      ),
    );
  }
}
