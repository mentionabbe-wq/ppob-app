import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// Handler pesan latar belakang harus berupa fungsi top-level.
@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

/// Push notification: status transaksi, deposit, promo, info sistem.
class PushNotificationService {
  PushNotificationService(this._onTokenRefresh);

  final Future<void> Function(String token) _onTokenRefresh;

  final _local = FlutterLocalNotificationsPlugin();

  static const _channel = AndroidNotificationChannel(
    'ppob_default',
    'Notifikasi Transaksi',
    description: 'Status transaksi, deposit, dan informasi promo.',
    importance: Importance.high,
  );

  Future<String?> initialize({
    required void Function(Map<String, dynamic> data) onTap,
  }) async {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);

    final messaging = FirebaseMessaging.instance;

    final settings = await messaging.requestPermission(alert: true, badge: true, sound: true);

    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      debugPrint('Izin notifikasi ditolak pengguna.');
      return null;
    }

    await _local.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(),
      ),
      onDidReceiveNotificationResponse: (response) {
        if (response.payload != null) onTap({'route': response.payload!});
      },
    );

    await _local
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);

    // Pesan saat aplikasi terbuka tidak ditampilkan otomatis oleh FCM.
    FirebaseMessaging.onMessage.listen(_showLocal);

    FirebaseMessaging.onMessageOpenedApp.listen((message) => onTap(message.data));

    final initial = await messaging.getInitialMessage();
    if (initial != null) onTap(initial.data);

    messaging.onTokenRefresh.listen(_onTokenRefresh);

    return messaging.getToken();
  }

  Future<void> _showLocal(RemoteMessage message) async {
    final notification = message.notification;

    if (notification == null) return;

    await _local.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id,
          _channel.name,
          channelDescription: _channel.description,
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: const DarwinNotificationDetails(),
      ),
      payload: message.data['transaction_id']?.toString(),
    );
  }
}
