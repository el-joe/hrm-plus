import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

/// `NotificationResource`.
class AppNotification {
  const AppNotification({
    required this.id,
    required this.event,
    required this.module,
    required this.title,
    required this.message,
    required this.data,
    required this.url,
    required this.isRead,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Json json) => AppNotification(
    id: json.str('id'),
    event: json.str('event'),
    module: json.str('module'),
    title: json.str('title'),
    message: json.str('message'),
    data: json.obj('data') ?? const {},
    url: json.strOrNull('url'),
    isRead: json.boolean('is_read'),
    createdAt: json.date('created_at'),
  );

  final String id;
  final String event;
  final String module;
  final String title;
  final String message;
  final Json data;
  final String? url;
  final bool isRead;
  final DateTime? createdAt;

  AppNotification markedRead() => AppNotification(
    id: id,
    event: event,
    module: module,
    title: title,
    message: message,
    data: data,
    url: url,
    isRead: true,
    createdAt: createdAt,
  );

  /// Payload for deep linking (same keys as a push).
  Map<String, Object?> get payload => {
    ...data,
    'event': event,
    'module': module,
    'url': ?url,
  };
}

class PreferenceEvent {
  const PreferenceEvent({
    required this.event,
    required this.label,
    required this.mandatory,
    required this.channels,
  });

  factory PreferenceEvent.fromJson(Json json) {
    final channels = json.obj('channels') ?? const {};
    return PreferenceEvent(
      event: json.str('event'),
      label: json.str('label'),
      mandatory: json.boolean('mandatory'),
      channels: {
        for (final entry in channels.entries) entry.key: entry.value == true,
      },
    );
  }

  final String event;
  final String label;
  final bool mandatory;
  final Map<String, bool> channels;
}

class PreferenceModule {
  const PreferenceModule({required this.label, required this.events});

  factory PreferenceModule.fromJson(Json json) => PreferenceModule(
    label: json.str('label'),
    events: json.objList('events').map(PreferenceEvent.fromJson).toList(),
  );

  final String label;
  final List<PreferenceEvent> events;
}

class PreferenceChannel {
  const PreferenceChannel({
    required this.key,
    required this.label,
    required this.available,
  });

  final String key;
  final String label;
  final bool available;
}

class NotificationPreferences {
  const NotificationPreferences({
    required this.modules,
    required this.channels,
  });

  final List<PreferenceModule> modules;
  final List<PreferenceChannel> channels;
}

class NotificationsRepository {
  NotificationsRepository(this._api);

  final ApiClient _api;

  Future<Paged<AppNotification>> list({
    int page = 1,
    bool unread = false,
  }) async {
    final response = await _api.get(
      'me/notifications',
      query: {'page': page, if (unread) 'unread': 1},
    );
    return Paged(
      items: response.list.map(AppNotification.fromJson).toList(),
      pagination: response.pagination,
      meta: response.meta,
    );
  }

  Future<int> unreadCount() async =>
      (await _api.get('me/notifications/unread-count')).json.integer('count');

  Future<void> markRead(String id) => _api.post('me/notifications/$id/read');

  Future<void> markAllRead() => _api.post('me/notifications/read-all');

  Future<NotificationPreferences> preferences() async {
    final response = await _api.get('me/notification-preferences');
    return _prefs(response);
  }

  /// PUT {preferences: {event: {channel: bool}}}.
  Future<NotificationPreferences> savePreferences(
    Map<String, Map<String, bool>> choices,
  ) async {
    final response = await _api.put(
      'me/notification-preferences',
      data: {'preferences': choices},
    );
    return _prefs(response);
  }

  NotificationPreferences _prefs(ApiResponse response) =>
      NotificationPreferences(
        modules: response.list.map(PreferenceModule.fromJson).toList(),
        channels: [
          for (final c in response.meta.objList('channels'))
            PreferenceChannel(
              key: c.str('key'),
              label: c.str('label'),
              available: c.boolean('available', true),
            ),
        ],
      );

  /// POST me/devices — registers (or refreshes) this device's FCM token.
  Future<void> registerDevice({
    required String token,
    required String platform,
    required String deviceName,
    required String appVersion,
    required String locale,
  }) => _api.post(
    'me/devices',
    data: {
      'token': token,
      'platform': platform,
      'device_name': deviceName,
      'app_version': appVersion,
      'locale': locale,
    },
  );

  /// DELETE me/devices {token}.
  Future<void> unregisterDevice(String token) =>
      _api.delete('me/devices', data: {'token': token});
}

final notificationsRepositoryProvider = Provider<NotificationsRepository>(
  (ref) => NotificationsRepository(ref.watch(apiClientProvider)),
);

final unreadCountProvider = FutureProvider.autoDispose<int>(
  (ref) => ref.watch(notificationsRepositoryProvider).unreadCount(),
);
