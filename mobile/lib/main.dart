import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:nfc_manager/nfc_manager.dart';
import 'package:nfc_manager_ndef/nfc_manager_ndef.dart';

import 'core/api_client.dart';
import 'core/push_service.dart';
import 'core/session_store.dart';
import 'core/sensitive_access.dart';

part 'features/authentication/authentication.dart';
part 'features/stay/stay.dart';
part 'features/reservations/reservations.dart';
part 'features/folios/folio.dart';
part 'features/requests/requests.dart';
part 'features/notifications/notifications.dart';
part 'features/account/account.dart';
part 'shared/widgets/widgets.dart';

void main() => runApp(const GuestApp());

String? extractRoomMarker(String value) {
  return RegExp(
    r'[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}',
  ).firstMatch(value)?.group(0)?.toLowerCase();
}

class NotificationDestination {
  const NotificationDestination({
    required this.tabIndex,
    this.requestId,
    this.reservationId,
    this.openPreArrival = false,
    this.openRoomAccess = false,
  });

  final int tabIndex;
  final int? requestId;
  final int? reservationId;
  final bool openPreArrival;
  final bool openRoomAccess;
}

NotificationDestination notificationDestination(Map<String, dynamic> data) {
  final type = data['type']?.toString() ?? '';
  final category = data['category']?.toString() ?? '';
  final requestId = int.tryParse(data['request_id']?.toString() ?? '');
  final reservationId = int.tryParse(data['reservation_id']?.toString() ?? '');
  if (requestId != null || type.startsWith('service_request')) {
    return NotificationDestination(tabIndex: 2, requestId: requestId);
  }
  if (type == 'pre_arrival_reviewed') {
    return NotificationDestination(
      tabIndex: 1,
      reservationId: reservationId,
      openPreArrival: reservationId != null,
    );
  }
  if (category == 'access' || type.contains('access')) {
    return const NotificationDestination(tabIndex: 0, openRoomAccess: true);
  }
  if (category == 'checkout' || type == 'checkout_completed') {
    return const NotificationDestination(tabIndex: 0);
  }
  if (type == 'checkin_completed') {
    return const NotificationDestination(tabIndex: 0);
  }
  if (category == 'booking' || reservationId != null) {
    return NotificationDestination(tabIndex: 1, reservationId: reservationId);
  }
  return const NotificationDestination(tabIndex: 3);
}

class GuestApp extends StatefulWidget {
  const GuestApp({super.key});
  @override
  State<GuestApp> createState() => _GuestAppState();
}

class _GuestAppState extends State<GuestApp> with WidgetsBindingObserver {
  final store = SessionStore();
  SessionData? session;
  Map<String, dynamic>? property;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    ApiClient.onSessionInvalid = sessionInvalid;
    _restore();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.detached) {
      SensitiveAccess.lock();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> sessionInvalid() async {
    if (!mounted) return;
    final next = await store.load();
    if (mounted) setState(() => session = next);
  }

  Future<void> _restore() async {
    final next = await store.load();
    if (next.isExpired) await store.clearToken();
    final restored = next.isExpired ? await store.load() : next;
    Map<String, dynamic>? hotel;
    if (restored.hotelSlug.isNotEmpty) {
      try {
        hotel = Map<String, dynamic>.from(
          (await ApiClient(restored).get('property'))['data'],
        );
      } catch (_) {
        // The selected property remains editable from the connection screen.
      }
    }
    if (mounted) {
      setState(() {
        session = restored;
        property = hotel;
        loading = false;
      });
    }
  }

  Future<void> selectHotel(
    String baseUrl,
    String slug,
    Map<String, dynamic> hotel,
  ) async {
    await store.saveHotel(baseUrl, slug);
    final next = await store.load();
    setState(() {
      session = next;
      property = hotel;
    });
  }

  Future<void> authenticated(String token, DateTime? expiresAt) async {
    await store.saveToken(token, expiresAt);
    setState(
      () => session = SessionData(
        baseUrl: session!.baseUrl,
        hotelSlug: session!.hotelSlug,
        deviceId: session!.deviceId,
        token: token,
        expiresAt: expiresAt,
      ),
    );
  }

  Future<void> logout() async {
    await PushService.disable(session!);
    try {
      await ApiClient(session!).post('auth/logout');
    } catch (_) {}
    await store.clearToken();
    final next = await store.load();
    setState(() => session = next);
  }

  Future<void> changeHotel() async {
    await store.clearHotel();
    final next = await store.load();
    setState(() {
      session = next;
      property = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final primary = colorFromHex(
      property?['primary_color']?.toString(),
      const Color(0xff1e3a5f),
    );
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: property?['name']?.toString() ?? 'Hotel Guest',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: primary,
          surface: Colors.white,
        ),
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xfff4f6f9),
        appBarTheme: const AppBarTheme(
          backgroundColor: Colors.transparent,
          surfaceTintColor: Colors.transparent,
          elevation: 0,
        ),
        inputDecorationTheme: InputDecorationTheme(
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: Color(0xffe5e9f0)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(color: primary, width: 1.5),
          ),
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 16,
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            minimumSize: const Size(0, 52),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(15),
            ),
            textStyle: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            minimumSize: const Size(0, 52),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(15),
            ),
            side: const BorderSide(color: Color(0xffdce1e8)),
            textStyle: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        navigationBarTheme: NavigationBarThemeData(
          height: 72,
          elevation: 0,
          backgroundColor: Colors.white,
          indicatorColor: primary.withValues(alpha: 0.12),
          labelTextStyle: WidgetStateProperty.resolveWith(
            (states) => TextStyle(
              fontSize: 11,
              fontWeight: states.contains(WidgetState.selected)
                  ? FontWeight.w700
                  : FontWeight.w500,
              color: states.contains(WidgetState.selected)
                  ? primary
                  : const Color(0xff687386),
            ),
          ),
        ),
      ),
      home: loading
          ? const SplashScreen()
          : session!.hotelSlug.isEmpty || property == null
          ? HotelScreen(initialUrl: session!.baseUrl, onSelected: selectHotel)
          : !session!.isAuthenticated
          ? AuthScreen(
              session: session!,
              property: property!,
              onAuthenticated: authenticated,
              onChangeHotel: changeHotel,
            )
          : GuestHome(session: session!, property: property!, onLogout: logout),
    );
  }
}

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});
  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: CircularProgressIndicator()));
}

class GuestHome extends StatefulWidget {
  const GuestHome({
    super.key,
    required this.session,
    required this.property,
    required this.onLogout,
  });
  final SessionData session;
  final Map<String, dynamic> property;
  final Future<void> Function() onLogout;
  @override
  State<GuestHome> createState() => _GuestHomeState();
}

class _GuestHomeState extends State<GuestHome> {
  int index = 0;
  @override
  void initState() {
    super.initState();
    PushService.configure(
      widget.session,
      widget.property,
      onNotificationOpened: (data) => WidgetsBinding.instance
          .addPostFrameCallback((_) => openNotification(data)),
    );
  }

  Future<void> openNotification(Map<String, dynamic> rawData) async {
    if (!mounted) return;
    final destination = notificationDestination(rawData);
    setState(() => index = destination.tabIndex);

    if (destination.requestId != null) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => RequestThreadScreen(
            session: widget.session,
            requestId: destination.requestId!,
          ),
        ),
      );
      return;
    }
    if (destination.openPreArrival && destination.reservationId != null) {
      try {
        final response = await ApiClient(widget.session).get('reservations');
        final reservations = List<dynamic>.from(response['data']);
        final rawReservation = reservations.cast<dynamic>().where((item) {
          final reservation = Map<String, dynamic>.from(item);
          return reservation['id'].toString() ==
              destination.reservationId.toString();
        }).firstOrNull;
        if (rawReservation != null && mounted) {
          await showDialog<void>(
            context: context,
            builder: (_) => PreArrivalDialog(
              session: widget.session,
              property: widget.property,
              reservation: Map<String, dynamic>.from(rawReservation),
            ),
          );
        }
      } on ApiException catch (exception) {
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(exception.message)));
        }
      }
      return;
    }
    if (destination.openRoomAccess) {
      if (!await SensitiveAccess.authorize(
        context,
        'Authenticate to open secure room access.',
      )) {
        return;
      }
      try {
        final response = await ApiClient(widget.session).get('stays/current');
        final stay = response['data'];
        final room = stay is Map ? stay['room'] : null;
        if (room is Map && room['mobile_access_available'] == true && mounted) {
          await Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => RoomAccessScreen(
                session: widget.session,
                roomNumber: room['number']?.toString() ?? '—',
              ),
            ),
          );
        }
      } on ApiException catch (exception) {
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(exception.message)));
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      OverviewTab(session: widget.session, property: widget.property),
      ReservationsTab(session: widget.session, property: widget.property),
      RequestsTab(session: widget.session),
      NotificationsTab(
        session: widget.session,
        firebaseAvailable: widget.property['firebase'] != null,
        onOpen: openNotification,
      ),
      ProfileTab(session: widget.session, onLogout: widget.onLogout),
    ];
    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 72,
        titleSpacing: 20,
        title: Row(
          children: [
            HotelLogo(property: widget.property, size: 42),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.property['name'].toString(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const Text(
                    'Guest experience',
                    style: TextStyle(
                      fontSize: 11,
                      color: Color(0xff768195),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: IconButton.filledTonal(
              tooltip: 'Refresh',
              onPressed: () => setState(() {}),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ),
        ],
      ),
      body: IndexedStack(index: index, children: screens),
      bottomNavigationBar: DecoratedBox(
        decoration: const BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: Color(0x12000000),
              blurRadius: 24,
              offset: Offset(0, -6),
            ),
          ],
        ),
        child: NavigationBar(
          selectedIndex: index,
          onDestinationSelected: (value) => setState(() => index = value),
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.bed_outlined),
              selectedIcon: Icon(Icons.bed),
              label: 'Stay',
            ),
            NavigationDestination(
              icon: Icon(Icons.event_outlined),
              selectedIcon: Icon(Icons.event),
              label: 'Bookings',
            ),
            NavigationDestination(
              icon: Icon(Icons.room_service_outlined),
              selectedIcon: Icon(Icons.room_service),
              label: 'Requests',
            ),
            NavigationDestination(
              icon: Icon(Icons.notifications_outlined),
              selectedIcon: Icon(Icons.notifications),
              label: 'Inbox',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Account',
            ),
          ],
        ),
      ),
    );
  }
}
