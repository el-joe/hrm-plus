import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_response.dart';
import '../../../core/providers.dart';
import '../../../core/router/deep_links.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/paged.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/notifications_repository.dart';

class NotificationsController extends PagedNotifier<AppNotification> {
  @override
  Future<Paged<AppNotification>> fetch(int page) =>
      ref.read(notificationsRepositoryProvider).list(page: page);
}

final notificationsProvider =
    AsyncNotifierProvider.autoDispose<
      NotificationsController,
      PagedState<AppNotification>
    >(NotificationsController.new);

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  Future<void> _open(
    BuildContext context,
    WidgetRef ref,
    AppNotification n,
  ) async {
    if (!n.isRead) {
      ref
          .read(notificationsProvider.notifier)
          .replaceWhere((x) => x.id == n.id, (x) => x.markedRead());
      try {
        await ref.read(notificationsRepositoryProvider).markRead(n.id);
      } catch (_) {
        // The list still shows it as read; the next refresh corrects it.
      }
      ref.invalidate(unreadCountProvider);
    }
    if (context.mounted) await context.push(routeForPayload(n.payload));
  }

  Future<void> _readAll(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(notificationsRepositoryProvider).markAllRead();
      ref.invalidate(notificationsProvider);
      ref.invalidate(unreadCountProvider);
    } catch (error) {
      if (context.mounted) {
        showSnack(
          context,
          failureMessage(context.l10n, error),
          tone: SnackTone.error,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final controller = ref.read(notificationsProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: Text(l.notificationsTitle),
        actions: [
          IconButton(
            tooltip: l.markAllRead,
            onPressed: () => _readAll(context, ref),
            icon: const Icon(Icons.done_all_rounded),
          ),
        ],
      ),
      body: PagedListView<AppNotification>(
        value: ref.watch(notificationsProvider),
        onRefresh: controller.refresh,
        onLoadMore: controller.loadMore,
        empty: EmptyState(
          icon: Icons.notifications_none_rounded,
          title: l.noNotifications,
        ),
        itemBuilder: (context, n) => AppCard(
          padding: const EdgeInsets.all(12),
          color: n.isRead ? null : t.actionPrimarySubtle,
          onTap: () => _open(context, ref, n),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: n.isRead ? Colors.transparent : t.actionPrimary,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(n.title, style: AppType.labelMd),
                    if (n.message.isNotEmpty)
                      Text(
                        n.message,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: AppType.bodySm.copyWith(color: t.textSecondary),
                      ),
                    const SizedBox(height: 4),
                    Text(
                      f.dateTime(n.createdAt),
                      style: AppType.captionSm.copyWith(color: t.textTertiary),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
