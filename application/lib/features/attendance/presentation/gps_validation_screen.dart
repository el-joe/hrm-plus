import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../application/location_preview.dart';
import '../data/attendance_repository.dart';
import '../domain/models.dart';
import '../services/location_service.dart';

/// GPS location validation: current fix, accuracy against the policy limit
/// and the distance to every allowed location. Display only — the server
/// validates again on check-in / check-out.
class GpsValidationScreen extends ConsumerWidget {
  const GpsValidationScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(locationPreviewProvider);
    ref.invalidate(todayProvider);
    await ref.read(todayProvider.future).then((_) {}, onError: (_) {});
  }

  Future<void> _grant(WidgetRef ref) async {
    final service = ref.read(locationServiceProvider);
    if (!await service.isServiceEnabled()) {
      await service.openLocationSettings();
    } else {
      final status = await service.requestPermission();
      if (status == LocationPermissionState.permanentlyDenied) {
        await service.openAppSettings();
      }
    }
    ref.invalidate(locationPreviewProvider);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final today = ref.watch(todayProvider);
    final preview = ref.watch(locationPreviewProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.gpsTitle)),
      body: AsyncView<TodayStatus>(
        value: today,
        onRefresh: () => _refresh(ref),
        builder: (status) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            InlineBanner(message: l.gpsExplanation, tone: Tone.info),
            const SizedBox(height: AppSizes.gap),
            _GpsCard(
              preview: preview,
              rules: status.rules,
              onGrant: () => _grant(ref),
              onRefresh: () => ref.invalidate(locationPreviewProvider),
            ),
            const SizedBox(height: AppSizes.gap),
            _LocationsCard(status: status, fix: preview.value?.fix),
          ],
        ),
      ),
    );
  }
}

class _GpsCard extends ConsumerWidget {
  const _GpsCard({
    required this.preview,
    required this.rules,
    required this.onGrant,
    required this.onRefresh,
  });

  final AsyncValue<LocationPreview> preview;
  final AttendanceRules rules;
  final VoidCallback onGrant;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final value = preview.value;

    if (preview.isLoading) {
      return SectionCard(
        title: l.gpsStatus,
        children: [
          Row(
            children: [
              const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
              const SizedBox(width: 12),
              Text(l.locating),
            ],
          ),
        ],
      );
    }

    final fix = value?.fix;
    final accuracyOk = fix != null && fix.accuracy <= rules.maxAccuracyMeters;
    return SectionCard(
      title: l.gpsStatus,
      trailing: IconButton(
        tooltip: l.refresh,
        onPressed: onRefresh,
        icon: const Icon(Icons.refresh_rounded),
      ),
      children: [
        switch (value?.state) {
          GpsState.ok => StatusBadge(
            label: l.gpsReady,
            tone: accuracyOk ? Tone.success : Tone.warning,
          ),
          GpsState.servicesOff => StatusBadge(
            label: l.gpsOff,
            tone: Tone.danger,
          ),
          GpsState.permissionNeeded || GpsState.permissionBlocked =>
            StatusBadge(label: l.gpsPermissionNeeded, tone: Tone.warning),
          _ => StatusBadge(label: l.gpsUnavailable, tone: Tone.danger),
        },
        const SizedBox(height: 8),
        if (fix != null) ...[
          InfoRow(
            label: l.accuracy,
            value: l.metersShort(f.number(fix.accuracy.round())),
          ),
          InfoRow(
            label: l.maxAccuracy,
            value: l.metersShort(f.number(rules.maxAccuracyMeters)),
          ),
          InfoRow(
            label: l.coordinates,
            value: f.d(
              '${fix.latitude.toStringAsFixed(6)}, ${fix.longitude.toStringAsFixed(6)}',
            ),
          ),
          if (!accuracyOk)
            InlineBanner(message: l.poorAccuracyHint, tone: Tone.warning),
          if (fix.isMocked)
            InlineBanner(message: l.mockLocationDetected, tone: Tone.warning),
        ] else if (value?.state != GpsState.failed)
          FilledButton.icon(
            onPressed: onGrant,
            icon: const Icon(Icons.my_location_rounded),
            label: Text(
              value?.state == GpsState.servicesOff
                  ? l.openLocationSettings
                  : l.allowLocation,
            ),
          )
        else
          OutlinedButton.icon(
            onPressed: onRefresh,
            icon: const Icon(Icons.refresh_rounded),
            label: Text(l.retry),
          ),
      ],
    );
  }
}

class _LocationsCard extends ConsumerWidget {
  const _LocationsCard({required this.status, required this.fix});

  final TodayStatus status;
  final LocationFix? fix;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final direction = status.nextDirection;

    if (status.allowAnywhere) {
      return SectionCard(
        title: l.allowedLocations,
        children: [Text(l.locationAnywhere)],
      );
    }
    if (status.locations.isEmpty) {
      return SectionCard(
        title: l.allowedLocations,
        children: [Text(l.noLocationConfigured)],
      );
    }
    return SectionCard(
      title: l.allowedLocations,
      children: [
        for (final location in status.locations)
          Builder(
            builder: (context) {
              final radius = location.radiusFor(direction);
              final distance = fix == null
                  ? null
                  : distanceMeters(
                      fix!.latitude,
                      fix!.longitude,
                      location.latitude,
                      location.longitude,
                    );
              final inside = distance != null && distance <= radius;
              return ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  Icons.place_outlined,
                  color: distance == null
                      ? t.textTertiary
                      : (inside
                            ? t.tone(Tone.success).solid
                            : t.tone(Tone.danger).solid),
                ),
                title: Text(location.name),
                subtitle: Text(l.radiusValue(f.number(radius))),
                trailing: distance == null
                    ? null
                    : StatusBadge(
                        label: inside
                            ? l.inside
                            : l.metersAway(f.number(distance.round())),
                        tone: inside ? Tone.success : Tone.danger,
                      ),
              );
            },
          ),
      ],
    );
  }
}
