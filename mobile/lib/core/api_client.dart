import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'session_store.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors = const {}});
  final String message;
  final int? statusCode;
  final Map<String, dynamic> errors;
  @override
  String toString() => message;
}

class ApiClient {
  ApiClient(this.session, {http.Client? client})
    : _client = client ?? http.Client();

  final SessionData session;
  final http.Client _client;
  static Future<void> Function()? onSessionInvalid;

  Map<String, String> get _headers => {
    HttpHeaders.acceptHeader: 'application/json',
    HttpHeaders.contentTypeHeader: 'application/json',
    'X-Hotel-Slug': session.hotelSlug,
    'X-Device-ID': session.deviceId,
    if (session.isAuthenticated)
      HttpHeaders.authorizationHeader: 'Bearer ${session.token}',
  };

  Future<dynamic> get(String path) => _send('GET', path);
  Future<dynamic> post(String path, [Map<String, dynamic>? body]) =>
      _send('POST', path, body);
  Future<dynamic> patch(String path, [Map<String, dynamic>? body]) =>
      _send('PATCH', path, body);
  Future<dynamic> put(String path, [Map<String, dynamic>? body]) =>
      _send('PUT', path, body);
  Future<dynamic> delete(String path, [Map<String, dynamic>? body]) =>
      _send('DELETE', path, body);

  Future<dynamic> multipart(
    String path,
    Map<String, String> fields,
    Map<String, String?> files,
  ) async {
    await _ensureFreshSession(path);
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${session.baseUrl}/api/v1/$path'),
    )..headers.addAll(Map.of(_headers)..remove(HttpHeaders.contentTypeHeader));
    request.fields.addAll(fields);
    for (final entry in files.entries) {
      if (entry.value != null) {
        request.files.add(
          await http.MultipartFile.fromPath(entry.key, entry.value!),
        );
      }
    }
    try {
      final streamed = await request.send().timeout(
        const Duration(seconds: 30),
      );
      final response = await http.Response.fromStream(streamed);
      await _handleUnauthorized(response);
      return _decode(response);
    } on SocketException {
      throw ApiException(
        'Could not reach the hotel server. Check the server address and connection.',
      );
    }
  }

  Future<dynamic> _send(
    String method,
    String path, [
    Map<String, dynamic>? body,
  ]) async {
    await _ensureFreshSession(path);
    final uri = Uri.parse('${session.baseUrl}/api/v1/$path');
    late http.Response response;
    try {
      response = await _client
          .send(
            http.Request(method, uri)
              ..headers.addAll(_headers)
              ..body = body == null ? '' : jsonEncode(body),
          )
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 20));
    } on SocketException {
      throw ApiException(
        'Could not reach the hotel server. Check the server address and connection.',
      );
    }
    await _handleUnauthorized(response);
    return _decode(response);
  }

  Future<void> _ensureFreshSession(String path) async {
    if (!session.isAuthenticated ||
        path == 'auth/refresh' ||
        !session.needsRefresh) {
      return;
    }
    if (session.isExpired) {
      await _invalidateSession();
      throw ApiException(
        'Your session expired. Sign in again to continue.',
        statusCode: 401,
      );
    }

    final existing = session.refreshInFlight;
    if (existing != null) return existing;
    final refresh = _refreshSession();
    session.refreshInFlight = refresh;
    try {
      await refresh;
    } finally {
      session.refreshInFlight = null;
    }
  }

  Future<void> _refreshSession() async {
    final request = http.Request(
      'POST',
      Uri.parse('${session.baseUrl}/api/v1/auth/refresh'),
    )..headers.addAll(_headers);
    late http.Response response;
    try {
      response = await _client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 20));
    } on SocketException {
      throw ApiException(
        'Could not renew your session. Check your connection and try again.',
      );
    }
    await _handleUnauthorized(response);
    final payload = _decode(response);
    final data = Map<String, dynamic>.from(payload['data']);
    final token = data['token']?.toString();
    if (token == null || token.isEmpty) {
      throw ApiException('The hotel server returned an invalid session.');
    }
    final expiresAt = DateTime.tryParse(
      data['expires_at']?.toString() ?? '',
    )?.toUtc();
    session.token = token;
    session.expiresAt = expiresAt;
    await SessionStore().saveToken(token, expiresAt);
  }

  Future<void> _handleUnauthorized(http.Response response) async {
    if (response.statusCode == 401 && session.isAuthenticated) {
      await _invalidateSession();
    }
  }

  Future<void> _invalidateSession() async {
    session.token = null;
    session.expiresAt = null;
    await SessionStore().clearToken();
    await onSessionInvalid?.call();
  }

  dynamic _decode(http.Response response) {
    final payload = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final map = payload is Map<String, dynamic>
          ? payload
          : <String, dynamic>{};
      throw ApiException(
        map['message']?.toString() ?? 'The request could not be completed.',
        statusCode: response.statusCode,
        errors: Map<String, dynamic>.from(map['errors'] ?? {}),
      );
    }
    return payload;
  }
}
