import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/files/upload_file.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

class LeaveType {
  const LeaveType({
    required this.id,
    required this.name,
    required this.color,
    required this.isPaid,
    required this.tracksBalance,
    required this.unit,
    required this.allowsHalfDay,
    required this.allowsHours,
    required this.requiresAttachment,
    required this.noticeDays,
    required this.allowsPastDates,
    this.description,
  });

  factory LeaveType.fromJson(Json json) => LeaveType(
    id: json.integer('id'),
    name: json.str('name'),
    description: json.strOrNull('description'),
    color: json.strOrNull('color'),
    isPaid: json.boolean('is_paid'),
    tracksBalance: json.boolean('tracks_balance'),
    unit: json.str('unit', 'day'),
    allowsHalfDay: json.boolean('allows_half_day'),
    allowsHours: json.boolean('allows_hours'),
    requiresAttachment: json.boolean('requires_attachment'),
    noticeDays: json.integer('notice_days'),
    allowsPastDates: json.boolean('allows_past_dates'),
  );

  final int id;
  final String name;
  final String? description;
  final String? color;
  final bool isPaid;
  final bool tracksBalance;
  final String unit;
  final bool allowsHalfDay;
  final bool allowsHours;
  final bool requiresAttachment;
  final int noticeDays;
  final bool allowsPastDates;
}

/// Balances are decimal strings ("23.00") — kept as strings.
class LeaveBalance {
  const LeaveBalance({
    required this.typeId,
    required this.typeName,
    required this.color,
    required this.tracksBalance,
    required this.entitlement,
    required this.carriedIn,
    required this.accrued,
    required this.used,
    required this.pending,
    required this.available,
    required this.balance,
  });

  factory LeaveBalance.fromJson(Json json) {
    final type = json.obj('leave_type') ?? const {};
    return LeaveBalance(
      typeId: type.integer('id'),
      typeName: type.str('name'),
      color: type.strOrNull('color'),
      tracksBalance: json.boolean('tracks_balance', true),
      entitlement: json.str('entitlement', '0'),
      carriedIn: json.str('carried_in', '0'),
      accrued: json.str('accrued', '0'),
      used: json.str('used', '0'),
      pending: json.str('pending', '0'),
      available: json.str('available', '0'),
      balance: json.str('balance', '0'),
    );
  }

  final int typeId;
  final String typeName;
  final String? color;
  final bool tracksBalance;
  final String entitlement;
  final String carriedIn;
  final String accrued;
  final String used;
  final String pending;
  final String available;
  final String balance;
}

class LeaveAttachment {
  const LeaveAttachment({required this.id, required this.name, this.url});

  factory LeaveAttachment.fromJson(Json json) => LeaveAttachment(
    id: json.integer('id'),
    name: json.str('name', json.str('file_name')),
    url: json.strOrNull('download_url') ?? json.strOrNull('url'),
  );

  final int id;
  final String name;
  final String? url;
}

class LeaveRequest {
  const LeaveRequest({
    required this.id,
    required this.typeName,
    required this.color,
    required this.startDate,
    required this.endDate,
    required this.dayPart,
    required this.days,
    required this.status,
    required this.statusLabel,
    required this.canCancel,
    required this.cancelNeedsApproval,
    required this.attachments,
    required this.dayBreakdown,
    required this.approval,
    this.reason,
    this.currentStep,
    this.rejectionReason,
    this.hours,
    this.createdAt,
  });

  factory LeaveRequest.fromJson(Json json) {
    final type = json.obj('leave_type') ?? const {};
    return LeaveRequest(
      id: json.integer('id'),
      typeName: type.str('name'),
      color: type.strOrNull('color'),
      startDate: json.str('start_date'),
      endDate: json.str('end_date'),
      dayPart: json.str('day_part', 'full'),
      hours: json.strOrNull('hours'),
      days: json.str('days', '0'),
      reason: json.strOrNull('reason'),
      status: json.str('status'),
      statusLabel: json.str('status_label'),
      currentStep: json.strOrNull('current_step'),
      rejectionReason: json.strOrNull('rejection_reason'),
      canCancel: json.boolean('can_cancel'),
      cancelNeedsApproval: json.boolean('cancel_needs_approval'),
      attachments: json
          .objList('attachments')
          .map(LeaveAttachment.fromJson)
          .toList(),
      dayBreakdown: json.objList('day_breakdown'),
      approval: json.objList('approval'),
      createdAt: json.date('created_at'),
    );
  }

  final int id;
  final String typeName;
  final String? color;
  final String startDate;
  final String endDate;
  final String dayPart;
  final String? hours;
  final String days;
  final String? reason;
  final String status;
  final String statusLabel;
  final String? currentStep;
  final String? rejectionReason;
  final bool canCancel;
  final bool cancelNeedsApproval;
  final List<LeaveAttachment> attachments;
  final List<Json> dayBreakdown;
  final List<Json> approval;
  final DateTime? createdAt;
}

/// Input of the new-leave form (preview and submit share it).
class LeaveDraft {
  const LeaveDraft({
    required this.typeId,
    required this.startDate,
    this.endDate,
    this.dayPart = 'full',
    this.hours,
    this.startTime,
    this.reason,
  });

  final int typeId;
  final String startDate;
  final String? endDate;

  /// full | first_half | second_half | hours
  final String dayPart;

  /// Decimal string, required when dayPart == hours.
  final String? hours;
  final String? startTime;
  final String? reason;

  Map<String, Object> toFields() => {
    'leave_type_id': typeId,
    'start_date': startDate,
    'end_date': ?endDate,
    'day_part': dayPart,
    if (dayPart == 'hours' && hours != null) 'hours': hours!,
    if (dayPart == 'hours' && startTime != null) 'start_time': startTime!,
    if (reason != null && reason!.trim().isNotEmpty) 'reason': reason!.trim(),
  };
}

/// `POST me/leave/requests/preview`.
class LeavePreview {
  const LeavePreview({
    required this.total,
    required this.days,
    required this.availableAfter,
    required this.needsAttachment,
  });

  factory LeavePreview.fromJson(Json json) => LeavePreview(
    total: json.str('total', '0'),
    days: json.objList('days'),
    availableAfter: {
      for (final entry in (json.obj('available_after') ?? const {}).entries)
        entry.key: entry.value.toString(),
    },
    needsAttachment: json.boolean('needs_attachment'),
  );

  final String total;
  final List<Json> days;

  /// leave year → balance after this request (string decimal).
  final Map<String, String> availableAfter;
  final bool needsAttachment;
}

class LeaveRepository {
  LeaveRepository(this._api);

  final ApiClient _api;

  Future<List<LeaveType>> types() async =>
      (await _api.get('me/leave/types')).list.map(LeaveType.fromJson).toList();

  Future<(List<LeaveBalance>, Json)> balances({int? year}) async {
    final response = await _api.get('me/leave/balances', query: {'year': year});
    return (response.list.map(LeaveBalance.fromJson).toList(), response.meta);
  }

  Future<Paged<LeaveRequest>> requests({int page = 1, String? status}) async {
    final response = await _api.get(
      'me/leave/requests',
      query: {'page': page, 'status': status},
    );
    return Paged(
      items: response.list.map(LeaveRequest.fromJson).toList(),
      pagination: response.pagination,
    );
  }

  Future<LeaveRequest> request(int id) async =>
      LeaveRequest.fromJson((await _api.get('me/leave/requests/$id')).json);

  Future<LeavePreview> preview(LeaveDraft draft) async => LeavePreview.fromJson(
    (await _api.post('me/leave/requests/preview', data: draft.toFields())).json,
  );

  /// Multipart when there are attachments, JSON otherwise.
  Future<LeaveRequest> submit(
    LeaveDraft draft, {
    List<UploadFile> attachments = const [],
  }) async {
    final Object body;
    if (attachments.isEmpty) {
      body = draft.toFields();
    } else {
      final form = FormData();
      draft.toFields().forEach(
        (key, value) => form.fields.add(MapEntry(key, value.toString())),
      );
      for (final file in attachments) {
        form.files.add(MapEntry('attachments[]', file.toMultipart()));
      }
      body = form;
    }
    final response = await _api.post('me/leave/requests', data: body);
    return LeaveRequest.fromJson(response.json);
  }

  Future<void> cancel(int id, {String? reason}) => _api.post(
    'me/leave/requests/$id/cancel',
    data: {if (reason != null && reason.isNotEmpty) 'reason': reason},
  );

  Future<void> addAttachments(int id, List<UploadFile> files) {
    final form = FormData();
    for (final file in files) {
      form.files.add(MapEntry('attachments[]', file.toMultipart()));
    }
    return _api.post('me/leave/requests/$id/attachments', data: form);
  }

  Future<Uint8List> download(String url) => _api.download(url);
}

final leaveRepositoryProvider = Provider<LeaveRepository>(
  (ref) => LeaveRepository(ref.watch(apiClientProvider)),
);

final leaveTypesProvider = FutureProvider.autoDispose<List<LeaveType>>(
  (ref) => ref.watch(leaveRepositoryProvider).types(),
);

final leaveBalancesProvider = FutureProvider.autoDispose
    .family<(List<LeaveBalance>, Json), int?>(
      (ref, year) => ref.watch(leaveRepositoryProvider).balances(year: year),
    );

final leaveRequestProvider = FutureProvider.autoDispose
    .family<LeaveRequest, int>(
      (ref, id) => ref.watch(leaveRepositoryProvider).request(id),
    );
