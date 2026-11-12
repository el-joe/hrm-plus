import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../services/location_service.dart';

enum GpsState { ok, servicesOff, permissionNeeded, permissionBlocked, failed }

/// A location read for display (home, attendance, GPS validation). Never
/// shows the OS permission prompt by itself.
class LocationPreview {
  const LocationPreview(this.state, [this.fix]);

  final GpsState state;
  final LocationFix? fix;
}

final locationPreviewProvider = FutureProvider.autoDispose<LocationPreview>((
  ref,
) async {
  final service = ref.watch(locationServiceProvider);
  try {
    if (!await service.isServiceEnabled()) {
      return const LocationPreview(GpsState.servicesOff);
    }
    final permission = await service.checkPermission();
    if (permission == LocationPermissionState.permanentlyDenied) {
      return const LocationPreview(GpsState.permissionBlocked);
    }
    if (permission != LocationPermissionState.granted) {
      return const LocationPreview(GpsState.permissionNeeded);
    }
    final fix = await service.currentFix(
      timeLimit: const Duration(seconds: 12),
    );
    return LocationPreview(GpsState.ok, fix);
  } on LocationTimeoutException {
    return const LocationPreview(GpsState.failed);
  } on LocationUnavailableException {
    return const LocationPreview(GpsState.failed);
  } catch (_) {
    // Plugin missing on this platform (web without permission API, tests).
    return const LocationPreview(GpsState.failed);
  }
});
