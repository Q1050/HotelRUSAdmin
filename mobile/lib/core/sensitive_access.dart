import 'package:flutter/material.dart';
import 'package:local_auth/local_auth.dart';

class SensitiveAccess {
  static final LocalAuthentication _auth = LocalAuthentication();
  static DateTime? _authorizedAt;

  static void lock() => _authorizedAt = null;

  static Future<bool> authorize(BuildContext context, String reason) async {
    final authorizedAt = _authorizedAt;
    if (authorizedAt != null &&
        DateTime.now().difference(authorizedAt) < const Duration(minutes: 2)) {
      return true;
    }
    try {
      if (!await _auth.isDeviceSupported()) {
        if (context.mounted) {
          _message(
            context,
            'Set up a screen lock or biometrics on this device to continue.',
          );
        }
        return false;
      }
      final authenticated = await _auth.authenticate(
        localizedReason: reason,
        persistAcrossBackgrounding: true,
      );
      if (authenticated) _authorizedAt = DateTime.now();
      return authenticated;
    } on LocalAuthException catch (exception) {
      if (context.mounted) {
        final locked =
            exception.code == LocalAuthExceptionCode.temporaryLockout ||
            exception.code == LocalAuthExceptionCode.biometricLockout;
        _message(
          context,
          locked
              ? 'Device authentication is temporarily locked. Try again later.'
              : 'Device authentication is unavailable or was cancelled.',
        );
      }
      return false;
    }
  }

  static void _message(BuildContext context, String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}
