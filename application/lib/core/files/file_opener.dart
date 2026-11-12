import 'dart:typed_data';

import 'file_opener_stub.dart'
    if (dart.library.io) 'file_opener_io.dart'
    as impl;

/// Saves downloaded bytes (payslip PDF, documents, attachments) to the app's
/// private storage and opens them with the platform viewer.
abstract class FileOpener {
  /// Returns false when the platform cannot open files (e.g. web build).
  Future<bool> saveAndOpen(Uint8List bytes, String fileName);
}

FileOpener createFileOpener() => impl.createPlatformFileOpener();
