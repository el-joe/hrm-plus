import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/files/upload_file.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

class HrRequestSummary {
  const HrRequestSummary({
    required this.id,
    required this.reference,
    required this.subject,
    required this.status,
    required this.statusLabel,
    required this.categoryName,
    required this.canCancel,
    required this.canReply,
    this.submittedAt,
    this.updatedAt,
  });

  factory HrRequestSummary.fromJson(Json json) => HrRequestSummary(
    id: json.integer('id'),
    reference: json.str('reference'),
    subject: json.str('subject'),
    status: json.str('status'),
    statusLabel: json.str('status_label'),
    categoryName: json.obj('category')?.str('name') ?? '',
    canCancel: json.boolean('can_cancel'),
    canReply: json.boolean('can_reply'),
    submittedAt: json.date('submitted_at'),
    updatedAt: json.date('updated_at'),
  );

  final int id;
  final String reference;
  final String subject;
  final String status;
  final String statusLabel;
  final String categoryName;
  final bool canCancel;
  final bool canReply;
  final DateTime? submittedAt;
  final DateTime? updatedAt;
}

class RequestMessage {
  const RequestMessage({
    required this.id,
    required this.body,
    required this.fromEmployee,
    required this.authorName,
    this.createdAt,
  });

  factory RequestMessage.fromJson(Json json) => RequestMessage(
    id: json.integer('id'),
    body: json.str('body'),
    fromEmployee: json.boolean('from_employee'),
    authorName: json.str('author_name'),
    createdAt: json.date('created_at'),
  );

  final int id;
  final String body;
  final bool fromEmployee;
  final String authorName;
  final DateTime? createdAt;
}

class RequestAttachment {
  const RequestAttachment({
    required this.id,
    required this.name,
    required this.downloadUrl,
  });

  factory RequestAttachment.fromJson(Json json) => RequestAttachment(
    id: json.integer('id'),
    name: json.str('name'),
    downloadUrl: json.strOrNull('download_url'),
  );

  final int id;
  final String name;
  final String? downloadUrl;
}

class HrRequestDetail {
  const HrRequestDetail({
    required this.summary,
    required this.description,
    required this.fields,
    required this.messages,
    required this.attachments,
    required this.history,
    this.resolutionNote,
    this.rejectionReason,
    this.priorityLabel,
  });

  factory HrRequestDetail.fromJson(Json json) => HrRequestDetail(
    summary: HrRequestSummary.fromJson(json),
    description: json.strOrNull('description'),
    resolutionNote: json.strOrNull('resolution_note'),
    rejectionReason: json.strOrNull('rejection_reason'),
    priorityLabel: json.strOrNull('priority_label'),
    fields: [
      for (final f in json.objList('fields')) (f.str('label'), f.str('value')),
    ],
    messages: json.objList('messages').map(RequestMessage.fromJson).toList(),
    attachments: json
        .objList('attachments')
        .map(RequestAttachment.fromJson)
        .toList(),
    history: json.objList('history'),
  );

  final HrRequestSummary summary;
  final String? description;
  final String? resolutionNote;
  final String? rejectionReason;
  final String? priorityLabel;
  final List<(String, String)> fields;
  final List<RequestMessage> messages;
  final List<RequestAttachment> attachments;
  final List<Json> history;
}

class CategoryField {
  const CategoryField({
    required this.key,
    required this.label,
    required this.type,
    required this.options,
    required this.required,
  });

  factory CategoryField.fromJson(Json json) => CategoryField(
    key: json.str('key'),
    label: json.str('label'),
    type: json.str('type', 'text'),
    options: json.strList('options'),
    required: json.boolean('required'),
  );

  final String key;
  final String label;

  /// text | textarea | number | date | select
  final String type;
  final List<String> options;
  final bool required;
}

class RequestCategory {
  const RequestCategory({
    required this.id,
    required this.code,
    required this.name,
    required this.type,
    required this.requiresAttachment,
    required this.requiresDescription,
    required this.letterLanguages,
    required this.fields,
    this.description,
  });

  factory RequestCategory.fromJson(Json json) => RequestCategory(
    id: json.integer('id'),
    code: json.str('code'),
    name: json.str('name'),
    description: json.strOrNull('description'),
    type: json.str('type', 'general'),
    requiresAttachment: json.boolean('requires_attachment'),
    requiresDescription: json.boolean('requires_description'),
    letterLanguages: json.strList('letter_languages'),
    fields: json.objList('fields').map(CategoryField.fromJson).toList(),
  );

  final int id;
  final String code;
  final String name;
  final String? description;

  /// letter | general | profile_change
  final String type;
  final bool requiresAttachment;
  final bool requiresDescription;
  final List<String> letterLanguages;
  final List<CategoryField> fields;

  bool get isLetter => type == 'letter';
  bool get isProfileChange => type == 'profile_change';
}

class RequestCategories {
  const RequestCategories({
    required this.categories,
    required this.enabled,
    required this.maxFiles,
    required this.extensions,
  });

  final List<RequestCategory> categories;
  final bool enabled;
  final int maxFiles;
  final List<String> extensions;
}

class ProfileField {
  const ProfileField({
    required this.field,
    required this.group,
    required this.label,
    required this.current,
    required this.masked,
  });

  factory ProfileField.fromJson(Json json) => ProfileField(
    field: json.str('field'),
    group: json.str('group'),
    label: json.str('label'),
    current: json.strOrNull('current'),
    masked: json.boolean('masked'),
  );

  final String field;
  final String group;
  final String label;
  final String? current;
  final bool masked;
}

/// Input of a new HR request.
class RequestDraft {
  const RequestDraft({
    required this.categoryId,
    this.subject,
    this.description,
    this.priority,
    this.letterLanguage,
    this.fields = const {},
    this.changes = const {},
    this.attachments = const [],
  });

  final int categoryId;
  final String? subject;
  final String? description;
  final String? priority;
  final String? letterLanguage;
  final Map<String, String> fields;

  /// Profile change request: field → requested value.
  final Map<String, String> changes;
  final List<UploadFile> attachments;

  FormData toForm() {
    final form = FormData();
    void add(String key, String? value) {
      if (value != null && value.trim().isNotEmpty) {
        form.fields.add(MapEntry(key, value.trim()));
      }
    }

    add('hr_request_category_id', '$categoryId');
    add('subject', subject);
    add('description', description);
    add('priority', priority);
    add('letter_language', letterLanguage);
    fields.forEach((key, value) => add('fields[$key]', value));
    changes.forEach((key, value) => add('changes[$key]', value));
    for (final file in attachments) {
      form.files.add(MapEntry('attachments[]', file.toMultipart()));
    }
    return form;
  }
}

class RequestsRepository {
  RequestsRepository(this._api);

  final ApiClient _api;

  Future<Paged<HrRequestSummary>> list({int page = 1, String? status}) async {
    final response = await _api.get(
      'me/requests',
      query: {'page': page, 'status': status},
    );
    return Paged(
      items: response.list.map(HrRequestSummary.fromJson).toList(),
      pagination: response.pagination,
    );
  }

  Future<RequestCategories> categories() async {
    final response = await _api.get('me/requests/categories');
    final attachments = response.meta.obj('attachments') ?? const {};
    return RequestCategories(
      categories: response.list.map(RequestCategory.fromJson).toList(),
      enabled: response.meta.boolean('enabled', true),
      maxFiles: attachments.integer('max_files', 5),
      extensions: attachments.strList('extensions'),
    );
  }

  Future<List<ProfileField>> profileFields() async =>
      (await _api.get('me/requests/profile-fields')).list
          .map(ProfileField.fromJson)
          .toList();

  Future<HrRequestDetail> show(int id) async =>
      HrRequestDetail.fromJson((await _api.get('me/requests/$id')).json);

  Future<HrRequestDetail> create(RequestDraft draft) async =>
      HrRequestDetail.fromJson(
        (await _api.post('me/requests', data: draft.toForm())).json,
      );

  Future<void> reply(int id, String body, {List<UploadFile> files = const []}) {
    final form = FormData()..fields.add(MapEntry('body', body));
    for (final file in files) {
      form.files.add(MapEntry('attachments[]', file.toMultipart()));
    }
    return _api.post('me/requests/$id/replies', data: form);
  }

  Future<void> attach(int id, List<UploadFile> files) {
    final form = FormData();
    for (final file in files) {
      form.files.add(MapEntry('attachments[]', file.toMultipart()));
    }
    return _api.post('me/requests/$id/attachments', data: form);
  }

  Future<void> cancel(int id) => _api.post('me/requests/$id/cancel');
}

final requestsRepositoryProvider = Provider<RequestsRepository>(
  (ref) => RequestsRepository(ref.watch(apiClientProvider)),
);

final requestCategoriesProvider = FutureProvider.autoDispose<RequestCategories>(
  (ref) => ref.watch(requestsRepositoryProvider).categories(),
);

final profileFieldsProvider = FutureProvider.autoDispose<List<ProfileField>>(
  (ref) => ref.watch(requestsRepositoryProvider).profileFields(),
);

final requestDetailProvider = FutureProvider.autoDispose
    .family<HrRequestDetail, int>(
      (ref, id) => ref.watch(requestsRepositoryProvider).show(id),
    );
