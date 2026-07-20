import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import 'api_client.dart';
import 'session_store.dart';

class PushService {
  static FirebaseMessaging? _messaging;

  static Future<bool> configure(
    SessionData session,
    Map<String, dynamic> property, {
    void Function(Map<String, dynamic> data)? onNotificationOpened,
  }) async {
    final raw = property['firebase'];
    if (raw is! Map || !session.isAuthenticated) return false;
    final config = Map<String, dynamic>.from(raw);
    final appId = Platform.isIOS
        ? config['ios_app_id']
        : config['android_app_id'];
    if (appId == null) return false;
    FirebaseApp app;
    try {
      app = Firebase.app();
      if (app.options.projectId != config['project_id'].toString()) {
        await app.delete();
        throw StateError('Switching Firebase hotel project.');
      }
    } catch (_) {
      app = await Firebase.initializeApp(
        options: FirebaseOptions(
          apiKey: config['api_key'].toString(),
          appId: appId.toString(),
          messagingSenderId: config['messaging_sender_id'].toString(),
          projectId: config['project_id'].toString(),
          iosBundleId: Platform.isIOS ? 'me.romarioburke.hotelcheckin' : null,
        ),
      );
    }
    _messaging = FirebaseMessaging.instance;
    final permission = await _messaging!.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    if (permission.authorizationStatus == AuthorizationStatus.denied) {
      return false;
    }
    final token = await _messaging!.getToken();
    if (token != null) await _register(session, token);
    _messaging!.onTokenRefresh.listen((next) => _register(session, next));
    FirebaseMessaging.onMessageOpenedApp.listen(
      (message) => onNotificationOpened?.call(message.data),
    );
    final initialMessage = await _messaging!.getInitialMessage();
    if (initialMessage != null) {
      onNotificationOpened?.call(initialMessage.data);
    }
    return token != null;
  }

  static Future<void> disable(SessionData session) async {
    if (!session.isAuthenticated) return;
    try {
      await ApiClient(
        session,
      ).put('devices/current/push-token', {'push_token': null});
      await _messaging?.deleteToken();
    } catch (_) {
      // Logout still succeeds if Firebase or the network is unavailable.
    }
  }

  static Future<void> _register(SessionData session, String token) => ApiClient(
    session,
  ).put('devices/current/push-token', {'push_token': token});
}
