import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/providers.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_tokens.dart';
import '../../application/check_flow_controller.dart';
import '../../application/location_preview.dart';
import '../../domain/models.dart';

/// Device clock, updated every second.
class LiveClock extends ConsumerStatefulWidget {
  const LiveClock({super.key, this.style});

  final TextStyle? style;

  @override
  ConsumerState<LiveClock> createState() => _LiveClockState();
}

class _LiveClockState extends ConsumerState<LiveClock> {
  late Timer _timer;
  DateTime _now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(
      const Duration(seconds: 1),
      (_) => setState(() => _now = DateTime.now()),
    );
  }

  @override
  void dispose() {
    _timer.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) =>
      Text(ref.watch(formatterProvider).time(_now), style: widget.style);
}

/// The 200 px attendance button inside a 248 px halo (Figma "Mobile
/// attendance button": check in, check out, processing, disabled).
class AttendanceActionButton extends StatelessWidget {
  const AttendanceActionButton({
    super.key,
    required this.today,
    this.processing = false,
  });

  final TodayStatus? today;
  final bool processing;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final l = context.l10n;
    final status = today;
    final enabled = status != null && status.canUseMobile && !processing;
    final checkOut = status?.nextDirection == AttendanceDirection.checkOut;
    final color = enabled
        ? (checkOut ? t.accent : t.actionPrimary)
        : t.textDisabled;
    final label = status == null
        ? l.loading
        : !status.canUseMobile
        ? l.mobileAttendanceDisabled
        : checkOut
        ? l.checkOut
        : l.checkIn;

    return Semantics(
      button: true,
      enabled: enabled,
      label: label,
      child: Container(
        width: AppSizes.attendanceHalo,
        height: AppSizes.attendanceHalo,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: color.withValues(alpha: 0.08),
        ),
        child: Material(
          key: const Key('attendance-button'),
          color: color,
          shape: const CircleBorder(),
          elevation: enabled ? 4 : 0,
          shadowColor: color.withValues(alpha: 0.4),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: enabled
                ? () =>
                      context.push(checkOut ? Routes.checkOut : Routes.checkIn)
                : null,
            child: SizedBox(
              width: AppSizes.attendanceButton,
              height: AppSizes.attendanceButton,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (processing)
                    const SizedBox(
                      width: 36,
                      height: 36,
                      child: CircularProgressIndicator(
                        strokeWidth: 3,
                        color: Colors.white,
                      ),
                    )
                  else
                    Icon(
                      checkOut ? Icons.logout_rounded : Icons.login_rounded,
                      size: 40,
                      color: t.textOnPrimary,
                    ),
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Text(
                      label,
                      textAlign: TextAlign.center,
                      style: AppType.headingH4.copyWith(color: t.textOnPrimary),
                    ),
                  ),
                  const SizedBox(height: 4),
                  LiveClock(
                    style: AppType.labelLg.copyWith(color: t.textOnPrimary),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// A compact status chip (location / GPS / face).
class StatusChip extends StatelessWidget {
  const StatusChip({
    super.key,
    required this.icon,
    required this.label,
    this.tone = Tone.neutral,
    this.onTap,
  });

  final IconData icon;
  final String label;
  final Tone tone;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.tokens.tone(tone);
    return Material(
      color: c.bg,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: c.fg),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  label,
                  overflow: TextOverflow.ellipsis,
                  style: AppType.labelSm.copyWith(color: c.fg),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Location validity, GPS and face status chips for the attendance action.
class AttendanceStatusChips extends ConsumerWidget {
  const AttendanceStatusChips({super.key, required this.today});

  final TodayStatus today;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final preview = ref.watch(locationPreviewProvider);
    final chips = <Widget>[];

    // Distance / validity.
    final value = preview.value;
    if (today.allowAnywhere) {
      chips.add(
        StatusChip(
          icon: Icons.public_rounded,
          label: l.locationAnywhere,
          tone: Tone.success,
        ),
      );
    } else if (value?.fix != null) {
      final check = evaluateGeofence(today, value!.fix!, today.nextDirection);
      chips.add(
        StatusChip(
          key: const Key('chip-geofence'),
          icon: Icons.place_outlined,
          tone: check.inside ? Tone.success : Tone.danger,
          label: check.nearest == null
              ? l.noLocationConfigured
              : check.inside
              ? l.insideLocation(check.nearest!.name)
              : l.distanceFromLocation(
                  f.number(check.distance!.round()),
                  check.nearest!.name,
                ),
          onTap: () => context.push(Routes.gps),
        ),
      );
    } else if (today.locations.isNotEmpty) {
      chips.add(
        StatusChip(
          icon: Icons.place_outlined,
          label: today.locations.map((e) => e.name).join(' · '),
          onTap: () => context.push(Routes.gps),
        ),
      );
    }

    // GPS status.
    final gps = value?.state;
    chips.add(
      StatusChip(
        key: const Key('chip-gps'),
        icon: Icons.gps_fixed_rounded,
        onTap: () => context.push(Routes.gps),
        tone: preview.isLoading
            ? Tone.neutral
            : gps == GpsState.ok
            ? Tone.success
            : Tone.warning,
        label: preview.isLoading
            ? l.gpsChecking
            : switch (gps) {
                GpsState.ok => l.gpsAccuracy(
                  f.number(value!.fix!.accuracy.round()),
                ),
                GpsState.servicesOff => l.gpsOff,
                GpsState.permissionNeeded ||
                GpsState.permissionBlocked => l.gpsPermissionNeeded,
                _ => l.gpsUnavailable,
              },
      ),
    );

    // Face verification status.
    final face = today.face;
    chips.add(
      StatusChip(
        key: const Key('chip-face'),
        icon: Icons.face_retouching_natural_outlined,
        onTap: () => context.push(Routes.face),
        tone: face.isOff
            ? Tone.neutral
            : face.enrolled
            ? Tone.success
            : (face.isRequired ? Tone.danger : Tone.warning),
        label: face.isOff
            ? l.faceOff
            : face.enrolled
            ? (face.isRequired ? l.faceRequiredReady : l.faceOptionalReady)
            : l.faceNotEnrolled,
      ),
    );

    return Wrap(
      alignment: WrapAlignment.center,
      spacing: 8,
      runSpacing: 8,
      children: chips,
    );
  }
}

/// Localised text for a server rejection / review reason code; falls back to
/// the server's own (already translated) message.
String reasonText(AppLocalizations l, String code, String serverMessage) {
  return switch (code) {
    'not_employed' => l.reasonNotEmployed,
    'mobile_disabled' => l.reasonMobileDisabled,
    'poor_accuracy' => l.reasonPoorAccuracy,
    'no_location_configured' => l.reasonNoLocation,
    'outside_geofence' => l.reasonOutsideGeofence,
    'face_required' => l.reasonFaceRequired,
    'face_retry' => l.reasonFaceRetry,
    'mock_location' => l.reasonMockLocation,
    'outside_shift_window' => l.reasonOutsideShiftWindow,
    'face_failed' => l.reasonFaceFailed,
    'face_not_enrolled' => l.reasonFaceNotEnrolled,
    'time_drift' => l.reasonTimeDrift,
    'repeated_attempts' => l.reasonRepeatedAttempts,
    _ => serverMessage.isNotEmpty ? serverMessage : l.errorUnexpected,
  };
}
