import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_response.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/widgets/paged.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/requests_repository.dart';

class RequestsController extends PagedNotifier<HrRequestSummary> {
  RequestsController(this.status);

  /// open | closed
  final String status;

  @override
  Future<Paged<HrRequestSummary>> fetch(int page) =>
      ref.read(requestsRepositoryProvider).list(page: page, status: status);
}

final requestsProvider = AsyncNotifierProvider.autoDispose
    .family<RequestsController, PagedState<HrRequestSummary>, String>(
      RequestsController.new,
    );

/// HR requests (letters, inquiries, profile changes), open and closed.
class RequestsScreen extends ConsumerWidget {
  const RequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.hrRequestsTitle),
          bottom: TabBar(
            tabs: [
              Tab(text: l.tabOpen),
              Tab(text: l.tabClosed),
            ],
          ),
        ),
        floatingActionButton: FloatingActionButton.extended(
          key: const Key('new-request'),
          onPressed: () async {
            await context.push(Routes.newRequest);
            ref.invalidate(requestsProvider);
          },
          icon: const Icon(Icons.add_rounded),
          label: Text(l.newRequest),
        ),
        body: const TabBarView(
          children: [
            _RequestList(status: 'open'),
            _RequestList(status: 'closed'),
          ],
        ),
      ),
    );
  }
}

class _RequestList extends ConsumerWidget {
  const _RequestList({required this.status});

  final String status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final controller = ref.read(requestsProvider(status).notifier);
    return PagedListView<HrRequestSummary>(
      value: ref.watch(requestsProvider(status)),
      onRefresh: controller.refresh,
      onLoadMore: controller.loadMore,
      empty: EmptyState(
        icon: Icons.support_agent_rounded,
        title: status == 'open' ? l.noOpenRequests : l.noClosedRequests,
      ),
      itemBuilder: (context, request) => AppListTile(
        leadingIcon: Icons.description_outlined,
        title: request.subject.isEmpty ? request.categoryName : request.subject,
        subtitle:
            '${request.reference} · ${request.categoryName} · ${f.date(request.submittedAt?.toLocal())}',
        trailing: StatusBadge(
          label: request.statusLabel,
          status: request.status,
        ),
        onTap: () async {
          await context.push(Routes.request(request.id));
          ref.invalidate(requestsProvider(status));
        },
      ),
    );
  }
}
