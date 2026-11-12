import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/attendance_repository.dart';
import '../domain/models.dart';

/// Work schedule, two weeks at a time (`GET me/schedule?from&to`).
class ScheduleScreen extends ConsumerStatefulWidget {
  const ScheduleScreen({super.key});

  @override
  ConsumerState<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends ConsumerState<ScheduleScreen> {
  static const _span = 14;
  DateTime _from = DateUtils.dateOnly(DateTime.now());

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final to = _from.add(const Duration(days: _span - 1));
    final range = (Formatter.apiDate(_from), Formatter.apiDate(to));
    final value = ref.watch(scheduleProvider(range));

    return Scaffold(
      appBar: AppBar(title: Text(l.scheduleTitle)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            child: Row(
              children: [
                IconButton(
                  tooltip: l.previous,
                  onPressed: () => setState(
                    () => _from = _from.subtract(const Duration(days: _span)),
                  ),
                  icon: const Icon(Icons.chevron_left_rounded),
                ),
                Expanded(
                  child: Text(
                    '${f.dayMonth(_from)} – ${f.dayMonth(to)}',
                    textAlign: TextAlign.center,
                    style: AppType.headingH5,
                  ),
                ),
                IconButton(
                  tooltip: l.next,
                  onPressed: () => setState(
                    () => _from = _from.add(const Duration(days: _span)),
                  ),
                  icon: const Icon(Icons.chevron_right_rounded),
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncView<List<ScheduleDay>>(
              value: value,
              onRefresh: () =>
                  ref.refresh(scheduleProvider(range).future).then((_) {}),
              isEmpty: (days) => days.isEmpty,
              empty: EmptyState(
                icon: Icons.calendar_month_outlined,
                title: l.noSchedule,
              ),
              builder: (days) => ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(AppSizes.pagePadding),
                itemCount: days.length,
                separatorBuilder: (_, _) => const SizedBox(height: 8),
                itemBuilder: (context, i) => _ScheduleTile(day: days[i]),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ScheduleTile extends ConsumerWidget {
  const _ScheduleTile({required this.day});

  final ScheduleDay day;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final date = Formatter.parseDate(day.date) ?? DateTime.now();
    final isToday = DateUtils.isSameDay(date, DateTime.now());

    final String subtitle;
    final String status;
    final String badge;
    if (day.holiday != null) {
      subtitle = day.holiday!;
      status = 'holiday';
      badge = l.holiday;
    } else if (day.shifts.isEmpty) {
      subtitle = day.isRestDay ? l.restDay : l.noShift;
      status = 'draft';
      badge = day.isRestDay ? l.restDay : l.noShift;
    } else {
      subtitle = day.shifts
          .map(
            (s) =>
                '${s.name} ${f.clock(s.startLocal)} – ${f.clock(s.endLocal)}',
          )
          .join('\n');
      status = 'approved';
      badge = l.workingDay;
    }

    return AppCard(
      color: isToday ? t.actionPrimarySubtle : null,
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          SizedBox(
            width: 48,
            child: Column(
              children: [
                Text(
                  f.weekdayShort(date),
                  style: AppType.captionMd.copyWith(color: t.textSecondary),
                ),
                Text(
                  f.d('${date.day}'),
                  style: AppType.numericLg.copyWith(color: t.textPrimary),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(subtitle, style: AppType.bodyMd)),
          StatusBadge(label: badge, status: status),
        ],
      ),
    );
  }
}
