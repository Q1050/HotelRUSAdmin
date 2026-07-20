part of '../../main.dart';

class ReservationsTab extends StatefulWidget {
  const ReservationsTab({
    super.key,
    required this.session,
    required this.property,
  });
  final SessionData session;
  final Map<String, dynamic> property;
  @override
  State<ReservationsTab> createState() => _ReservationsTabState();
}

class _ReservationsTabState extends State<ReservationsTab> {
  List<dynamic> reservations = [];
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() => loading = true);
    try {
      final response = await ApiClient(widget.session).get('reservations');
      reservations = List<dynamic>.from(response['data']);
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> claim() async {
    final linked = await showDialog<bool>(
      context: context,
      builder: (_) => ClaimReservationDialog(session: widget.session),
    );
    if (linked == true) await load();
  }

  Future<void> preArrival(Map<String, dynamic> reservation) async {
    await showDialog<void>(
      context: context,
      builder: (_) => PreArrivalDialog(
        session: widget.session,
        property: widget.property,
        reservation: reservation,
      ),
    );
    await load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.transparent,
    floatingActionButton: FloatingActionButton.extended(
      onPressed: claim,
      icon: const Icon(Icons.link),
      label: const Text('Claim booking'),
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
        ? Center(child: ErrorCard(error!))
        : RefreshIndicator(
            onRefresh: load,
            child: reservations.isEmpty
                ? ListView(
                    padding: const EdgeInsets.all(24),
                    children: const [
                      MobilePageHeader(
                        eyebrow: 'YOUR TRIPS',
                        title: 'Bookings',
                        subtitle:
                            'Reservations, arrival details, and check-in preparation.',
                        icon: Icons.luggage_outlined,
                      ),
                      SizedBox(height: 80),
                      Icon(Icons.event_busy, size: 52),
                      SizedBox(height: 12),
                      Text(
                        'No reservations linked',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      SizedBox(height: 6),
                      Text(
                        'Use Claim booking if the hotel created your reservation before you made this account.',
                        textAlign: TextAlign.center,
                      ),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
                    itemCount: reservations.length + 1,
                    separatorBuilder: (context, index) =>
                        const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      if (index == 0) {
                        return const MobilePageHeader(
                          eyebrow: 'YOUR TRIPS',
                          title: 'Bookings',
                          subtitle:
                              'Reservations, arrival details, and check-in preparation.',
                          icon: Icons.luggage_outlined,
                        );
                      }
                      final item = Map<String, dynamic>.from(
                        reservations[index - 1],
                      );
                      final room = item['room'] as Map<String, dynamic>?;
                      final available = [
                        'pending',
                        'confirmed',
                      ].contains(item['status']);
                      return Surface(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.primaryContainer,
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                  child: Icon(
                                    Icons.hotel_outlined,
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.primary,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        item['reference'].toString(),
                                        style: const TextStyle(
                                          fontSize: 16,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                      const SizedBox(height: 3),
                                      Text(
                                        room == null
                                            ? '${item['room_type'] ?? 'Room'} · Room pending'
                                            : 'Room ${room['number']} · ${room['type']}',
                                        style: const TextStyle(
                                          color: Color(0xff687386),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                StatusPill(
                                  status:
                                      item['status']?.toString() ?? 'pending',
                                  label: item['status'].toString().replaceAll(
                                    '_',
                                    ' ',
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 16),
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: const Color(0xfff5f7fa),
                                borderRadius: BorderRadius.circular(14),
                              ),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.calendar_month_outlined,
                                    size: 18,
                                    color: Color(0xff687386),
                                  ),
                                  const SizedBox(width: 9),
                                  Expanded(
                                    child: Text(
                                      '${item['arrival_date'].toString().substring(0, 10)}  –  ${item['departure_date'].toString().substring(0, 10)}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (available) ...[
                              const SizedBox(height: 14),
                              FilledButton.icon(
                                onPressed: () => preArrival(item),
                                icon: const Icon(Icons.badge_outlined),
                                label: const Text('Pre-arrival check-in'),
                              ),
                            ],
                            const SizedBox(height: 10),
                            OutlinedButton.icon(
                              onPressed: () => Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => GuestFolioScreen(
                                    session: widget.session,
                                    reservationId: item['id'] as int,
                                    reference: item['reference'].toString(),
                                  ),
                                ),
                              ),
                              icon: const Icon(Icons.receipt_long_outlined),
                              label: const Text('View folio & receipts'),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
          ),
  );
}

class ClaimReservationDialog extends StatefulWidget {
  const ClaimReservationDialog({super.key, required this.session});
  final SessionData session;
  @override
  State<ClaimReservationDialog> createState() => _ClaimReservationDialogState();
}

class _ClaimReservationDialogState extends State<ClaimReservationDialog> {
  final reference = TextEditingController();
  final code = TextEditingController();
  bool codeSent = false;
  bool busy = false;
  String? message;
  String? error;
  Future<void> requestCode() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final response = await ApiClient(widget.session).post(
        'reservations/claim/request',
        {'reference': reference.text.trim()},
      );
      if (response['already_claimed'] == true) {
        if (mounted) Navigator.pop(context, true);
        return;
      }
      setState(() {
        codeSent = true;
        message = response['message']?.toString();
      });
    } on ApiException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> verify() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiClient(widget.session).post('reservations/claim/verify', {
        'reference': reference.text.trim(),
        'code': code.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Claim a booking'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Use the reference supplied by the hotel. The booking email or phone must match this account.',
          ),
          const SizedBox(height: 16),
          TextField(
            controller: reference,
            enabled: !codeSent,
            textCapitalization: TextCapitalization.characters,
            decoration: const InputDecoration(
              labelText: 'Booking reference',
              hintText: 'RS-AB12CD34',
            ),
          ),
          if (codeSent) ...[
            const SizedBox(height: 12),
            TextField(
              controller: code,
              keyboardType: TextInputType.number,
              maxLength: 6,
              decoration: const InputDecoration(
                labelText: 'Six-digit email code',
              ),
            ),
            if (message != null)
              Text(message!, style: const TextStyle(color: Colors.green)),
          ],
          if (error != null) ErrorCard(error!),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: busy ? null : () => Navigator.pop(context, false),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: busy ? null : (codeSent ? verify : requestCode),
        child: Text(
          busy
              ? 'Please wait…'
              : codeSent
              ? 'Verify & link'
              : 'Send code',
        ),
      ),
    ],
  );
}

class PreArrivalDialog extends StatefulWidget {
  const PreArrivalDialog({
    super.key,
    required this.session,
    required this.property,
    required this.reservation,
  });
  final SessionData session;
  final Map<String, dynamic> property;
  final Map<String, dynamic> reservation;
  @override
  State<PreArrivalDialog> createState() => _PreArrivalDialogState();
}

class _PreArrivalDialogState extends State<PreArrivalDialog> {
  final idNumber = TextEditingController();
  final arrival = TextEditingController();
  final notes = TextEditingController();
  String idType = 'passport';
  XFile? front;
  XFile? back;
  Map<String, dynamic>? existing;
  bool accepted = false;
  bool loading = true;
  bool busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await ApiClient(
        widget.session,
      ).get('reservations/${widget.reservation['id']}/pre-arrival');
      if (response['data'] != null) {
        existing = Map<String, dynamic>.from(response['data']);
        idType = existing!['id_type'] ?? 'passport';
        idNumber.text = existing!['id_number'] ?? '';
        arrival.text = existing!['estimated_arrival_time'] ?? '';
        notes.text = existing!['guest_notes'] ?? '';
        accepted = existing!['policy_accepted'] == true;
      }
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> pick(bool isFront) async {
    final image = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 2200,
    );
    if (image != null) {
      setState(() {
        if (isFront) {
          front = image;
        } else {
          back = image;
        }
      });
    }
  }

  Future<void> submit() async {
    if (front == null) {
      setState(
        () => error = 'Select the front of your identification document.',
      );
      return;
    }
    if (!accepted) {
      setState(() => error = 'Accept the hotel policies before submitting.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiClient(widget.session).multipart(
        'reservations/${widget.reservation['id']}/pre-arrival',
        {
          'id_type': idType,
          'id_number': idNumber.text.trim(),
          'estimated_arrival_time': arrival.text.trim(),
          'guest_notes': notes.text.trim(),
          'policy_accepted': '1',
        },
        {'id_document_front': front!.path, 'id_document_back': back?.path},
      );
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      setState(
        () => error = e.errors.values.isNotEmpty
            ? (e.errors.values.first as List).first.toString()
            : e.message,
      );
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final approved = existing?['status'] == 'approved';
    final policies = widget.property['policies'] as Map<String, dynamic>?;
    return AlertDialog(
      title: Text('Pre-arrival · ${widget.reservation['reference']}'),
      content: loading
          ? const SizedBox(
              height: 120,
              child: Center(child: CircularProgressIndicator()),
            )
          : SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (existing != null)
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: existing!['status'] == 'rejected'
                            ? Colors.red.shade50
                            : existing!['status'] == 'approved'
                            ? Colors.green.shade50
                            : Colors.amber.shade50,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        'Status: ${existing!['status']}${existing!['review_notes'] != null ? '\n${existing!['review_notes']}' : ''}',
                      ),
                    ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: idType,
                    decoration: const InputDecoration(labelText: 'ID type'),
                    items: const [
                      DropdownMenuItem(
                        value: 'passport',
                        child: Text('Passport'),
                      ),
                      DropdownMenuItem(
                        value: 'drivers_license',
                        child: Text('Driver’s license'),
                      ),
                      DropdownMenuItem(
                        value: 'national_id',
                        child: Text('National ID'),
                      ),
                    ],
                    onChanged: approved
                        ? null
                        : (value) => setState(() => idType = value!),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: idNumber,
                    enabled: !approved,
                    decoration: const InputDecoration(labelText: 'ID number'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: arrival,
                    enabled: !approved,
                    keyboardType: TextInputType.datetime,
                    decoration: const InputDecoration(
                      labelText: 'Estimated arrival time',
                      hintText: '15:30',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: notes,
                    enabled: !approved,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Arrival notes (optional)',
                    ),
                  ),
                  if (!approved) ...[
                    const SizedBox(height: 14),
                    OutlinedButton.icon(
                      onPressed: () => pick(true),
                      icon: const Icon(Icons.badge),
                      label: Text(
                        front == null
                            ? 'Select ID front'
                            : 'Front selected: ${front!.name}',
                      ),
                    ),
                    OutlinedButton.icon(
                      onPressed: () => pick(false),
                      icon: const Icon(Icons.badge_outlined),
                      label: Text(
                        back == null
                            ? 'Select ID back (optional)'
                            : 'Back selected: ${back!.name}',
                      ),
                    ),
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: accepted,
                      onChanged: (value) =>
                          setState(() => accepted = value ?? false),
                      title: const Text(
                        'I accept the hotel terms and privacy policy',
                      ),
                      subtitle: Text(
                        policies?['terms'] != null
                            ? 'Hotel policies are available through this property.'
                            : 'Consent is required for identity review.',
                      ),
                    ),
                  ],
                  if (error != null) ErrorCard(error!),
                ],
              ),
            ),
      actions: [
        TextButton(
          onPressed: busy ? null : () => Navigator.pop(context),
          child: Text(approved ? 'Close' : 'Cancel'),
        ),
        if (!loading && !approved)
          FilledButton(
            onPressed: busy ? null : submit,
            child: Text(
              busy
                  ? 'Submitting…'
                  : existing?['status'] == 'rejected'
                  ? 'Resubmit'
                  : 'Submit for review',
            ),
          ),
      ],
    );
  }
}
