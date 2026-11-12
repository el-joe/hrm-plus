import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/ui.dart';
import '../application/check_flow_controller.dart';
import '../data/attendance_repository.dart';
import '../domain/models.dart';
import 'face_capture_screen.dart';
import 'widgets/attendance_widgets.dart';

/// Check-in and check-out: permission rationale → location → geofence →
/// camera → face → submit → the server's final result.
class CheckScreen extends ConsumerStatefulWidget {
  const CheckScreen({super.key, required this.direction});

  final AttendanceDirection direction;

  @override
  ConsumerState<CheckScreen> createState() => _CheckScreenState();
}

class _CheckScreenState extends ConsumerState<CheckScreen> {
  CheckFlowController get _flow =>
      ref.read(checkFlowProvider(widget.direction).notifier);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _flow.start());
  }

  Future<void> _openCamera(CheckFlowState state) async {
    if (!await _flow.ensureCameraPermission()) return;
    if (!mounted) return;
    final outcome = await FaceCaptureScreen.open(
      context,
      isSimulated: state.today?.face.isSimulated ?? false,
    );
    if (!mounted) return;
    if (outcome == null) {
      _flow.faceCaptureCancelled();
    } else if (outcome.error != null) {
      _flow.faceCaptureFailed(outcome.error!);
    } else {
      await _flow.faceCaptured(outcome.images);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = ref.watch(checkFlowProvider(widget.direction));
    final checkIn = widget.direction == AttendanceDirection.checkIn;
    final busy =
        state.stage == CheckStage.submitting ||
        state.stage == CheckStage.locating;

    return PopScope(
      // Do not leave while the request is in flight.
      canPop: state.stage != CheckStage.submitting,
      child: Scaffold(
        appBar: AppBar(title: Text(checkIn ? l.checkIn : l.checkOut)),
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(AppSizes.pagePadding),
            children: [
              _Steps(state: state),
              const SizedBox(height: 24),
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 200),
                child: KeyedSubtree(
                  key: ValueKey(state.stage),
                  child: switch (state.stage) {
                    CheckStage.preparing => _Progress(text: l.preparing),
                    CheckStage.rationale => _Rationale(
                      onContinue: _flow.acceptRationale,
                    ),
                    CheckStage.locating => _Progress(text: l.locating),
                    CheckStage.awaitingFace => _FaceStep(
                      state: state,
                      canSkip: _flow.canSkipFace,
                      onOpenCamera: () => _openCamera(state),
                      onSkip: _flow.continueWithoutFace,
                    ),
                    CheckStage.submitting => _Progress(text: l.submitting),
                    CheckStage.finished => _ResultView(
                      state: state,
                      direction: widget.direction,
                      onRetry: _flow.retryAfterRejection,
                    ),
                    CheckStage.blocked => _IssueView(
                      issue: state.issue!,
                      onRetry: _flow.retry,
                      onOpenSettings: _flow.openSettings,
                    ),
                  },
                ),
              ),
              if (state.fix != null && !busy) ...[
                const SizedBox(height: 24),
                _LocationSummary(state: state),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Steps extends StatelessWidget {
  const _Steps({required this.state});

  final CheckFlowState state;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = context.tokens;
    final faceIncluded = state.today?.face.shouldCapture ?? true;
    final current = switch (state.stage) {
      CheckStage.preparing || CheckStage.rationale || CheckStage.locating => 0,
      CheckStage.awaitingFace => 1,
      CheckStage.submitting => 2,
      CheckStage.finished => 3,
      CheckStage.blocked => switch (state.issue?.kind) {
        IssueKind.cameraPermissionDenied ||
        IssueKind.cameraPermissionPermanentlyDenied ||
        IssueKind.cameraUnavailable ||
        IssueKind.noCamera ||
        IssueKind.faceNotEnrolled => 1,
        IssueKind.submitFailed => 2,
        _ => 0,
      },
    };
    final steps = [
      (Icons.place_outlined, l.stepLocation),
      if (faceIncluded) (Icons.face_outlined, l.stepFace),
      (Icons.cloud_upload_outlined, l.stepSubmit),
    ];
    int stepIndex(int i) => faceIncluded ? i : (i >= 1 ? i + 1 : i);

    return Row(
      children: [
        for (var i = 0; i < steps.length; i++) ...[
          Expanded(
            child: Column(
              children: [
                CircleAvatar(
                  radius: 18,
                  backgroundColor: stepIndex(i) <= current
                      ? t.actionPrimary
                      : t.surfaceMuted,
                  child: Icon(
                    steps[i].$1,
                    size: 18,
                    color: stepIndex(i) <= current
                        ? t.textOnPrimary
                        : t.textTertiary,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  steps[i].$2,
                  style: AppType.labelSm.copyWith(color: t.textSecondary),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _Progress extends StatelessWidget {
  const _Progress({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 48),
    child: Column(
      children: [
        const CircularProgressIndicator(),
        const SizedBox(height: 16),
        Text(text, textAlign: TextAlign.center, style: AppType.bodyLg),
      ],
    ),
  );
}

class _Rationale extends StatelessWidget {
  const _Rationale({required this.onContinue});

  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = context.tokens;
    return AppCard(
      key: const Key('location-rationale'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Icon(Icons.location_on_outlined, size: 48, color: t.actionPrimary),
          const SizedBox(height: 12),
          Text(
            l.locationRationaleTitle,
            textAlign: TextAlign.center,
            style: AppType.headingH4,
          ),
          const SizedBox(height: 8),
          Text(
            l.locationRationaleBody,
            textAlign: TextAlign.center,
            style: AppType.bodyMd.copyWith(color: t.textSecondary),
          ),
          const SizedBox(height: 20),
          FilledButton(onPressed: onContinue, child: Text(l.continueLabel)),
          TextButton(
            onPressed: () => Navigator.of(context).maybePop(),
            child: Text(l.cancel),
          ),
        ],
      ),
    );
  }
}

class _FaceStep extends StatelessWidget {
  const _FaceStep({
    required this.state,
    required this.canSkip,
    required this.onOpenCamera,
    required this.onSkip,
  });

  final CheckFlowState state;
  final bool canSkip;
  final VoidCallback onOpenCamera;
  final VoidCallback onSkip;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = context.tokens;
    final face = state.today?.face;
    return AppCard(
      key: const Key('face-step'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Icon(
            Icons.face_retouching_natural_outlined,
            size: 48,
            color: t.actionPrimary,
          ),
          const SizedBox(height: 12),
          Text(
            l.faceStepTitle,
            textAlign: TextAlign.center,
            style: AppType.headingH4,
          ),
          const SizedBox(height: 8),
          Text(
            l.cameraRationale,
            textAlign: TextAlign.center,
            style: AppType.bodyMd.copyWith(color: t.textSecondary),
          ),
          if (face?.isSimulated ?? false) ...[
            const SizedBox(height: 12),
            InlineBanner(message: l.faceSimulatedNotice, tone: Tone.warning),
          ],
          const SizedBox(height: 20),
          FilledButton.icon(
            key: const Key('open-camera'),
            onPressed: onOpenCamera,
            icon: const Icon(Icons.camera_alt_outlined),
            label: Text(l.openCamera),
          ),
          if (canSkip)
            TextButton(onPressed: onSkip, child: Text(l.continueWithoutFace)),
        ],
      ),
    );
  }
}

class _IssueView extends ConsumerWidget {
  const _IssueView({
    required this.issue,
    required this.onRetry,
    required this.onOpenSettings,
  });

  final FlowIssue issue;
  final VoidCallback onRetry;
  final VoidCallback onOpenSettings;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);

    final (IconData icon, String title, String message) = switch (issue.kind) {
      IssueKind.offline => (
        Icons.wifi_off_rounded,
        l.issueOfflineTitle,
        l.issueOfflineBody,
      ),
      IssueKind.todayFailed => (
        Icons.error_outline_rounded,
        l.errorTitle,
        failureMessage(l, issue.failure),
      ),
      IssueKind.notEmployed => (
        Icons.block_rounded,
        l.reasonNotEmployed,
        l.contactHr,
      ),
      IssueKind.mobileDisabled => (
        Icons.phonelink_off_rounded,
        l.reasonMobileDisabled,
        l.contactHr,
      ),
      IssueKind.locationServicesOff => (
        Icons.location_disabled_rounded,
        l.issueLocationOffTitle,
        l.issueLocationOffBody,
      ),
      IssueKind.locationPermissionDenied => (
        Icons.location_off_outlined,
        l.issueLocationDeniedTitle,
        l.issueLocationDeniedBody,
      ),
      IssueKind.locationPermissionPermanentlyDenied => (
        Icons.location_off_outlined,
        l.issueLocationDeniedTitle,
        l.issueLocationBlockedBody,
      ),
      IssueKind.gpsTimeout => (
        Icons.gps_not_fixed_rounded,
        l.issueGpsTimeoutTitle,
        l.issueGpsTimeoutBody,
      ),
      IssueKind.gpsUnavailable => (
        Icons.gps_off_rounded,
        l.issueGpsFailedTitle,
        l.issueGpsFailedBody,
      ),
      IssueKind.poorAccuracy => (
        Icons.gps_not_fixed_rounded,
        l.issuePoorAccuracyTitle,
        l.issuePoorAccuracyBody(
          f.number(issue.accuracy?.round() ?? 0),
          f.number(issue.maxAccuracy ?? 0),
        ),
      ),
      IssueKind.outsideGeofence => (
        Icons.wrong_location_outlined,
        l.issueOutsideTitle,
        l.issueOutsideBody(
          f.number(issue.distance?.round() ?? 0),
          issue.locationName ?? '',
          f.number(issue.radius ?? 0),
        ),
      ),
      IssueKind.noLocationConfigured => (
        Icons.wrong_location_outlined,
        l.noLocationConfigured,
        l.contactHr,
      ),
      IssueKind.cameraPermissionDenied => (
        Icons.no_photography_outlined,
        l.issueCameraDeniedTitle,
        l.issueCameraDeniedBody,
      ),
      IssueKind.cameraPermissionPermanentlyDenied => (
        Icons.no_photography_outlined,
        l.issueCameraDeniedTitle,
        l.issueCameraBlockedBody,
      ),
      IssueKind.cameraUnavailable => (
        Icons.videocam_off_outlined,
        l.issueCameraErrorTitle,
        l.issueCameraErrorBody,
      ),
      IssueKind.noCamera => (
        Icons.videocam_off_outlined,
        l.issueCameraErrorTitle,
        l.issueNoCameraBody,
      ),
      IssueKind.faceNotEnrolled => (
        Icons.face_outlined,
        l.faceNotEnrolled,
        l.issueFaceNotEnrolledBody,
      ),
      IssueKind.submitFailed => (
        Icons.cloud_off_rounded,
        l.issueSubmitFailedTitle,
        _submitMessage(l, issue.failure),
      ),
    };

    return AppCard(
      key: Key('issue-${issue.kind.name}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Icon(icon, size: 48, color: t.tone(Tone.danger).solid),
          const SizedBox(height: 12),
          Text(title, textAlign: TextAlign.center, style: AppType.headingH4),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: AppType.bodyMd.copyWith(color: t.textSecondary),
          ),
          const SizedBox(height: 8),
          Text(
            l.notRecordedNote,
            textAlign: TextAlign.center,
            style: AppType.captionMd.copyWith(color: t.textTertiary),
          ),
          const SizedBox(height: 20),
          if (issue.opensSettings)
            FilledButton.icon(
              onPressed: onOpenSettings,
              icon: const Icon(Icons.settings_outlined),
              label: Text(
                issue.kind == IssueKind.locationServicesOff
                    ? l.openLocationSettings
                    : l.openAppSettings,
              ),
            ),
          if (issue.kind == IssueKind.faceNotEnrolled)
            FilledButton(
              onPressed: () => context.pushReplacement(Routes.face),
              child: Text(l.enrolFace),
            ),
          if (issue.retryable) ...[
            const SizedBox(height: 8),
            OutlinedButton.icon(
              key: const Key('flow-retry'),
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: Text(l.retry),
            ),
          ],
          TextButton(
            onPressed: () => Navigator.of(context).maybePop(),
            child: Text(l.close),
          ),
        ],
      ),
    );
  }

  String _submitMessage(AppLocalizations l, Object? failure) {
    if (failure is TimeoutFailure || failure is ServerFailure) {
      return '${failureMessage(l, failure)} ${l.retrySafeNote}';
    }
    return failureMessage(l, failure);
  }
}

class _ResultView extends ConsumerWidget {
  const _ResultView({
    required this.state,
    required this.direction,
    required this.onRetry,
  });

  final CheckFlowState state;
  final AttendanceDirection direction;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final result = state.result!;
    final attempt = result.attempt;
    final checkIn = direction == AttendanceDirection.checkIn;

    final (
      Tone tone,
      IconData icon,
      String title,
      String body,
    ) = switch (result) {
      CheckAccepted() => (
        Tone.success,
        Icons.check_circle_rounded,
        checkIn ? l.checkInRecorded : l.checkOutRecorded,
        l.recordedAt(f.clock(attempt.serverTimeLocal)),
      ),
      CheckFlagged() => (
        Tone.warning,
        Icons.hourglass_top_rounded,
        l.sentForReviewTitle,
        l.sentForReviewBody,
      ),
      CheckRejected() => (
        Tone.danger,
        Icons.cancel_rounded,
        l.rejectedTitle,
        l.rejectedBody,
      ),
    };
    final colors = t.tone(tone);
    final rejected = result is CheckRejected;
    final faceRemaining = rejected ? result.faceRemainingAttempts : null;

    return AppCard(
      key: Key('result-${attempt.outcome}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Icon(icon, size: 56, color: colors.solid),
          const SizedBox(height: 12),
          Text(title, textAlign: TextAlign.center, style: AppType.headingH3),
          const SizedBox(height: 8),
          Text(
            body,
            textAlign: TextAlign.center,
            style: AppType.bodyMd.copyWith(color: t.textSecondary),
          ),
          if (attempt.reasons.isNotEmpty) ...[
            const SizedBox(height: 16),
            for (final reason in attempt.reasons)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: InlineBanner(
                  message: reasonText(l, reason.code, reason.message),
                  tone: tone,
                ),
              ),
          ],
          if (attempt.distanceMeters != null &&
              attempt.withinGeofence == false) ...[
            InfoRow(
              label: l.distance,
              value: l.distanceFromLocation(
                f.number(attempt.distanceMeters!),
                attempt.locationName ?? '',
              ),
            ),
            if (attempt.allowedRadiusMeters != null)
              InfoRow(
                label: l.allowedRadius,
                value: l.metersShort(f.number(attempt.allowedRadiusMeters!)),
              ),
          ],
          if (faceRemaining != null)
            InfoRow(label: l.faceAttemptsLeft, value: f.number(faceRemaining)),
          if (attempt.faceSimulated)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                l.faceSimulatedNotice,
                textAlign: TextAlign.center,
                style: AppType.captionMd.copyWith(color: t.textTertiary),
              ),
            ),
          const SizedBox(height: 20),
          if (rejected && _canRetry(result))
            FilledButton.icon(
              key: const Key('retry-after-rejection'),
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: Text(l.tryAgain),
            ),
          const SizedBox(height: 8),
          OutlinedButton(
            key: const Key('result-done'),
            onPressed: () =>
                context.canPop() ? context.pop() : context.go(Routes.home),
            child: Text(l.done),
          ),
        ],
      ),
    );
  }

  bool _canRetry(CheckRejected result) {
    final codes = result.attempt.reasons.map((r) => r.code).toSet();
    if (codes.contains('face_retry')) {
      return (result.faceRemainingAttempts ?? 0) > 0;
    }
    // Rejections the employee can fix by moving or waiting for a better fix.
    return codes.isEmpty ||
        codes.contains('poor_accuracy') ||
        codes.contains('outside_geofence') ||
        codes.contains('mock_location');
  }
}

class _LocationSummary extends ConsumerWidget {
  const _LocationSummary({required this.state});

  final CheckFlowState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final fix = state.fix!;
    final geofence = state.geofence;
    return SectionCard(
      title: l.gpsTitle,
      children: [
        InfoRow(
          label: l.accuracy,
          value: l.metersShort(f.number(fix.accuracy.round())),
        ),
        if (geofence?.nearest != null) ...[
          InfoRow(label: l.nearestLocation, value: geofence!.nearest!.name),
          InfoRow(
            label: l.distance,
            value: l.metersShort(f.number(geofence.distance!.round())),
          ),
          InfoRow(
            label: l.allowedRadius,
            value: l.metersShort(f.number(geofence.radius ?? 0)),
          ),
        ],
        if (geofence?.allowAnywhere ?? false)
          InfoRow(label: l.allowedLocations, value: l.locationAnywhere),
        if (fix.isMocked)
          InlineBanner(message: l.mockLocationDetected, tone: Tone.warning),
      ],
    );
  }
}
