part of '../../main.dart';

class NotificationsTab extends StatefulWidget {
  const NotificationsTab({
    super.key,
    required this.session,
    required this.firebaseAvailable,
    required this.onOpen,
  });
  final SessionData session;
  final bool firebaseAvailable;
  final Future<void> Function(Map<String, dynamic>) onOpen;
  @override
  State<NotificationsTab> createState() => _NotificationsTabState();
}

class _NotificationsTabState extends State<NotificationsTab> {
  List<dynamic> items = [];
  Map<String, dynamic>? preferences;
  bool loading = true;
  int unread = 0;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final client = ApiClient(widget.session);
      final results = await Future.wait([
        client.get('notifications'),
        client.get('notification-preferences'),
      ]);
      items = List<dynamic>.from(results[0]['data']);
      unread = results[0]['meta']['unread'] ?? 0;
      preferences = Map<String, dynamic>.from(results[1]['data']);
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> read(Map<String, dynamic> item) async {
    if (item['read_at'] == null) {
      await ApiClient(widget.session).patch('notifications/${item['id']}/read');
      await load();
    }
  }

  Future<void> readAndOpen(Map<String, dynamic> item) async {
    await read(item);
    final data = item['data'] is Map
        ? Map<String, dynamic>.from(item['data'])
        : <String, dynamic>{};
    data.putIfAbsent('category', () => item['category']?.toString() ?? '');
    await widget.onOpen(data);
  }

  Future<void> readAll() async {
    await ApiClient(widget.session).patch('notifications/read-all');
    await load();
  }

  Future<void> savePreference(String key, bool value) async {
    final next = Map<String, dynamic>.from(preferences!)..[key] = value;
    setState(() => preferences = next);
    try {
      final response = await ApiClient(
        widget.session,
      ).put('notification-preferences', next);
      setState(() => preferences = Map<String, dynamic>.from(response['data']));
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  String label(String key) =>
      {
        'booking_updates': 'Booking updates',
        'access_updates': 'Room access',
        'service_updates': 'Service requests',
        'checkout_reminders': 'Checkout reminders',
        'marketing': 'Offers & marketing',
      }[key] ??
      key;
  IconData icon(String category) =>
      {
        'booking': Icons.event_available,
        'access': Icons.lock_outline,
        'service': Icons.room_service_outlined,
        'checkout': Icons.logout,
      }[category] ??
      Icons.notifications_outlined;
  Widget notificationCard(dynamic raw) {
    final item = Map<String, dynamic>.from(raw);
    final unreadItem = item['read_at'] == null;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => readAndOpen(item),
        child: Surface(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                foregroundColor: Theme.of(context).colorScheme.primary,
                child: Icon(icon(item['category']?.toString() ?? '')),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            item['title']?.toString() ?? '',
                            style: TextStyle(
                              fontWeight: unreadItem
                                  ? FontWeight.bold
                                  : FontWeight.w600,
                            ),
                          ),
                        ),
                        if (unreadItem)
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Colors.blue,
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      item['body']?.toString() ?? '',
                      style: TextStyle(color: Colors.grey.shade700),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      item['created_at'] != null
                          ? DateTime.parse(
                              item['created_at'],
                            ).toLocal().toString().substring(0, 16)
                          : '',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) return Center(child: ErrorCard(error!));
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
        children: [
          MobilePageHeader(
            eyebrow: 'STAY UP TO DATE',
            title: 'Inbox${unread > 0 ? ' · $unread' : ''}',
            subtitle:
                'Bookings, room access, and service updates in one place.',
            icon: Icons.notifications_none_rounded,
            action: unread > 0
                ? TextButton(onPressed: readAll, child: const Text('Read all'))
                : null,
          ),
          if (!widget.firebaseAvailable)
            Container(
              margin: const EdgeInsets.only(top: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.amber.shade50,
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Text(
                'Push delivery is not configured for this hotel yet. Updates still appear in this inbox.',
              ),
            ),
          const SizedBox(height: 14),
          if (items.isEmpty)
            const Surface(
              child: Column(
                children: [
                  Icon(Icons.notifications_none, size: 46),
                  SizedBox(height: 10),
                  Text(
                    'No notifications yet',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ],
              ),
            )
          else
            ...items.map(notificationCard),
          const SizedBox(height: 16),
          Text(
            'Preferences',
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          if (preferences != null)
            Surface(
              child: Column(
                children: preferences!.entries
                    .map(
                      (entry) => SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(label(entry.key)),
                        value: entry.value == true,
                        onChanged: (value) => savePreference(entry.key, value),
                      ),
                    )
                    .toList(),
              ),
            ),
        ],
      ),
    );
  }
}
