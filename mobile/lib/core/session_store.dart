import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

class SessionData {
  SessionData({
    required this.baseUrl,
    required this.hotelSlug,
    required this.deviceId,
    this.token,
    this.expiresAt,
  });

  final String baseUrl;
  final String hotelSlug;
  final String deviceId;
  String? token;
  DateTime? expiresAt;
  Future<void>? refreshInFlight;

  bool get isAuthenticated => token != null && token!.isNotEmpty;
  bool get isExpired =>
      expiresAt != null && !expiresAt!.isAfter(DateTime.now().toUtc());
  bool get needsRefresh =>
      expiresAt != null &&
      !expiresAt!.isAfter(
        DateTime.now().toUtc().add(const Duration(minutes: 5)),
      );
}

class SessionStore {
  static const _storage = FlutterSecureStorage();

  Future<SessionData> load() async {
    var deviceId = await _storage.read(key: 'device_id');
    if (deviceId == null) {
      deviceId = const Uuid().v4();
      await _storage.write(key: 'device_id', value: deviceId);
    }
    return SessionData(
      baseUrl:
          await _storage.read(key: 'base_url') ??
          const String.fromEnvironment(
            'API_BASE_URL',
            defaultValue: 'http://10.0.2.2:8000',
          ),
      hotelSlug: await _storage.read(key: 'hotel_slug') ?? '',
      deviceId: deviceId,
      token: await _storage.read(key: 'access_token'),
      expiresAt: DateTime.tryParse(
        await _storage.read(key: 'access_token_expires_at') ?? '',
      )?.toUtc(),
    );
  }

  Future<void> saveHotel(String baseUrl, String hotelSlug) async {
    await _storage.write(
      key: 'base_url',
      value: baseUrl.replaceAll(RegExp(r'/$'), ''),
    );
    await _storage.write(
      key: 'hotel_slug',
      value: hotelSlug.trim().toLowerCase(),
    );
  }

  Future<void> saveToken(String token, DateTime? expiresAt) async {
    await _storage.write(key: 'access_token', value: token);
    if (expiresAt == null) {
      await _storage.delete(key: 'access_token_expires_at');
    } else {
      await _storage.write(
        key: 'access_token_expires_at',
        value: expiresAt.toUtc().toIso8601String(),
      );
    }
  }

  Future<void> clearToken() async {
    await _storage.delete(key: 'access_token');
    await _storage.delete(key: 'access_token_expires_at');
  }

  Future<void> clearHotel() async {
    await _storage.delete(key: 'hotel_slug');
    await _storage.delete(key: 'access_token');
    await _storage.delete(key: 'access_token_expires_at');
  }
}
