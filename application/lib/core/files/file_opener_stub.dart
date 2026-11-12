import 'dart:typed_data';

import 'file_opener.dart';

class _UnsupportedFileOpener implements FileOpener {
  @override
  Future<bool> saveAndOpen(Uint8List bytes, String fileName) async => false;
}

FileOpener createPlatformFileOpener() => _UnsupportedFileOpener();
