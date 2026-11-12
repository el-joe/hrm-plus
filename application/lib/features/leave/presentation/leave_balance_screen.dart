import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/money.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/leave_repository.dart';

/// Leave balances per type for a leave year.
class LeaveBalanceScreen extends ConsumerStatefulWidget {
  const LeaveBalanceScreen({super.key});

  @override
  ConsumerState<LeaveBalanceScreen> createState() => _LeaveBalanceScreenState();
}

class _LeaveBalanceScreenState extends ConsumerState<LeaveBalanceScreen> {
  int _year = DateTime.now().year;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final value = ref.watch(leaveBalancesProvider(_year));

    return Scaffold(
      appBar: AppBar(
        title: Text(l.leaveBalance),
        actions: [
          IconButton(
            tooltip: l.leaveHistoryTitle,
            onPressed: () => context.push(Routes.leaveHistory),
            icon: const Icon(Icons.history_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('new-leave'),
        onPressed: () async {
          await context.push(Routes.newLeave);
          ref.invalidate(leaveBalancesProvider);
        },
        icon: const Icon(Icons.add_rounded),
        label: Text(l.newLeaveRequest),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            child: Row(
              children: [
                IconButton(
                  tooltip: l.previous,
                  onPressed: () => setState(() => _year--),
                  icon: const Icon(Icons.chevron_left_rounded),
                ),
                Expanded(
                  child: Text(
                    value.value?.$2.strOrNull('year_label') ?? f.d('$_year'),
                    textAlign: TextAlign.center,
                    style: AppType.headingH5,
                  ),
                ),
                IconButton(
                  tooltip: l.next,
                  onPressed: _year >= DateTime.now().year + 1
                      ? null
                      : () => setState(() => _year++),
                  icon: const Icon(Icons.chevron_right_rounded),
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncView<(List<LeaveBalance>, Json)>(
              value: value,
              onRefresh: () =>
                  ref.refresh(leaveBalancesProvider(_year).future).then((_) {}),
              isEmpty: (data) => data.$1.isEmpty,
              empty: EmptyState(
                icon: Icons.event_busy_outlined,
                title: l.noLeaveTypes,
              ),
              builder: (data) => ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                itemCount: data.$1.length,
                separatorBuilder: (_, _) => const SizedBox(height: 12),
                itemBuilder: (context, i) =>
                    _BalanceCard(balance: data.$1[i], index: i),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BalanceCard extends ConsumerWidget {
  const _BalanceCard({required this.balance, required this.index});

  final LeaveBalance balance;
  final int index;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final color = t.chartColor(balance.color, index);
    final entitled =
        Money.parse(balance.entitlement) + Money.parse(balance.carriedIn);
    final used = Money.parse(balance.used) + Money.parse(balance.pending);
    final progress = balance.tracksBalance
        ? used.ratioOf(entitled).clamp(0.0, 1.0)
        : 0.0;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              ),
              const SizedBox(width: 8),
              Expanded(child: Text(balance.typeName, style: AppType.headingH5)),
              if (balance.tracksBalance)
                Text(
                  l.daysValue(f.decimal(balance.available)),
                  style: AppType.numericLg.copyWith(color: color),
                )
              else
                StatusBadge(label: l.notTracked, tone: Tone.neutral),
            ],
          ),
          if (balance.tracksBalance) ...[
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 6,
                color: color,
                backgroundColor: t.surfaceMuted,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                _Stat(
                  label: l.entitlement,
                  value: f.decimal(balance.entitlement),
                ),
                _Stat(label: l.carriedIn, value: f.decimal(balance.carriedIn)),
                _Stat(label: l.used, value: f.decimal(balance.used)),
                _Stat(label: l.pending, value: f.decimal(balance.pending)),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Expanded(
      child: Column(
        children: [
          Text(value, style: AppType.numericMd.copyWith(color: t.textPrimary)),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppType.captionSm.copyWith(color: t.textSecondary),
          ),
        ],
      ),
    );
  }
}
