import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../errors/failure_message.dart';
import '../l10n/l10n.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';

/// Pulsing placeholder block (skeleton loading).
class SkeletonBox extends StatefulWidget {
  const SkeletonBox({super.key, this.height = 16, this.width, this.radius = 8});

  final double height;
  final double? width;
  final double radius;

  @override
  State<SkeletonBox> createState() => _SkeletonBoxState();
}

class _SkeletonBoxState extends State<SkeletonBox>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = context.tokens.surfaceMuted;
    return FadeTransition(
      opacity: Tween<double>(begin: 0.45, end: 1).animate(_controller),
      child: Container(
        height: widget.height,
        width: widget.width,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

/// A list of skeleton tiles, used while a list screen loads.
class SkeletonList extends StatelessWidget {
  const SkeletonList({super.key, this.count = 6, this.tileHeight = 72});

  final int count;
  final double tileHeight;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: context.l10n.loading,
      child: ListView.separated(
        key: const Key('skeleton-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSizes.pagePadding),
        itemCount: count,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (_, _) =>
            SkeletonBox(height: tileHeight, radius: AppSizes.radiusLg),
      ),
    );
  }
}

/// Empty state: icon, title, optional message and action.
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.title,
    this.message,
    this.icon = Icons.inbox_outlined,
    this.action,
  });

  final String title;
  final String? message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Center(
      key: const Key('empty-state'),
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: t.actionPrimarySubtle,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 30, color: t.actionPrimary),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              style: AppType.headingH5.copyWith(color: t.textPrimary),
              textAlign: TextAlign.center,
            ),
            if (message != null) ...[
              const SizedBox(height: 6),
              Text(
                message!,
                style: AppType.bodyMd.copyWith(color: t.textSecondary),
                textAlign: TextAlign.center,
              ),
            ],
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      ),
    );
  }
}

/// Error state with a retry button.
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.error, this.onRetry});

  final Object? error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final offline = isConnectivityFailure(error);
    return EmptyState(
      key: const Key('error-state'),
      icon: offline ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
      title: offline ? l.errorOfflineTitle : l.errorTitle,
      message: failureMessage(l, error),
      action: onRetry == null
          ? null
          : OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: Text(l.retry),
            ),
    );
  }
}

/// Renders an [AsyncValue] with skeleton / error / empty / data states, all
/// inside a pull-to-refresh scrollable so refresh works in every state.
class AsyncView<T> extends StatelessWidget {
  const AsyncView({
    super.key,
    required this.value,
    required this.onRefresh,
    required this.builder,
    this.isEmpty,
    this.empty,
    this.loading,
  });

  final AsyncValue<T> value;
  final Future<void> Function() onRefresh;

  /// Must return a scrollable (ListView / CustomScrollView).
  final Widget Function(T data) builder;
  final bool Function(T data)? isEmpty;
  final Widget? empty;
  final Widget? loading;

  Widget _scrollableFill(Widget child) => LayoutBuilder(
    builder: (context, constraints) => SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: ConstrainedBox(
        constraints: BoxConstraints(minHeight: constraints.maxHeight),
        child: child,
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final Widget body;
    if (value.hasValue && !(value.hasError && !value.isLoading)) {
      final data = value.requireValue;
      if (isEmpty?.call(data) ?? false) {
        body = _scrollableFill(
          empty ?? EmptyState(title: context.l10n.emptyGeneric),
        );
      } else {
        body = builder(data);
      }
    } else if (value.hasError) {
      body = _scrollableFill(
        ErrorState(error: value.error, onRetry: onRefresh),
      );
    } else {
      body = loading ?? const SkeletonList();
    }
    return RefreshIndicator(onRefresh: onRefresh, child: body);
  }
}

enum SnackTone { info, success, error }

void showSnack(
  BuildContext context,
  String message, {
  SnackTone tone = SnackTone.info,
}) {
  final t = context.tokens;
  final color = switch (tone) {
    SnackTone.success => t.tone(Tone.success).solid,
    SnackTone.error => t.tone(Tone.danger).solid,
    SnackTone.info => null,
  };
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), backgroundColor: color));
}

Future<bool> confirmDialog(
  BuildContext context, {
  required String title,
  required String message,
  required String confirmLabel,
  bool danger = false,
}) async {
  final l = context.l10n;
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: Text(l.cancel),
        ),
        FilledButton(
          style: danger
              ? FilledButton.styleFrom(
                  backgroundColor: context.tokens.actionDanger,
                )
              : null,
          onPressed: () => Navigator.of(context).pop(true),
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return result ?? false;
}
