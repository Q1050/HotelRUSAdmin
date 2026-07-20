import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hotel_guest/core/api_client.dart';
import 'package:hotel_guest/core/session_store.dart';
import 'package:hotel_guest/main.dart';

void main() {
  test('hotel theme colors are parsed safely', () {
    expect(colorFromHex('#123456', Colors.black), const Color(0xff123456));
    expect(colorFromHex('invalid', Colors.black), Colors.black);
    expect(colorFromHex(null, Colors.black), Colors.black);
  });

  test('room markers are extracted from raw values and URLs', () {
    const marker = '1206f98a-f3e4-4df8-a449-665c2ae9cfa8';
    expect(extractRoomMarker(marker), marker);
    expect(
      extractRoomMarker('https://hotel.test/access?marker=$marker'),
      marker,
    );
    expect(extractRoomMarker('not-a-room-marker'), isNull);
  });

  test('API errors retain status and validation details', () {
    final error = ApiException(
      'Invalid details',
      statusCode: 422,
      errors: {
        'email': ['Required'],
      },
    );
    expect(error.toString(), 'Invalid details');
    expect(error.statusCode, 422);
    expect(error.errors['email'], ['Required']);
  });

  test('sessions expose refresh and expiration windows', () {
    final fresh = SessionData(
      baseUrl: 'https://hotel.test',
      hotelSlug: 'hotel',
      deviceId: 'device',
      token: 'token',
      expiresAt: DateTime.now().toUtc().add(const Duration(minutes: 30)),
    );
    final expiring = SessionData(
      baseUrl: 'https://hotel.test',
      hotelSlug: 'hotel',
      deviceId: 'device',
      token: 'token',
      expiresAt: DateTime.now().toUtc().add(const Duration(minutes: 2)),
    );
    final expired = SessionData(
      baseUrl: 'https://hotel.test',
      hotelSlug: 'hotel',
      deviceId: 'device',
      token: 'token',
      expiresAt: DateTime.now().toUtc().subtract(const Duration(seconds: 1)),
    );
    expect(fresh.needsRefresh, isFalse);
    expect(expiring.needsRefresh, isTrue);
    expect(expiring.isExpired, isFalse);
    expect(expired.isExpired, isTrue);
  });

  test('notification payloads resolve to useful destinations', () {
    final request = notificationDestination({
      'category': 'service',
      'type': 'request_message',
      'request_id': '42',
    });
    expect(request.tabIndex, 2);
    expect(request.requestId, 42);

    final preArrival = notificationDestination({
      'category': 'booking',
      'type': 'pre_arrival_reviewed',
      'reservation_id': '9',
    });
    expect(preArrival.tabIndex, 1);
    expect(preArrival.reservationId, 9);
    expect(preArrival.openPreArrival, isTrue);

    final access = notificationDestination({'category': 'access'});
    expect(access.tabIndex, 0);
    expect(access.openRoomAccess, isTrue);
    expect(notificationDestination({'category': 'unknown'}).tabIndex, 3);
  });
}
