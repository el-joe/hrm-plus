import 'dart:typed_data';

import 'package:flutter/widgets.dart';
import 'package:hrm_plus/core/files/file_opener.dart';
import 'package:hrm_plus/core/network/connectivity.dart';
import 'package:hrm_plus/features/attendance/services/camera_permission_service.dart';
import 'package:hrm_plus/features/attendance/services/face_camera.dart';
import 'package:hrm_plus/features/attendance/services/location_service.dart';

/// Main building of the demo branch (30.0285310, 31.4725180, radius 150 m).
const mainBuildingLat = 30.0285310;
const mainBuildingLng = 31.4725180;

LocationFix fixAt(
  double lat,
  double lng, {
  double accuracy = 10,
  bool mocked = false,
}) => LocationFix(
  latitude: lat,
  longitude: lng,
  accuracy: accuracy,
  isMocked: mocked,
  timestamp: DateTime(2026, 11, 12, 8, 10),
);

class FakeLocationService implements LocationService {
  FakeLocationService({
    this.serviceEnabled = true,
    this.permission = LocationPermissionState.granted,
    this.permissionAfterRequest = LocationPermissionState.granted,
    LocationFix? fix,
    this.error,
  }) : fix = fix ?? fixAt(mainBuildingLat, mainBuildingLng);

  bool serviceEnabled;
  LocationPermissionState permission;
  LocationPermissionState permissionAfterRequest;
  LocationFix fix;

  /// Thrown by [currentFix] when set.
  Exception? error;

  int requests = 0;
  int fixes = 0;
  int openedLocationSettings = 0;
  int openedAppSettings = 0;

  @override
  Future<bool> isServiceEnabled() async => serviceEnabled;

  @override
  Future<LocationPermissionState> checkPermission() async => permission;

  @override
  Future<LocationPermissionState> requestPermission() async {
    requests++;
    permission = permissionAfterRequest;
    return permission;
  }

  @override
  Future<LocationFix> currentFix({Duration? timeLimit}) async {
    fixes++;
    final e = error;
    if (e != null) throw e;
    return fix;
  }

  @override
  Future<bool> openLocationSettings() async {
    openedLocationSettings++;
    return true;
  }

  @override
  Future<bool> openAppSettings() async {
    openedAppSettings++;
    return true;
  }
}

class FakeCameraPermissionService implements CameraPermissionService {
  FakeCameraPermissionService({
    this.current = CameraPermissionState.granted,
    this.afterRequest = CameraPermissionState.granted,
  });

  CameraPermissionState current;
  CameraPermissionState afterRequest;
  int requests = 0;
  int openedSettings = 0;

  @override
  Future<CameraPermissionState> status() async => current;

  @override
  Future<CameraPermissionState> request() async {
    requests++;
    current = afterRequest;
    return current;
  }

  @override
  Future<bool> openSettings() async {
    openedSettings++;
    return true;
  }
}

class FakeConnectivity implements ConnectivityChecker {
  FakeConnectivity({this.online = true});

  bool online;

  @override
  Future<bool> isOnline() async => online;
}

class FakeFaceCamera implements FaceCamera {
  FakeFaceCamera({this.initError, this.captureError});

  final FaceCameraError? initError;
  final FaceCameraError? captureError;
  bool _ready = false;
  bool disposed = false;

  @override
  bool get isReady => _ready;

  @override
  Future<void> initialize() async {
    if (initError != null) throw FaceCameraException(initError!);
    _ready = true;
  }

  @override
  Widget preview() => const SizedBox(key: Key('fake-preview'));

  @override
  Future<Uint8List> takePicture() async {
    if (captureError != null) throw FaceCameraException(captureError!);
    return Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xD9]);
  }

  @override
  Future<void> dispose() async => disposed = true;
}

class FakeFileOpener implements FileOpener {
  final opened = <(String, int)>[];

  @override
  Future<bool> saveAndOpen(Uint8List bytes, String fileName) async {
    opened.add((fileName, bytes.length));
    return true;
  }
}
