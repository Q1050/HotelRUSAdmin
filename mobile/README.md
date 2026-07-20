# Hotel Guest Mobile

Flutter guest application for the Laravel Hotel Check-In platform. The app is a separate Android/iOS client and communicates only through `/api/v1`.

## Project structure

```text
lib/
├── main.dart                         # Bootstrap, theme, session, navigation
├── core/                             # API, storage, push, sensitive access
├── features/
│   ├── authentication/
│   ├── stay/
│   ├── reservations/
│   ├── requests/
│   ├── notifications/
│   └── account/
└── shared/widgets/                   # Reusable guest UI components
```

Feature files are `part` files of the main application library. This keeps existing cross-feature navigation type-safe while separating ownership and making later conversion to fully independent packages straightforward.

## Included foundation

- Hotel resolution through `X-Hotel-Slug`
- Per-installation UUID sent as `X-Device-ID`
- Guest registration and login
- Email-code password recovery from the mobile sign-in screen
- Tokens, hotel selection, and device identity stored in platform-secure storage
- Automatic access-token renewal with expired and revoked session handling
- Hotel branding loaded from `/api/v1/property`
- Branded Material 3 guest experience with refined stay and navigation surfaces
- Refined booking, request, inbox, and profile screens with consistent visual hierarchy
- Current stay and room summary
- Reservation claiming with email verification codes
- Reservation list and pre-arrival ID document submission
- Per-hotel Firebase push registration and token rotation
- Notification inbox, unread tracking, and category preferences
- Push and inbox deep links to requests, bookings, pre-arrival, stays, and room access
- Guest service-request list and request creation
- Request details, timeline, two-way messaging, and private image attachments
- Request cancellation, completion confirmation, and reopening with feedback
- Assigned-room access through QR or NFC marker verification
- Mobile credential retrieval and tracked remote door unlock commands
- Registered-device review and remote revocation from the guest account
- Password changes and one-action sign-out for every other guest device
- Device authentication protecting room access and account-security actions
- Personal-data export and reviewed account-deletion requests
- Guest account summary and logout

## Local development

Start Laravel from the repository root:

```bash
php artisan serve
```

Run the app from this directory:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Use `http://10.0.2.2:8000` for the Android emulator and `http://127.0.0.1:8000` for the iOS Simulator. A physical device must use an HTTPS address or a reachable LAN address during development. Production builds should always use HTTPS.

The first screen also allows the server address to be changed without rebuilding the app.

## Room access setup

Room access requires the `smart_locks` hotel module, an active checked-in stay, a verified guest identity, an eligible payment state, and a lock paired to the assigned room. Print or encode that room's access-marker UUID as either a QR code or an NDEF NFC text/URL record. The marker is validated by the server and is not returned in the stay API.

Android declares camera and NFC support while keeping both hardware features optional. iOS includes camera/NFC usage descriptions and the NDEF/TAG reader entitlement. NFC cannot be tested in a simulator; use the manual UUID fallback or QR scanner there, and use a physical device for NFC verification.

Sensitive actions use Face ID, Touch ID, Android biometrics, or the device screen-lock credential through `local_auth`. Android uses a `FlutterFragmentActivity` and biometric permission; iOS includes the required Face ID purpose string. The authorization window closes whenever the app moves into the background.

## Per-hotel Firebase setup

In the hotel web dashboard, open **Settings → Guest mobile notifications** and save both sets of Firebase values:

- Server delivery: project ID, service-account email, private key, and OAuth token URL
- Mobile app: Web API key, messaging sender ID, Android app ID, and iOS app ID

The Web API key and app identifiers are public client configuration. The service-account email and encrypted private key are never returned to the mobile app. Register `me.romarioburke.hotelcheckin` as both the Android package and iOS bundle identifier in each hotel's Firebase project.

## Verification

```bash
flutter analyze
flutter test
```
