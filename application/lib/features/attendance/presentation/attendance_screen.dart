import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../application/location_preview.dart';
import '../data/attendance_repository.dart';
import '../domain/models.dart';
import 'widgets/attendance_widgets.dart';

/// Attendance tab: today's state, shift, allowed locations, punches, and
/// entry points to history, schedule, GPS and face screens.
class AttendanceScreen extends ConsumerWidget {
  const AttendanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final today = ref.watch(todayProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.attendanceTitle)),
      body: AsyncView<TodayStatus>(
        value: today,
        onRefresh: () async {
          ref.invalidate(locationPreviewProvider);
          return ref.refresh(todayProvider.future).then((_) {});
        },
        builder: (status) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            _StateHeader(status: status),
            const SizedBox(height: 8),
            Center(child: AttendanceActionButton(today: status)),
            AttendanceStatusChips(today: status),
            const SizedBox(height: AppSizes.gap),
            _ShiftCard(status: status),
            const SizedBox(height: AppSizes.gap),
            _PunchesCard(status: status),
            const SizedBox(height: AppSizes.gap),
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.history_rounded),
                    title: Text(l.attendanceHistoryTitle),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () => context.push(Routes.attendanceHistory),
                  ),
                  ListTile(
                    leading: const Icon(Icons.calendar_month_outlined),
                    title: Text(l.scheduleTitle),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () => context.push(Routes.schedule),
                  ),
                  ListTile(
                    leading: const Icon(Icons.gps_fixed_rounded),
                    title: Text(l.gpsTitle),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () => context.push(Routes.gps),
                  ),
                  ListTile(
                    leading: const Icon(Icons.face_retouching_natural_outlined),
                    title: Text(l.faceTitle),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () => context.push(Routes.face),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StateHeader extends ConsumerWidget {
  const _StateHeader({required this.status});

  final TodayStatus status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final (label, state) = switch (status.state) {
      'checked_in' => (l.stateCheckedIn, 'present'),
      'checked_out' => (l.stateCheckedOut, 'finalized'),
      _ => (l.stateNotCheckedIn, 'draft'),
    };
    return Row(
      children: [
        Expanded(
          child: Text(
            f.dateLong(DateTime.tryParse(status.date)),
            style: AppType.labelMd.copyWith(color: t.textSecondary),
          ),
        ),
        StatusBadge(label: label, status: state),
      ],
    );
  }
}

class _ShiftCard extends ConsumerWidget {
  const _ShiftCard({required this.status});

  final TodayStatus status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    return SectionCard(
      title: l.assignedShift,
      children: [
        if (status.holiday != null)
          InfoRow(label: l.holiday, value: status.holiday)
        else if (status.shifts.isEmpty)
          InfoRow(
            label: l.shift,
            value: status.isRestDay ? l.restDay : l.noShiftToday,
          ),
        for (final shift in status.shifts)
          InfoRow(
            label: shift.name,
            value: '${f.clock(shift.startLocal)} – ${f.clock(shift.endLocal)}',
          ),
        InfoRow(
          label: l.allowedLocations,
          value: status.allowAnywhere
              ? l.locationAnywhere
              : status.locations
                    .map(
                      (e) =>
                          '${e.name} (${l.metersShort(f.number(e.radiusMeters))})',
                    )
                    .join('\n'),
        ),
        if (!status.withinShiftWindow && status.shifts.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: InlineBanner(
              message: l.outsideShiftWindowHint,
              tone: Tone.info,
            ),
          ),
      ],
    );
  }
}

class _PunchesCard extends ConsumerWidget {
  const _PunchesCard({required this.status});

  final TodayStatus status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    return SectionCard(
      title: l.todayPunches,
      children: [
        if (status.punches.isEmpty)
          Text(l.noPunchesYet)
        else
          for (final punch in status.punches)
            InfoRow(
              label: punch.direction == 'in' ? l.checkIn : l.checkOut,
              value: '${f.clock(punch.local)} · ${punch.source}',
            ),
      ],
    );
  }
}
