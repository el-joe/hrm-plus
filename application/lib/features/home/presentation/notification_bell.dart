import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../notifications/data/notifications_repository.dart';

class NotificationBell extends ConsumerWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(unreadCountProvider).value ?? 0;
    final f = ref.watch(formatterProvider);
    return IconButton(
      key: const Key('notification-bell'),
      tooltip: context.l10n.notificationsTitle,
      onPressed: () async {
        await context.push(Routes.notifications);
        ref.invalidate(unreadCountProvider);
      },
      icon: Badge(
        isLabelVisible: count > 0,
        label: Text(f.d(count > 99 ? '99+' : '$count')),
        child: const Icon(Icons.notifications_none_rounded),
      ),
    );
  }
}
