import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_response.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/paged.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/leave_repository.dart';

class LeaveRequestsController extends PagedNotifier<LeaveRequest> {
  @override
  Future<Paged<LeaveRequest>> fetch(int page) =>
      ref.read(leaveRepositoryProvider).requests(page: page);
}

final leaveRequestsProvider =
    AsyncNotifierProvider.autoDispose<
      LeaveRequestsController,
      PagedState<LeaveRequest>
    >(LeaveRequestsController.new);

class LeaveHistoryScreen extends ConsumerWidget {
  const LeaveHistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final controller = ref.read(leaveRequestsProvider.notifier);

    return Scaffold(
      appBar: AppBar(title: Text(l.leaveHistoryTitle)),
      floatingActionButton: FloatingActionButton(
        tooltip: l.newLeaveRequest,
        onPressed: () async {
          await context.push(Routes.newLeave);
          ref.invalidate(leaveRequestsProvider);
        },
        child: const Icon(Icons.add_rounded),
      ),
      body: PagedListView<LeaveRequest>(
        value: ref.watch(leaveRequestsProvider),
        onRefresh: controller.refresh,
        onLoadMore: controller.loadMore,
        empty: EmptyState(
          icon: Icons.beach_access_outlined,
          title: l.noLeaveRequests,
          action: FilledButton(
            onPressed: () => context.push(Routes.newLeave),
            child: Text(l.newLeaveRequest),
          ),
        ),
        itemBuilder: (context, request) {
          final start = f.date(Formatter.parseDate(request.startDate));
          final end = f.date(Formatter.parseDate(request.endDate));
          return AppListTile(
            leadingIcon: Icons.event_note_outlined,
            leadingColor: context.tokens.chartColor(request.color),
            title: request.typeName,
            subtitle:
                '${start == end ? start : '$start – $end'} · ${l.daysValue(f.decimal(request.days))}',
            trailing: StatusBadge(
              label: request.statusLabel,
              status: request.status,
            ),
            onTap: () async {
              await context.push(Routes.leaveRequest(request.id));
              ref.invalidate(leaveRequestsProvider);
            },
          );
        },
      ),
    );
  }
}
