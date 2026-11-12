import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failures.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

/// One downloadable document (`me/documents/all` item).
class EmployeeDocument {
  const EmployeeDocument({
    required this.id,
    required this.type,
    required this.title,
    required this.subtitle,
    required this.date,
    required this.expiryDate,
    required this.isExpired,
    required this.fileName,
    required this.fileSize,
    required this.downloadUrl,
  });

  factory EmployeeDocument.fromJson(Json json) => EmployeeDocument(
    id: json.integer('id'),
    type: json.str('type'),
    title: json.str('title'),
    subtitle: json.strOrNull('subtitle') ?? json.strOrNull('category_label'),
    date: json.strOrNull('date') ?? json.strOrNull('issue_date'),
    expiryDate: json.strOrNull('expiry_date'),
    isExpired: json.boolean('is_expired'),
    fileName: json.strOrNull('file_name'),
    fileSize: json.intOrNull('file_size'),
    downloadUrl: json.strOrNull('download_url'),
  );

  final int id;

  /// document | contract | certificate | letter | payslip
  final String type;
  final String title;
  final String? subtitle;
  final String? date;
  final String? expiryDate;
  final bool isExpired;
  final String? fileName;
  final int? fileSize;
  final String? downloadUrl;
}

class DocumentsRepository {
  DocumentsRepository(this._api);

  final ApiClient _api;

  /// Grouped documents. Falls back to `me/documents` (shared documents only)
  /// on servers without the consolidated endpoint.
  Future<Map<String, List<EmployeeDocument>>> all() async {
    try {
      final data = (await _api.get('me/documents/all')).json;
      return {
        for (final group in const [
          'documents',
          'contracts',
          'certificates',
          'letters',
          'payslips',
        ])
          group: data.objList(group).map(EmployeeDocument.fromJson).toList(),
      };
    } on NotFoundFailure {
      final list = (await _api.get(
        'me/documents',
        query: {'per_page': 100},
      )).list;
      return {'documents': list.map(EmployeeDocument.fromJson).toList()};
    }
  }
}

final documentsRepositoryProvider = Provider<DocumentsRepository>(
  (ref) => DocumentsRepository(ref.watch(apiClientProvider)),
);

final documentsProvider =
    FutureProvider.autoDispose<Map<String, List<EmployeeDocument>>>(
      (ref) => ref.watch(documentsRepositoryProvider).all(),
    );
