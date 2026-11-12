import 'dart:typed_data';

import 'package:dio/dio.dart';

/// A file picked for upload (kept in memory; multipart from bytes works on
/// every platform).
class UploadFile {
  const UploadFile({required this.name, required this.bytes});

  final String name;
  final Uint8List bytes;

  MultipartFile toMultipart() => MultipartFile.fromBytes(bytes, filename: name);
}
