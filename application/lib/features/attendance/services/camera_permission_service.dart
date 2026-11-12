import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

enum CameraPermissionState { granted, denied, permanentlyDenied }

/// Camera permission (permission_handler), behind an interface for tests.
abstract class CameraPermissionService {
  Future<CameraPermissionState> status();
  Future<CameraPermissionState> request();
  Future<bool> openSettings();
}

class PlatformCameraPermissionService implements CameraPermissionService {
  const PlatformCameraPermissionService();

  CameraPermissionState _map(PermissionStatus status) => switch (status) {
    PermissionStatus.granted ||
    PermissionStatus.limited => CameraPermissionState.granted,
    PermissionStatus.permanentlyDenied ||
    PermissionStatus.restricted => CameraPermissionState.permanentlyDenied,
    _ => CameraPermissionState.denied,
  };

  @override
  Future<CameraPermissionState> status() async {
    try {
      return _map(await Permission.camera.status);
    } catch (_) {
      // Platforms without the plugin (web/desktop): the camera plugin asks.
      return CameraPermissionState.granted;
    }
  }

  @override
  Future<CameraPermissionState> request() async {
    try {
      return _map(await Permission.camera.request());
    } catch (_) {
      return CameraPermissionState.granted;
    }
  }

  @override
  Future<bool> openSettings() => openAppSettings();
}

final cameraPermissionServiceProvider = Provider<CameraPermissionService>(
  (ref) => const PlatformCameraPermissionService(),
);
