import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/paged.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/attendance_repository.dart';
import '../domain/models.dart';
import 'widgets/attendance_widgets.dart';

class AttemptsController extends PagedNotifier<AttendanceAttempt> {
  @override
  Future<Paged<AttendanceAttempt>> fetch(int page) =>
      ref.read(attendanceRepositoryProvider).attempts(page: page);
}

final attemptsProvider =
    AsyncNotifierProvider.autoDispose<
      AttemptsController,
      PagedState<AttendanceAttempt>
    >(AttemptsController.new);

/// Attendance history: processed days per month, mobile attempts (with
/// review status) and correction requests.
class AttendanceHistoryScreen extends StatelessWidget {
  const AttendanceHistoryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.attendanceHistoryTitle),
          bottom: TabBar(
            tabs: [
              Tab(text: l.tabDays),
              Tab(text: l.tabAttempts),
              Tab(text: l.tabCorrections),
            ],
          ),
        ),
        body: const TabBarView(
          children: [_MonthTab(), _AttemptsTab(), _CorrectionsTab()],
        ),
      ),
    );
  }
}

class _MonthTab extends ConsumerStatefulWidget {
  const _MonthTab();

  @override
  ConsumerState<_MonthTab> createState() => _MonthTabState();
}

class _MonthTabState extends ConsumerState<_MonthTab> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final key = Formatter.apiMonth(_month);
    final value = ref.watch(attendanceMonthProvider(key));
    final isCurrent =
        _month.year == DateTime.now().year &&
        _month.month == DateTime.now().month;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          child: Row(
            children: [
              IconButton(
                tooltip: l.previous,
                onPressed: () => setState(
                  () => _month = DateTime(_month.year, _month.month - 1),
                ),
                icon: const Icon(Icons.chevron_left_rounded),
              ),
              Expanded(
                child: Text(
                  f.monthYear(_month),
                  textAlign: TextAlign.center,
                  style: AppType.headingH5,
                ),
              ),
              IconButton(
                tooltip: l.next,
                onPressed: isCurrent
                    ? null
                    : () => setState(
                        () => _month = DateTime(_month.year, _month.month + 1),
                      ),
                icon: const Icon(Icons.chevron_right_rounded),
              ),
            ],
          ),
        ),
        Expanded(
          child: AsyncView<AttendanceMonth>(
            value: value,
            onRefresh: () =>
                ref.refresh(attendanceMonthProvider(key).future).then((_) {}),
            isEmpty: (m) => m.days.isEmpty,
            empty: EmptyState(
              icon: Icons.event_note_outlined,
              title: l.noAttendanceDays,
            ),
            builder: (month) => ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(AppSizes.pagePadding),
              children: [
                _MonthSummary(summary: month.summary),
                const SizedBox(height: AppSizes.gap),
                for (final day in month.days.reversed) ...[
                  _DayTile(day: day),
                  const SizedBox(height: 8),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _MonthSummary extends ConsumerWidget {
  const _MonthSummary({required this.summary});

  final Json summary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final t = context.tokens;
    final items = [
      (l.presentDays, f.number(summary.integer('present_days')), 'present'),
      (l.lateDays, f.number(summary.integer('late_days')), 'late'),
      (l.absentDays, f.number(summary.integer('absent_days')), 'absent'),
      (l.leaveDays, f.number(summary.integer('leave_days')), 'on_leave'),
      (l.workedHours, f.duration(summary.integer('worked_minutes')), 'info'),
      (l.overtime, f.duration(summary.integer('overtime_minutes')), 'approved'),
    ];
    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: 1.4,
      children: [
        for (final (label, value, status) in items)
          AppCard(
            padding: const EdgeInsets.all(8),
            color: t.tone(toneForStatus(status)).bg,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  value,
                  style: AppType.numericLg.copyWith(
                    color: t.tone(toneForStatus(status)).fg,
                  ),
                ),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppType.captionMd.copyWith(color: t.textSecondary),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _DayTile extends ConsumerWidget {
  const _DayTile({required this.day});

  final AttendanceDay day;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final date = Formatter.parseDate(day.date);
    final details = <String>[
      if (day.shiftName != null) day.shiftName!,
      '${f.clock(day.checkInLocal)} – ${f.clock(day.checkOutLocal)}',
      if (day.lateMinutes > 0) l.lateBy(f.number(day.lateMinutes)),
      ...day.flags,
    ];
    return AppListTile(
      leadingIcon: Icons.today_outlined,
      leadingColor: context.tokens.tone(toneForStatus(day.status)).solid,
      title: date == null ? day.date : f.dateLong(date),
      subtitle: details.join(' · '),
      trailing: StatusBadge(label: day.statusLabel, status: day.status),
    );
  }
}

class _AttemptsTab extends ConsumerWidget {
  const _AttemptsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final controller = ref.read(attemptsProvider.notifier);
    return PagedListView<AttendanceAttempt>(
      value: ref.watch(attemptsProvider),
      onRefresh: controller.refresh,
      onLoadMore: controller.loadMore,
      empty: EmptyState(
        icon: Icons.phone_android_outlined,
        title: l.noAttempts,
      ),
      itemBuilder: (context, attempt) {
        final outcome = switch (attempt.outcome) {
          'accepted' => l.outcomeAccepted,
          'flagged' =>
            attempt.reviewStatus == 'approved'
                ? l.outcomeReviewApproved
                : attempt.reviewStatus == 'rejected'
                ? l.outcomeReviewRejected
                : l.outcomeFlagged,
          _ => l.outcomeRejected,
        };
        final status = attempt.outcome == 'flagged'
            ? (attempt.reviewStatus ?? 'pending')
            : attempt.outcome;
        return AppListTile(
          leadingIcon: attempt.direction == 'in'
              ? Icons.login_rounded
              : Icons.logout_rounded,
          title:
              '${attempt.direction == 'in' ? l.checkIn : l.checkOut} · ${f.dateTime(attempt.serverTime)}',
          subtitle: attempt.reasons.isEmpty
              ? attempt.locationName
              : attempt.reasons
                    .map((r) => reasonText(l, r.code, r.message))
                    .join(' · '),
          trailing: StatusBadge(label: outcome, status: status),
        );
      },
    );
  }
}

class _CorrectionsTab extends ConsumerWidget {
  const _CorrectionsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    return AsyncView<List<AttendanceCorrection>>(
      value: ref.watch(correctionsProvider),
      onRefresh: () => ref.refresh(correctionsProvider.future).then((_) {}),
      isEmpty: (list) => list.isEmpty,
      empty: EmptyState(
        icon: Icons.edit_calendar_outlined,
        title: l.noCorrections,
      ),
      builder: (list) => ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSizes.pagePadding),
        itemCount: list.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, i) => AppListTile(
          leadingIcon: Icons.edit_calendar_outlined,
          title: f.date(Formatter.parseDate(list[i].date)),
          subtitle: list[i].reason,
          trailing: StatusBadge(
            label: list[i].statusLabel,
            status: list[i].status,
          ),
        ),
      ),
    );
  }
}
