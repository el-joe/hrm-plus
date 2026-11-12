import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Why the camera could not be used.
enum FaceCameraError { noCamera, permissionDenied, failed }

class FaceCameraException implements Exception {
  const FaceCameraException(this.error, [this.detail]);
  final FaceCameraError error;
  final String? detail;
}

/// The front camera used for face verification, behind an interface so the
/// capture screen can be tested without a device.
abstract class FaceCamera {
  /// Throws [FaceCameraException].
  Future<void> initialize();
  Widget preview();
  bool get isReady;

  /// A JPEG frame. Throws [FaceCameraException].
  Future<Uint8List> takePicture();
  Future<void> dispose();
}

class DeviceFaceCamera implements FaceCamera {
  CameraController? _controller;

  @override
  bool get isReady => _controller?.value.isInitialized ?? false;

  @override
  Future<void> initialize() async {
    try {
      final cameras = await availableCameras();
      if (cameras.isEmpty) {
        throw const FaceCameraException(FaceCameraError.noCamera);
      }
      final front = cameras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );
      // Medium keeps frames well under the server's 4 MB image limit.
      final controller = CameraController(
        front,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.jpeg,
      );
      _controller = controller;
      await controller.initialize();
    } on CameraException catch (error) {
      throw FaceCameraException(_map(error.code), error.description);
    } on FaceCameraException {
      rethrow;
    } catch (error) {
      throw FaceCameraException(FaceCameraError.failed, error.toString());
    }
  }

  FaceCameraError _map(String code) => switch (code) {
    'CameraAccessDenied' ||
    'CameraAccessDeniedWithoutPrompt' ||
    'CameraAccessRestricted' ||
    'cameraPermission' => FaceCameraError.permissionDenied,
    _ => FaceCameraError.failed,
  };

  @override
  Widget preview() {
    final controller = _controller;
    if (controller == null) return const SizedBox.shrink();
    return CameraPreview(controller);
  }

  @override
  Future<Uint8List> takePicture() async {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      throw const FaceCameraException(FaceCameraError.failed, 'not ready');
    }
    try {
      final file = await controller.takePicture();
      return await file.readAsBytes();
    } on CameraException catch (error) {
      throw FaceCameraException(_map(error.code), error.description);
    }
  }

  @override
  Future<void> dispose() async {
    await _controller?.dispose();
    _controller = null;
  }
}

/// A new camera per capture screen.
final faceCameraFactoryProvider = Provider<FaceCamera Function()>(
  (ref) => DeviceFaceCamera.new,
);
