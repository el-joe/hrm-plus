import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

class AnnouncementAttachment {
  const AnnouncementAttachment({
    required this.id,
    required this.name,
    required this.size,
    required this.downloadUrl,
  });

  factory AnnouncementAttachment.fromJson(Json json) => AnnouncementAttachment(
    id: json.integer('id'),
    name: json.str('name'),
    size: json.intOrNull('size'),
    downloadUrl: json.strOrNull('download_url'),
  );

  final int id;
  final String name;
  final int? size;
  final String? downloadUrl;
}

class Announcement {
  const Announcement({
    required this.id,
    required this.title,
    required this.summary,
    required this.typeLabel,
    required this.isPinned,
    required this.requiresAcknowledgement,
    required this.isRead,
    required this.acknowledged,
    required this.canAcknowledge,
    required this.attachmentsCount,
    required this.attachments,
    this.publishedAt,
    this.acknowledgedAt,
    this.bodyText,
  });

  factory Announcement.fromJson(Json json) => Announcement(
    id: json.integer('id'),
    title: json.str('title'),
    summary: json.strOrNull('summary'),
    typeLabel: json.str('type_label'),
    isPinned: json.boolean('is_pinned'),
    requiresAcknowledgement: json.boolean('requires_acknowledgement'),
    publishedAt: json.date('published_at'),
    isRead: json.boolean('is_read'),
    acknowledged: json.boolean('acknowledged'),
    acknowledgedAt: json.date('acknowledged_at'),
    canAcknowledge: json.boolean('can_acknowledge'),
    attachmentsCount: json.integer('attachments_count'),
    bodyText: json.strOrNull('body_text'),
    attachments: json
        .objList('attachments')
        .map(AnnouncementAttachment.fromJson)
        .toList(),
  );

  final int id;
  final String title;
  final String? summary;
  final String typeLabel;
  final bool isPinned;
  final bool requiresAcknowledgement;
  final DateTime? publishedAt;
  final bool isRead;
  final bool acknowledged;
  final DateTime? acknowledgedAt;
  final bool canAcknowledge;
  final int attachmentsCount;

  /// Plain-text body (the HTML body is not rendered in the app).
  final String? bodyText;
  final List<AnnouncementAttachment> attachments;
}

class AnnouncementsRepository {
  AnnouncementsRepository(this._api);

  final ApiClient _api;

  Future<Paged<Announcement>> list({int page = 1}) async {
    final response = await _api.get('me/announcements', query: {'page': page});
    return Paged(
      items: response.list.map(Announcement.fromJson).toList(),
      pagination: response.pagination,
      meta: response.meta,
    );
  }

  Future<Announcement> show(int id) async =>
      Announcement.fromJson((await _api.get('me/announcements/$id')).json);

  Future<void> markRead(int id) => _api.post('me/announcements/$id/read');

  Future<void> acknowledge(int id) =>
      _api.post('me/announcements/$id/acknowledge');
}

final announcementsRepositoryProvider = Provider<AnnouncementsRepository>(
  (ref) => AnnouncementsRepository(ref.watch(apiClientProvider)),
);

final announcementProvider = FutureProvider.autoDispose
    .family<Announcement, int>((ref, id) async {
      final repo = ref.watch(announcementsRepositoryProvider);
      final announcement = await repo.show(id);
      if (!announcement.isRead) {
        // Opening it counts as reading it.
        await repo.markRead(id).catchError((Object _) {});
      }
      return announcement;
    });
