import 'dart:io';
import 'dart:typed_data';

import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import 'file_opener.dart';

class _IoFileOpener implements FileOpener {
  @override
  Future<bool> saveAndOpen(Uint8List bytes, String fileName) async {
    // App-private temporary directory: payslips and documents are personal
    // data, so they are not written to shared storage.
    final dir = await getTemporaryDirectory();
    final safeName = fileName.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');
    final file = File('${dir.path}/$safeName');
    await file.writeAsBytes(bytes, flush: true);
    final result = await OpenFilex.open(file.path);
    return result.type == ResultType.done;
  }
}

FileOpener createPlatformFileOpener() => _IoFileOpener();
