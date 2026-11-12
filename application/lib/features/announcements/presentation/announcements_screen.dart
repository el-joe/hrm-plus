import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_response.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/paged.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/announcements_repository.dart';

class AnnouncementsController extends PagedNotifier<Announcement> {
  @override
  Future<Paged<Announcement>> fetch(int page) =>
      ref.read(announcementsRepositoryProvider).list(page: page);
}

final announcementsProvider =
    AsyncNotifierProvider.autoDispose<
      AnnouncementsController,
      PagedState<Announcement>
    >(AnnouncementsController.new);

class AnnouncementsScreen extends ConsumerWidget {
  const AnnouncementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final controller = ref.read(announcementsProvider.notifier);

    return Scaffold(
      appBar: AppBar(title: Text(l.announcementsTitle)),
      body: PagedListView<Announcement>(
        value: ref.watch(announcementsProvider),
        onRefresh: controller.refresh,
        onLoadMore: controller.loadMore,
        empty: EmptyState(
          icon: Icons.campaign_outlined,
          title: l.noAnnouncements,
        ),
        itemBuilder: (context, a) {
          final Widget? badge = a.canAcknowledge
              ? StatusBadge(label: l.needsAcknowledgement, tone: Tone.warning)
              : !a.isRead
              ? StatusBadge(label: l.unread, tone: Tone.info)
              : null;
          return AppListTile(
            leadingIcon: a.isPinned
                ? Icons.push_pin_outlined
                : Icons.campaign_outlined,
            title: a.title,
            subtitle: '${a.typeLabel} · ${f.date(a.publishedAt?.toLocal())}',
            trailing: badge,
            onTap: () async {
              await context.push(Routes.announcement(a.id));
              ref.invalidate(announcementsProvider);
            },
          );
        },
      ),
    );
  }
}
