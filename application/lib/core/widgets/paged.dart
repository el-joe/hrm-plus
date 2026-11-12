import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../errors/failure_message.dart';
import '../l10n/l10n.dart';
import '../network/api_response.dart';
import '../network/json.dart';
import '../theme/app_theme.dart';
import 'states.dart';

/// Accumulated pages of a paginated `/api/v1` list.
class PagedState<T> {
  const PagedState({
    required this.items,
    required this.page,
    required this.hasMore,
    this.meta = const {},
    this.loadingMore = false,
    this.loadMoreError,
  });

  final List<T> items;
  final int page;
  final bool hasMore;
  final Json meta;
  final bool loadingMore;
  final Object? loadMoreError;

  PagedState<T> copyWith({
    List<T>? items,
    int? page,
    bool? hasMore,
    Json? meta,
    bool? loadingMore,
    Object? loadMoreError,
    bool clearError = false,
  }) => PagedState(
    items: items ?? this.items,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    meta: meta ?? this.meta,
    loadingMore: loadingMore ?? this.loadingMore,
    loadMoreError: clearError ? null : (loadMoreError ?? this.loadMoreError),
  );
}

/// Base for list controllers: page 1 on build, [loadMore] appends.
abstract class PagedNotifier<T> extends AsyncNotifier<PagedState<T>> {
  Future<Paged<T>> fetch(int page);

  @override
  Future<PagedState<T>> build() async {
    final first = await fetch(1);
    return PagedState(
      items: first.items,
      page: first.page,
      hasMore: first.hasMore,
      meta: first.meta,
    );
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.loadingMore) return;
    state = AsyncData(current.copyWith(loadingMore: true, clearError: true));
    try {
      final next = await fetch(current.page + 1);
      if (!ref.mounted) return;
      state = AsyncData(
        current.copyWith(
          items: [...current.items, ...next.items],
          page: next.page,
          hasMore: next.hasMore,
          meta: next.meta,
          loadingMore: false,
        ),
      );
    } catch (error) {
      if (!ref.mounted) return;
      state = AsyncData(
        current.copyWith(loadingMore: false, loadMoreError: error),
      );
    }
  }

  /// Replace one item in place (e.g. after marking it read).
  void replaceWhere(bool Function(T item) test, T Function(T item) update) {
    final current = state.value;
    if (current == null) return;
    state = AsyncData(
      current.copyWith(
        items: [
          for (final item in current.items) test(item) ? update(item) : item,
        ],
      ),
    );
  }
}

/// List with pull-to-refresh, skeleton / empty / error states and infinite
/// scrolling.
class PagedListView<T> extends StatelessWidget {
  const PagedListView({
    super.key,
    required this.value,
    required this.onRefresh,
    required this.onLoadMore,
    required this.itemBuilder,
    this.empty,
    this.header,
  });

  final AsyncValue<PagedState<T>> value;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;
  final Widget Function(BuildContext context, T item) itemBuilder;
  final Widget? empty;
  final Widget? header;

  @override
  Widget build(BuildContext context) {
    return AsyncView<PagedState<T>>(
      value: value,
      onRefresh: onRefresh,
      isEmpty: (state) => state.items.isEmpty && header == null,
      empty: empty,
      builder: (state) {
        final headerCount = header == null ? 0 : 1;
        return ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          itemCount: headerCount + state.items.length + 1,
          separatorBuilder: (_, _) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            if (header != null && index == 0) {
              if (state.items.isEmpty) {
                return Column(
                  children: [header!, empty ?? const SizedBox.shrink()],
                );
              }
              return header!;
            }
            final i = index - headerCount;
            if (i < state.items.length) {
              return itemBuilder(context, state.items[i]);
            }
            return _Footer(state: state, onLoadMore: onLoadMore);
          },
        );
      },
    );
  }
}

class _Footer<T> extends StatelessWidget {
  const _Footer({required this.state, required this.onLoadMore});

  final PagedState<T> state;
  final Future<void> Function() onLoadMore;

  @override
  Widget build(BuildContext context) {
    if (state.loadMoreError != null) {
      return Center(
        child: TextButton.icon(
          onPressed: onLoadMore,
          icon: const Icon(Icons.refresh_rounded),
          label: Text(
            '${failureMessage(context.l10n, state.loadMoreError)} · ${context.l10n.retry}',
          ),
        ),
      );
    }
    if (state.hasMore) {
      if (!state.loadingMore) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          unawaited(onLoadMore());
        });
      }
      return const Padding(
        padding: EdgeInsets.all(12),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    return const SizedBox(height: 24);
  }
}
