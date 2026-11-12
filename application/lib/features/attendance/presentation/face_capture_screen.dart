import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/ui.dart';
import '../services/face_camera.dart';

/// What the capture screen returns to its caller.
class FaceCaptureOutcome {
  const FaceCaptureOutcome.captured(this.images) : error = null;
  const FaceCaptureOutcome.failed(this.error) : images = const [];

  /// Base64 JPEG frames.
  final List<String> images;
  final FaceCameraError? error;
}

/// Front camera preview with a face guide; captures one frame.
/// Pops with a [FaceCaptureOutcome], or null when the user cancels.
class FaceCaptureScreen extends ConsumerStatefulWidget {
  const FaceCaptureScreen({super.key, this.isSimulated = false});

  final bool isSimulated;

  static Future<FaceCaptureOutcome?> open(
    BuildContext context, {
    bool isSimulated = false,
  }) => Navigator.of(context).push<FaceCaptureOutcome>(
    MaterialPageRoute(
      fullscreenDialog: true,
      builder: (_) => FaceCaptureScreen(isSimulated: isSimulated),
    ),
  );

  @override
  ConsumerState<FaceCaptureScreen> createState() => _FaceCaptureScreenState();
}

class _FaceCaptureScreenState extends ConsumerState<FaceCaptureScreen> {
  late final FaceCamera _camera = ref.read(faceCameraFactoryProvider)();
  bool _ready = false;
  bool _capturing = false;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    try {
      await _camera.initialize();
      if (mounted) setState(() => _ready = true);
    } on FaceCameraException catch (error) {
      if (mounted) {
        Navigator.of(context).pop(FaceCaptureOutcome.failed(error.error));
      }
    }
  }

  Future<void> _capture() async {
    setState(() => _capturing = true);
    try {
      final bytes = await _camera.takePicture();
      if (!mounted) return;
      Navigator.of(context)
          .pop(FaceCaptureOutcome.captured([base64Encode(bytes)]));
    } on FaceCameraException catch (error) {
      if (!mounted) return;
      Navigator.of(context).pop(FaceCaptureOutcome.failed(error.error));
    }
  }

  @override
  void dispose() {
    _camera.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = context.tokens;
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(l.faceVerification),
      ),
      body: SafeArea(
        child: Column(
          children: [
            if (widget.isSimulated)
              Padding(
                padding: const EdgeInsets.all(12),
                child: InlineBanner(
                  message: l.faceSimulatedNotice,
                  tone: Tone.warning,
                ),
              ),
            Expanded(
              child: Center(
                child: _ready
                    ? Stack(
                        alignment: Alignment.center,
                        children: [
                          _camera.preview(),
                          IgnorePointer(
                            child: Container(
                              width: 220,
                              height: 290,
                              decoration: BoxDecoration(
                                border: Border.all(
                                  color: t.borderFocus,
                                  width: 3,
                                ),
                                borderRadius: const BorderRadius.all(
                                  Radius.elliptical(110, 145),
                                ),
                              ),
                            ),
                          ),
                        ],
                      )
                    : const CircularProgressIndicator(color: Colors.white),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSizes.pagePadding),
              child: Column(
                children: [
                  Text(
                    l.faceCaptureHint,
                    textAlign: TextAlign.center,
                    style: AppType.bodyMd.copyWith(color: Colors.white),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: LoadingButton(
                      key: const Key('face-capture'),
                      label: l.capture,
                      icon: Icons.camera_alt_rounded,
                      loading: _capturing,
                      onPressed: _ready && !_capturing ? _capture : null,
                    ),
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
