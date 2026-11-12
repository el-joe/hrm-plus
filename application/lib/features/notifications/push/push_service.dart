import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../../core/config/app_config.dart';

/// A push message as the app needs it.
class PushMessage {
  const PushMessage({this.title, this.body, this.data = const {}});

  final String? title;
  final String? body;

  /// FCM data (strings): event, kind, notification_id, url, record ids.
  final Map<String, Object?> data;
}

/// Push delivery behind an interface. The app works without Firebase
/// configuration: [NoopPushService] is used and push is reported as disabled.
abstract class PushService {
  /// True when a provider is configured and initialised.
  bool get available;

  Future<void> initialize();

  /// The device token to register with `POST me/devices`, or null.
  Future<String?> token();

  Stream<String> get onTokenRefresh;

  /// Taps on notifications (system tray or local foreground notification).
  Stream<PushMessage> get onOpened;

  /// Messages received while the app is in the foreground.
  Stream<PushMessage> get onForeground;

  /// The notification that launched the app, if any.
  Future<PushMessage?> initialMessage();
}

class NoopPushService implements PushService {
  const NoopPushService();

  @override
  bool get available => false;
  @override
  Future<void> initialize() async {}
  @override
  Future<String?> token() async => null;
  @override
  Stream<String> get onTokenRefresh => const Stream.empty();
  @override
  Stream<PushMessage> get onOpened => const Stream.empty();
  @override
  Stream<PushMessage> get onForeground => const Stream.empty();
  @override
  Future<PushMessage?> initialMessage() async => null;
}

@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage message) async {
  // The system shows `notification` messages itself; nothing to do here.
}

/// Firebase Cloud Messaging + flutter_local_notifications (foreground display).
class FirebasePushService implements PushService {
  FirebasePushService._();

  /// Initialises Firebase. Returns a [NoopPushService] (and logs) when the
  /// platform files (google-services.json / GoogleService-Info.plist) are
  /// missing, so the app never crashes for lack of push configuration.
  static Future<PushService> tryCreate() async {
    if (kIsWeb) {
      debugPrint('[push] disabled on web builds');
      return const NoopPushService();
    }
    try {
      await Firebase.initializeApp();
    } catch (error) {
      debugPrint('[push] Firebase is not configured, push disabled: $error');
      return const NoopPushService();
    }
    final service = FirebasePushService._();
    try {
      await service.initialize();
    } catch (error) {
      debugPrint('[push] initialisation failed, push disabled: $error');
      return const NoopPushService();
    }
    return service;
  }

  final _local = FlutterLocalNotificationsPlugin();
  final _opened = StreamController<PushMessage>.broadcast();
  final _foreground = StreamController<PushMessage>.broadcast();

  static const _channel = AndroidNotificationChannel(
    AppConfig.androidChannelId,
    'HRM Plus',
    description: 'HRM Plus notifications',
    importance: Importance.high,
  );

  @override
  bool get available => true;

  PushMessage _fromRemote(RemoteMessage message) => PushMessage(
    title: message.notification?.title,
    body: message.notification?.body,
    data: Map<String, Object?>.from(message.data),
  );

  @override
  Future<void> initialize() async {
    FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);
    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission();
    await messaging.setForegroundNotificationPresentationOptions(
      alert: false,
      badge: true,
      sound: true,
    );

    await _local.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
        ),
      ),
      onDidReceiveNotificationResponse: (response) {
        final payload = response.payload;
        if (payload == null) return;
        _opened.add(PushMessage(data: Uri.splitQueryString(payload)));
      },
    );
    final android = _local
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    await android?.createNotificationChannel(_channel);
    await android?.requestNotificationsPermission();

    FirebaseMessaging.onMessage.listen((message) {
      final push = _fromRemote(message);
      _foreground.add(push);
      unawaited(_showLocal(push));
    });
    FirebaseMessaging.onMessageOpenedApp.listen(
      (message) => _opened.add(_fromRemote(message)),
    );
  }

  Future<void> _showLocal(PushMessage message) async {
    if (message.title == null && message.body == null) return;
    final payload = Uri(
      queryParameters: {
        for (final entry in message.data.entries)
          if (entry.value != null) entry.key: entry.value.toString(),
      },
    ).query;
    await _local.show(
      id: message.hashCode & 0x7fffffff,
      title: message.title,
      body: message.body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id,
          _channel.name,
          channelDescription: _channel.description,
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: const DarwinNotificationDetails(),
      ),
      payload: payload,
    );
  }

  // The backend sends with FCM HTTP v1 to classic registration tokens, so the
  // (deprecated in firebase_messaging 17) token API is used on purpose rather
  // than the new FID-based register()/onRegistered direct-send flow.
  @override
  // ignore: deprecated_member_use
  Future<String?> token() => FirebaseMessaging.instance.getToken();

  @override
  Stream<String> get onTokenRefresh =>
      // ignore: deprecated_member_use
      FirebaseMessaging.instance.onTokenRefresh;

  @override
  Stream<PushMessage> get onOpened => _opened.stream;

  @override
  Stream<PushMessage> get onForeground => _foreground.stream;

  @override
  Future<PushMessage?> initialMessage() async {
    final message = await FirebaseMessaging.instance.getInitialMessage();
    if (message != null) return _fromRemote(message);
    final launch = await _local.getNotificationAppLaunchDetails();
    final payload = launch?.notificationResponse?.payload;
    if (launch?.didNotificationLaunchApp == true && payload != null) {
      return PushMessage(data: Uri.splitQueryString(payload));
    }
    return null;
  }
}
