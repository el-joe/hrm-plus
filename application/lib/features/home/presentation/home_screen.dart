import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../../attendance/application/location_preview.dart';
import '../../attendance/data/attendance_repository.dart';
import '../../attendance/domain/models.dart';
import '../../attendance/presentation/widgets/attendance_widgets.dart';
import '../../notifications/data/notifications_repository.dart';
import '../data/home_repository.dart';
import 'notification_bell.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(homeProvider);
    ref.invalidate(todayProvider);
    ref.invalidate(unreadCountProvider);
    ref.invalidate(locationPreviewProvider);
    await Future.wait([
      ref.read(homeProvider.future).then((_) {}, onError: (_) {}),
      ref.read(todayProvider.future).then((_) {}, onError: (_) {}),
    ]);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final home = ref.watch(homeProvider);
    final today = ref.watch(todayProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l.navHome),
        actions: const [NotificationBell()],
      ),
      body: AsyncView<HomeSummary>(
        value: home,
        onRefresh: () => _refresh(ref),
        loading: const _HomeSkeleton(),
        builder: (summary) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            _Greeting(summary: summary, today: today.value),
            const SizedBox(height: 8),
            Center(
              child: AttendanceActionButton(
                today: today.value,
                processing: today.isLoading && !today.hasValue,
              ),
            ),
            const SizedBox(height: 8),
            if (today.hasError && !today.hasValue)
              InlineBanner(
                message: l.attendanceStatusUnavailable,
                tone: Tone.warning,
                action: TextButton(
                  onPressed: () => ref.invalidate(todayProvider),
                  child: Text(l.retry),
                ),
              )
            else if (today.value != null) ...[
              AttendanceStatusChips(today: today.value!),
              if (today.value!.pendingReviews > 0) ...[
                const SizedBox(height: 12),
                InlineBanner(
                  message: l.pendingReviews(today.value!.pendingReviews),
                  tone: Tone.warning,
                  icon: Icons.hourglass_top_rounded,
                ),
              ],
            ],
            const SizedBox(height: AppSizes.gap),
            _TodayTimes(summary: summary),
            const SizedBox(height: AppSizes.gap),
            _SummaryCards(summary: summary),
            if (summary.tasks.isNotEmpty || summary.pendingTotal > 0) ...[
              const SizedBox(height: AppSizes.gap),
              _Pending(summary: summary),
            ],
            const SizedBox(height: AppSizes.gap),
            const _QuickLinks(),
          ],
        ),
      ),
    );
  }
}

class _Greeting extends ConsumerWidget {
  const _Greeting({required this.summary, required this.today});

  final HomeSummary summary;
  final TodayStatus? today;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = context.tokens;
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final hour = DateTime.now().hour;
    final greeting = hour < 12 ? l.goodMorning : l.goodEvening;
    final name = summary.employeeName.split(' ').first;
    final initials = summary.employeeName
        .split(' ')
        .where((p) => p.isNotEmpty)
        .take(2)
        .map((p) => p.characters.first)
        .join();
    final shift = summary.shiftName == null
        ? (today?.isRestDay ?? false ? l.restDay : l.noShiftToday)
        : l.shiftRange(
            summary.shiftName!,
            f.clock(summary.shiftStart),
            f.clock(summary.shiftEnd),
          );

    return Row(
      children: [
        CircleAvatar(
          radius: 20,
          backgroundColor: t.actionPrimarySubtle,
          child: Text(
            initials,
            style: AppType.labelMd.copyWith(color: t.actionPrimary),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                l.greetingName(greeting, name),
                style: AppType.headingH5.copyWith(color: t.textPrimary),
              ),
              Text(
                '${f.dateLong(DateTime.now())} · $shift',
                style: AppType.captionMd.copyWith(color: t.textSecondary),
              ),
              if (today?.holiday != null)
                Text(
                  l.holidayToday(today!.holiday!),
                  style: AppType.captionMd.copyWith(color: t.accent),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TodayTimes extends ConsumerWidget {
  const _TodayTimes({required this.summary});

  final HomeSummary summary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = context.tokens;
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final today = ref.watch(todayProvider).value;
    final worked = today?.day?.workedMinutes ?? 0;

    Widget cell(String label, String value) => Expanded(
      child: Column(
        children: [
          Text(
            label,
            style: AppType.captionMd.copyWith(color: t.textSecondary),
          ),
          const SizedBox(height: 4),
          Text(value, style: AppType.numericLg.copyWith(color: t.textPrimary)),
        ],
      ),
    );

    return AppCard(
      child: IntrinsicHeight(
        child: Row(
          children: [
            cell(
              l.checkInTime,
              f.clock(today?.firstIn ?? summary.checkInLocal),
            ),
            VerticalDivider(color: t.borderSubtle),
            cell(
              l.checkOutTime,
              f.clock(today?.lastOut ?? summary.checkOutLocal),
            ),
            VerticalDivider(color: t.borderSubtle),
            cell(l.workedHours, f.duration(worked)),
          ],
        ),
      ),
    );
  }
}

class _SummaryCards extends ConsumerWidget {
  const _SummaryCards({required this.summary});

  final HomeSummary summary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final balance = summary.primaryBalance;
    final payslip = summary.latestPayslip;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: _SummaryCard(
            icon: Icons.event_available_outlined,
            title: balance == null
                ? l.leaveBalance
                : (balance.obj('leave_type')?.str('name') ?? l.leaveBalance),
            value: balance == null
                ? '—'
                : l.daysValue(f.decimal(balance.strOrNull('available'))),
            onTap: () => context.go(Routes.leave),
          ),
        ),
        const SizedBox(width: AppSizes.gap),
        Expanded(
          child: _SummaryCard(
            icon: Icons.account_balance_wallet_outlined,
            title: l.lastNetSalary,
            value: payslip == null
                ? '—'
                : f.money(payslip.strOrNull('net_pay')),
            caption: payslip?.strOrNull('currency'),
            onTap: payslip == null
                ? () => context.go(Routes.payslips)
                : () => context.push(Routes.payslip(payslip.integer('id'))),
          ),
        ),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.icon,
    required this.title,
    required this.value,
    this.caption,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String value;
  final String? caption;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return AppCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: t.actionPrimarySubtle,
              borderRadius: BorderRadius.circular(AppSizes.radiusMd),
            ),
            child: Icon(icon, size: 20, color: t.actionPrimary),
          ),
          const SizedBox(height: 12),
          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppType.labelSm.copyWith(color: t.textSecondary),
          ),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: AlignmentDirectional.centerStart,
            child: Text(
              value,
              style: AppType.numericLg.copyWith(color: t.textPrimary),
            ),
          ),
          if (caption != null)
            Text(
              caption!,
              style: AppType.captionSm.copyWith(color: t.textTertiary),
            ),
        ],
      ),
    );
  }
}

class _Pending extends ConsumerWidget {
  const _Pending({required this.summary});

  final HomeSummary summary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final p = summary.pending;
    final rows = <Widget>[];

    void add(String key, String label, String route) {
      final count = p.integer(key);
      if (count <= 0) return;
      rows.add(
        ListTile(
          dense: true,
          title: Text(label),
          trailing: StatusBadge(label: f.number(count), status: 'pending'),
          onTap: () => context.push(route),
        ),
      );
    }

    add('leave_requests', l.pendingLeaveRequests, Routes.leaveHistory);
    add('hr_requests', l.pendingHrRequests, Routes.requests);
    add('loan_requests', l.pendingLoanRequests, Routes.loans);
    add(
      'attendance_corrections',
      l.pendingCorrections,
      Routes.attendanceHistory,
    );
    add(
      'announcements_to_acknowledge',
      l.pendingAcknowledgements,
      Routes.announcements,
    );
    for (final task in summary.tasks) {
      rows.add(
        ListTile(
          dense: true,
          leading: const Icon(Icons.task_alt_rounded),
          title: Text(task.str('title')),
          subtitle: task.strOrNull('due_date') == null
              ? null
              : Text(f.date(Formatter.parseDate(task.strOrNull('due_date')))),
        ),
      );
    }

    return SectionCard(title: l.pendingItems, children: rows);
  }
}

class _QuickLinks extends StatelessWidget {
  const _QuickLinks();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final items = [
      (Icons.add_circle_outline_rounded, l.newLeaveRequest, Routes.newLeave),
      (Icons.support_agent_rounded, l.hrRequestsTitle, Routes.requests),
      (Icons.campaign_outlined, l.announcementsTitle, Routes.announcements),
      (Icons.folder_open_rounded, l.documentsTitle, Routes.documents),
      (Icons.payments_outlined, l.loansTitle, Routes.loans),
      (Icons.calendar_month_outlined, l.scheduleTitle, Routes.schedule),
    ];
    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.05,
      children: [
        for (final (icon, label, route) in items)
          AppCard(
            padding: const EdgeInsets.all(8),
            onTap: () => context.push(route),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: context.tokens.actionPrimary),
                const SizedBox(height: 8),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: AppType.labelSm.copyWith(
                    color: context.tokens.textPrimary,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    key: const Key('home-skeleton'),
    padding: const EdgeInsets.all(AppSizes.pagePadding),
    children: const [
      SkeletonBox(height: 44),
      SizedBox(height: 16),
      Center(
        child: SkeletonBox(
          height: AppSizes.attendanceButton,
          width: AppSizes.attendanceButton,
          radius: 100,
        ),
      ),
      SizedBox(height: 16),
      SkeletonBox(height: 84, radius: 16),
      SizedBox(height: 16),
      Row(
        children: [
          Expanded(child: SkeletonBox(height: 120, radius: 16)),
          SizedBox(width: 16),
          Expanded(child: SkeletonBox(height: 120, radius: 16)),
        ],
      ),
    ],
  );
}
