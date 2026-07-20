part of '../../main.dart';

class OverviewTab extends StatefulWidget {
  const OverviewTab({super.key, required this.session, required this.property});
  final SessionData session;
  final Map<String, dynamic> property;
  @override
  State<OverviewTab> createState() => _OverviewTabState();
}

class _OverviewTabState extends State<OverviewTab> {
  Map<String, dynamic>? stay;
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await ApiClient(widget.session).get('stays/current');
      stay = response['data'] == null
          ? null
          : Map<String, dynamic>.from(response['data']);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) return Center(child: ErrorCard(error!));
    final room = stay?['room'] as Map<String, dynamic>?;
    final support = widget.property['support'] as Map<String, dynamic>?;
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            widget.property['welcome_message']?.toString() ??
                'Your stay, all in one place.',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 18),
          if (stay == null)
            const Surface(
              child: Column(
                children: [
                  Icon(Icons.event_available, size: 44),
                  SizedBox(height: 12),
                  Text(
                    'No active stay',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  SizedBox(height: 6),
                  Text(
                    'Your room and mobile access will appear here after check-in.',
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            )
          else
            StayCard(
              stay: stay!,
              room: room ?? const {},
              onUnlock: room?['mobile_access_available'] == true
                  ? () async {
                      if (!await SensitiveAccess.authorize(
                        context,
                        'Authenticate to open secure room access.',
                      )) {
                        return;
                      }
                      if (context.mounted) {
                        await Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => RoomAccessScreen(
                              session: widget.session,
                              roomNumber: room?['number']?.toString() ?? '—',
                            ),
                          ),
                        );
                      }
                    }
                  : null,
            ),
          const SizedBox(height: 18),
          Surface(
            child: ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.support_agent),
              title: const Text('Need help?'),
              subtitle: Text(
                support?['phone']?.toString() ??
                    support?['email']?.toString() ??
                    'Contact the front desk',
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class StayCard extends StatelessWidget {
  const StayCard({
    super.key,
    required this.stay,
    required this.room,
    this.onUnlock,
  });

  final Map<String, dynamic> stay;
  final Map<String, dynamic> room;
  final VoidCallback? onUnlock;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final payment = stay['payment_status']?.toString() ?? 'pending';
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [primary, Color.lerp(primary, Colors.black, 0.28)!],
        ),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: primary.withValues(alpha: 0.25),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -26,
            top: -38,
            child: Container(
              width: 130,
              height: 130,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.07),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'CURRENT STAY',
                    style: TextStyle(
                      color: Color(0xffdce7f5),
                      fontSize: 11,
                      letterSpacing: 1.2,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(100),
                    ),
                    child: Text(
                      payment.replaceAll('_', ' ').toUpperCase(),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                'Room ${room['number'] ?? '—'}',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 34,
                  height: 1,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -1,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                '${room['type'] ?? 'Guest room'}  •  Floor ${room['floor'] ?? '—'}',
                style: const TextStyle(
                  color: Color(0xffdce7f5),
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 22),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.calendar_month_outlined,
                      color: Colors.white,
                      size: 19,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        '${stay['check_in_date']}  –  ${stay['check_out_date']}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (onUnlock != null) ...[
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: primary,
                    ),
                    onPressed: onUnlock,
                    icon: const Icon(Icons.lock_open_rounded),
                    label: const Text('Unlock my room'),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class RoomAccessScreen extends StatefulWidget {
  const RoomAccessScreen({
    super.key,
    required this.session,
    required this.roomNumber,
  });

  final SessionData session;
  final String roomNumber;

  @override
  State<RoomAccessScreen> createState() => _RoomAccessScreenState();
}

class _RoomAccessScreenState extends State<RoomAccessScreen> {
  final manualController = TextEditingController();
  bool processing = false;
  bool nfcSessionActive = false;
  bool unlocked = false;
  String? error;

  @override
  void dispose() {
    manualController.dispose();
    if (nfcSessionActive) NfcManager.instance.stopSession();
    super.dispose();
  }

  Future<void> unlock(String rawMarker, String scanType) async {
    if (!await SensitiveAccess.authorize(
      context,
      'Authenticate to unlock Room ${widget.roomNumber}.',
    )) {
      return;
    }
    final marker = extractRoomMarker(rawMarker);
    if (marker == null) {
      setState(() => error = 'This marker does not contain a valid room ID.');
      return;
    }

    setState(() {
      processing = true;
      error = null;
    });
    try {
      final body = {'marker': marker, 'scan_type': scanType};
      await ApiClient(widget.session).post('access/credential', body);
      await ApiClient(widget.session).post('access/unlock', body);
      if (mounted) setState(() => unlocked = true);
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } catch (_) {
      if (mounted) {
        setState(() => error = 'The room could not be unlocked. Try again.');
      }
    } finally {
      if (mounted) setState(() => processing = false);
    }
  }

  Future<void> scanQr() async {
    final marker = await Navigator.of(
      context,
    ).push<String>(MaterialPageRoute(builder: (_) => const RoomQrScanner()));
    if (marker != null && mounted) await unlock(marker, 'qr');
  }

  Future<void> scanNfc() async {
    setState(() => error = null);
    final availability = await NfcManager.instance.checkAvailability();
    if (availability != NfcAvailability.enabled) {
      if (mounted) {
        setState(
          () => error =
              'NFC is unavailable. Enable NFC or scan the room QR code.',
        );
      }
      return;
    }

    setState(() => nfcSessionActive = true);
    await NfcManager.instance.startSession(
      pollingOptions: {NfcPollingOption.iso14443, NfcPollingOption.iso15693},
      alertMessageIos: 'Hold your phone near the room access marker.',
      onDiscovered: (tag) async {
        try {
          final ndef = Ndef.from(tag);
          final message = ndef == null ? null : await ndef.read();
          final value = message?.records
              .map(
                (record) => utf8.decode(record.payload, allowMalformed: true),
              )
              .join(' ');
          final marker = value == null ? null : extractRoomMarker(value);
          await NfcManager.instance.stopSession(
            alertMessageIos: marker == null
                ? null
                : 'Room marker read successfully.',
            errorMessageIos: marker == null
                ? 'This NFC tag is not a valid room marker.'
                : null,
          );
          nfcSessionActive = false;
          if (!mounted) return;
          if (marker == null) {
            setState(() => error = 'This NFC tag is not a valid room marker.');
            return;
          }
          await unlock(marker, 'nfc');
        } catch (_) {
          await NfcManager.instance.stopSession(
            errorMessageIos: 'The NFC marker could not be read.',
          );
          nfcSessionActive = false;
          if (mounted) {
            setState(() => error = 'The NFC marker could not be read.');
          }
        }
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Room access')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (unlocked)
              Surface(
                child: Column(
                  children: [
                    const CircleAvatar(
                      radius: 34,
                      backgroundColor: Color(0xffdcfce7),
                      child: Icon(
                        Icons.lock_open,
                        size: 36,
                        color: Color(0xff15803d),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Room ${widget.roomNumber} unlocked',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'The unlock command was accepted by the door lock.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Done'),
                    ),
                  ],
                ),
              )
            else ...[
              Text(
                'Unlock Room ${widget.roomNumber}',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Scan the secure marker at your assigned room. Access is checked against your stay, identity, payment, and device.',
              ),
              const SizedBox(height: 20),
              if (error != null) ...[
                ErrorCard(error!),
                const SizedBox(height: 16),
              ],
              Surface(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FilledButton.icon(
                      onPressed: processing ? null : scanQr,
                      icon: const Icon(Icons.qr_code_scanner),
                      label: const Text('Scan room QR code'),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: processing ? null : scanNfc,
                      icon: const Icon(Icons.nfc),
                      label: const Text('Tap NFC room marker'),
                    ),
                    if (processing) ...[
                      const SizedBox(height: 18),
                      const Center(child: CircularProgressIndicator()),
                      const SizedBox(height: 8),
                      const Center(child: Text('Verifying room access…')),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 20),
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                title: const Text('Development fallback'),
                subtitle: const Text('Enter a marker UUID manually'),
                children: [
                  TextField(
                    controller: manualController,
                    autocorrect: false,
                    decoration: const InputDecoration(
                      labelText: 'Room marker UUID',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Align(
                    alignment: Alignment.centerRight,
                    child: OutlinedButton(
                      onPressed: processing
                          ? null
                          : () => unlock(manualController.text, 'qr'),
                      child: const Text('Verify and unlock'),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class RoomQrScanner extends StatefulWidget {
  const RoomQrScanner({super.key});

  @override
  State<RoomQrScanner> createState() => _RoomQrScannerState();
}

class _RoomQrScannerState extends State<RoomQrScanner> {
  final controller = MobileScannerController();
  bool detected = false;

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        foregroundColor: Colors.white,
        backgroundColor: Colors.black,
        title: const Text('Scan room QR code'),
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: controller,
            onDetect: (capture) {
              if (detected) return;
              for (final barcode in capture.barcodes) {
                final rawValue = barcode.rawValue;
                if (rawValue != null && extractRoomMarker(rawValue) != null) {
                  detected = true;
                  controller.stop();
                  Navigator.pop(context, rawValue);
                  return;
                }
              }
            },
          ),
          Center(
            child: Container(
              width: 250,
              height: 250,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.white, width: 3),
                borderRadius: BorderRadius.circular(20),
              ),
            ),
          ),
          const Positioned(
            left: 24,
            right: 24,
            bottom: 40,
            child: Text(
              'Point the camera at the QR marker beside your room door.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white, fontSize: 16),
            ),
          ),
        ],
      ),
    );
  }
}
