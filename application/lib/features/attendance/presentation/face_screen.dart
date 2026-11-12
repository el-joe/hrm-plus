import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/attendance_repository.dart';
import '../domain/models.dart';
import '../services/camera_permission_service.dart';
import 'face_capture_screen.dart';

/// Face verification: consent (versioned), enrolment and withdrawal.
/// Nothing biometric is shown; the provider may be simulated (B4).
class FaceScreen extends ConsumerStatefulWidget {
  const FaceScreen({super.key});

  @override
  ConsumerState<FaceScreen> createState() => _FaceScreenState();
}

class _FaceScreenState extends ConsumerState<FaceScreen> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action, String success) async {
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(faceStatusProvider);
      ref.invalidate(todayProvider);
      if (mounted) showSnack(context, success, tone: SnackTone.success);
    } on ValidationFailure catch (failure) {
      if (mounted) {
        showSnack(
          context,
          failure.first('face') ?? failureMessage(context.l10n, failure),
          tone: SnackTone.error,
        );
      }
    } catch (error) {
      if (mounted) {
        showSnack(
          context,
          failureMessage(context.l10n, error),
          tone: SnackTone.error,
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _enrol(FaceStatus status) async {
    final l = context.l10n;
    final permissions = ref.read(cameraPermissionServiceProvider);
    var permission = await permissions.status();
    if (permission == CameraPermissionState.denied) {
      permission = await permissions.request();
    }
    if (!mounted) return;
    if (permission != CameraPermissionState.granted) {
      showSnack(context, l.issueCameraDeniedBody, tone: SnackTone.error);
      if (permission == CameraPermissionState.permanentlyDenied) {
        await permissions.openSettings();
      }
      return;
    }
    final outcome = await FaceCaptureScreen.open(
      context,
      isSimulated: status.isSimulated,
    );
    if (!mounted || outcome == null) return;
    if (outcome.error != null) {
      showSnack(context, l.issueCameraErrorBody, tone: SnackTone.error);
      return;
    }
    await _run(
      () => ref.read(attendanceRepositoryProvider).enrol(outcome.images),
      l.faceEnrolled,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final face = ref.watch(faceStatusProvider);
    final mode = ref.watch(todayProvider).value?.face.mode;

    return Scaffold(
      appBar: AppBar(title: Text(l.faceTitle)),
      body: AsyncView<FaceStatus>(
        value: face,
        onRefresh: () => ref.refresh(faceStatusProvider.future).then((_) {}),
        builder: (status) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            if (status.isSimulated) ...[
              InlineBanner(
                message: l.faceSimulatedNotice,
                tone: Tone.warning,
                icon: Icons.science_outlined,
              ),
              const SizedBox(height: AppSizes.gap),
            ],
            SectionCard(
              title: l.faceStatus,
              children: [
                if (mode != null)
                  InfoRow(
                    label: l.facePolicy,
                    value: switch (mode) {
                      'required' => l.faceModeRequired,
                      'optional' => l.faceModeOptional,
                      _ => l.faceModeOff,
                    },
                  ),
                InfoRow(
                  label: l.faceConsent,
                  value: status.consentGiven ? l.given : l.notGiven,
                ),
                InfoRow(
                  label: l.faceEnrolment,
                  value: status.enrolled
                      ? l.enrolledOn(f.date(status.enrolledAt?.toLocal()))
                      : l.notEnrolled,
                ),
                if (status.expiresAt != null)
                  InfoRow(
                    label: l.expires,
                    value: f.date(status.expiresAt!.toLocal()),
                  ),
              ],
            ),
            const SizedBox(height: AppSizes.gap),
            if (!status.consentGiven)
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(l.faceConsentTitle, style: AppType.headingH5),
                    const SizedBox(height: 8),
                    Text(
                      l.faceConsentBody(status.consentVersion),
                      style: AppType.bodyMd.copyWith(color: t.textSecondary),
                    ),
                    const SizedBox(height: 16),
                    LoadingButton(
                      label: l.faceGiveConsent,
                      loading: _busy,
                      onPressed: () => _run(
                        () => ref
                            .read(attendanceRepositoryProvider)
                            .giveConsent(status.consentVersion),
                        l.faceConsentSaved,
                      ),
                    ),
                  ],
                ),
              )
            else ...[
              LoadingButton(
                key: const Key('face-enrol'),
                label: status.enrolled ? l.faceReEnrol : l.enrolFace,
                icon: Icons.camera_alt_outlined,
                loading: _busy,
                onPressed: () => _enrol(status),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                  foregroundColor: t.actionDanger,
                ),
                onPressed: _busy
                    ? null
                    : () async {
                        final ok = await confirmDialog(
                          context,
                          title: l.faceWithdraw,
                          message: l.faceWithdrawConfirm,
                          confirmLabel: l.faceWithdraw,
                          danger: true,
                        );
                        if (ok) {
                          await _run(
                            () => ref
                                .read(attendanceRepositoryProvider)
                                .withdrawConsent(),
                            l.faceWithdrawn,
                          );
                        }
                      },
                child: Text(l.faceWithdraw),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
