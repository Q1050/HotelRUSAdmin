part of '../../main.dart';

class RequestsTab extends StatefulWidget {
  const RequestsTab({super.key, required this.session});
  final SessionData session;
  @override
  State<RequestsTab> createState() => _RequestsTabState();
}

class _RequestsTabState extends State<RequestsTab> {
  List<dynamic> items = [];
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await ApiClient(widget.session).get('requests');
      items = List<dynamic>.from(response['data']);
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> create() async {
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const RequestDialog(),
    );
    if (result == null) return;
    try {
      await ApiClient(widget.session).post('requests', result);
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Request sent to the hotel.')),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> open(Map<String, dynamic> item) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => RequestThreadScreen(
          session: widget.session,
          requestId: item['id'] as int,
        ),
      ),
    );
    await load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.transparent,
    floatingActionButton: FloatingActionButton.extended(
      onPressed: create,
      icon: const Icon(Icons.add),
      label: const Text('New request'),
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
        ? Center(child: ErrorCard(error!))
        : RefreshIndicator(
            onRefresh: load,
            child: items.isEmpty
                ? ListView(
                    padding: const EdgeInsets.all(20),
                    children: const [
                      MobilePageHeader(
                        eyebrow: 'HOTEL SERVICES',
                        title: 'Requests',
                        subtitle:
                            'Message the team and follow every service update.',
                        icon: Icons.room_service_outlined,
                      ),
                      SizedBox(height: 90),
                      Icon(Icons.room_service_outlined, size: 50),
                      SizedBox(height: 12),
                      Text(
                        'No service requests yet',
                        textAlign: TextAlign.center,
                      ),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
                    itemCount: items.length + 1,
                    separatorBuilder: (context, index) =>
                        const SizedBox(height: 12),
                    itemBuilder: (_, i) {
                      if (i == 0) {
                        return const MobilePageHeader(
                          eyebrow: 'HOTEL SERVICES',
                          title: 'Requests',
                          subtitle:
                              'Message the team and follow every service update.',
                          icon: Icons.room_service_outlined,
                        );
                      }
                      final item = Map<String, dynamic>.from(items[i - 1]);
                      return Surface(
                        child: ListTile(
                          onTap: () => open(item),
                          contentPadding: EdgeInsets.zero,
                          leading: Container(
                            padding: const EdgeInsets.all(11),
                            decoration: BoxDecoration(
                              color: Theme.of(
                                context,
                              ).colorScheme.primaryContainer,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Icon(
                              item['type'] == 'maintenance'
                                  ? Icons.build_outlined
                                  : Icons.room_service_outlined,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                          ),
                          title: Text(
                            item['type']
                                .toString()
                                .replaceAll('_', ' ')
                                .toUpperCase(),
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 13,
                              letterSpacing: .4,
                            ),
                          ),
                          subtitle: Padding(
                            padding: const EdgeInsets.only(top: 5),
                            child: Text(
                              item['details']?.toString() ?? '',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          trailing: StatusPill(
                            status: item['status']?.toString() ?? 'pending',
                            label:
                                item['status']?.toString().replaceAll(
                                  '_',
                                  ' ',
                                ) ??
                                'pending',
                          ),
                          titleAlignment: ListTileTitleAlignment.top,
                        ),
                      );
                    },
                  ),
          ),
  );
}

class RequestThreadScreen extends StatefulWidget {
  const RequestThreadScreen({
    super.key,
    required this.session,
    required this.requestId,
  });
  final SessionData session;
  final int requestId;
  @override
  State<RequestThreadScreen> createState() => _RequestThreadScreenState();
}

class _RequestThreadScreenState extends State<RequestThreadScreen> {
  Map<String, dynamic>? request;
  List<dynamic> messages = [];
  List<dynamic> timeline = [];
  bool loading = true;
  bool sending = false;
  String tab = 'messages';
  String? error;
  final message = TextEditingController();
  XFile? attachment;
  Map<String, String> get mediaHeaders => {
    'Authorization': 'Bearer ${widget.session.token}',
    'X-Hotel-Slug': widget.session.hotelSlug,
    'X-Device-ID': widget.session.deviceId,
    'Accept': 'image/*',
  };
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await ApiClient(
        widget.session,
      ).get('requests/${widget.requestId}');
      final data = Map<String, dynamic>.from(response['data']);
      request = Map<String, dynamic>.from(data['request']);
      messages = List<dynamic>.from(data['messages']);
      timeline = List<dynamic>.from(data['timeline']);
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> pick() async {
    final image = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 1800,
    );
    if (image != null) setState(() => attachment = image);
  }

  Future<void> send() async {
    if (message.text.trim().isEmpty) return;
    setState(() => sending = true);
    try {
      await ApiClient(widget.session).multipart(
        'requests/${widget.requestId}/messages',
        {'message': message.text.trim()},
        {'attachment': attachment?.path},
      );
      message.clear();
      attachment = null;
      await load();
    } on ApiException catch (e) {
      show(e.message);
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  Future<void> cancel() async {
    final yes = await confirm(
      'Cancel request?',
      'The hotel will stop this request if work has not started.',
    );
    if (!yes) return;
    try {
      await ApiClient(
        widget.session,
      ).patch('requests/${widget.requestId}/cancel');
      await load();
    } on ApiException catch (e) {
      show(e.message);
    }
  }

  Future<void> resolve(String action) async {
    String? note;
    if (action == 'reopen') {
      note = await ask('What still needs attention?');
      if (note == null || note.trim().isEmpty) return;
    }
    try {
      await ApiClient(widget.session).patch(
        'requests/${widget.requestId}/resolution',
        {'action': action, 'message': note},
      );
      await load();
    } on ApiException catch (e) {
      show(e.message);
    }
  }

  Future<bool> confirm(String title, String body) async =>
      (await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: Text(title),
          content: Text(body),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Keep request'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Confirm'),
            ),
          ],
        ),
      )) ??
      false;
  Future<String?> ask(String title) async {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Message to hotel'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const Text('Send'),
          ),
        ],
      ),
    );
  }

  void show(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  Widget attachmentImage(int messageId) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Image.network(
        '${widget.session.baseUrl}/api/v1/requests/${widget.requestId}/messages/$messageId/attachment',
        headers: mediaHeaders,
        height: 150,
        width: 220,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stack) =>
            const Text('Attachment could not be loaded.'),
      ),
    ),
  );
  @override
  Widget build(BuildContext context) {
    final status = request?['status']?.toString();
    final closed = ['cancelled', 'confirmed'].contains(status);
    return Scaffold(
      appBar: AppBar(title: const Text('Request details')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
          ? Center(child: ErrorCard(error!))
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Surface(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                request!['type']
                                    .toString()
                                    .replaceAll('_', ' ')
                                    .toUpperCase(),
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                            Chip(label: Text(status!.replaceAll('_', ' '))),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(request!['details']?.toString() ?? ''),
                        const SizedBox(height: 8),
                        Text(
                          'Room ${request!['room']?['number'] ?? '—'} · ${request!['priority']} priority',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(
                        value: 'messages',
                        label: Text('Messages'),
                        icon: Icon(Icons.chat_bubble_outline),
                      ),
                      ButtonSegment(
                        value: 'timeline',
                        label: Text('Timeline'),
                        icon: Icon(Icons.history),
                      ),
                    ],
                    selected: {tab},
                    onSelectionChanged: (value) =>
                        setState(() => tab = value.first),
                  ),
                  const SizedBox(height: 14),
                  if (tab == 'messages') ...[
                    if (messages.isEmpty)
                      const Surface(
                        child: Text(
                          'No follow-up messages yet.',
                          textAlign: TextAlign.center,
                        ),
                      )
                    else
                      ...messages.map((raw) {
                        final item = Map<String, dynamic>.from(raw);
                        final mine = item['sender'] == 'guest';
                        return Align(
                          alignment: mine
                              ? Alignment.centerRight
                              : Alignment.centerLeft,
                          child: Container(
                            constraints: const BoxConstraints(maxWidth: 330),
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: mine
                                  ? Theme.of(
                                      context,
                                    ).colorScheme.primaryContainer
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  mine ? 'You' : item['sender'].toString(),
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(item['message']?.toString() ?? ''),
                                if (item['has_attachment'] == true)
                                  attachmentImage(item['id'] as int),
                                const SizedBox(height: 4),
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
                        );
                      }),
                    if (!closed) ...[
                      const SizedBox(height: 8),
                      Surface(
                        child: Column(
                          children: [
                            TextField(
                              controller: message,
                              maxLines: 3,
                              decoration: const InputDecoration(
                                labelText: 'Message the hotel',
                              ),
                            ),
                            if (attachment != null)
                              Align(
                                alignment: Alignment.centerLeft,
                                child: Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Chip(
                                    label: Text(attachment!.name),
                                    onDeleted: () =>
                                        setState(() => attachment = null),
                                  ),
                                ),
                              ),
                            const SizedBox(height: 8),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                IconButton(
                                  onPressed: pick,
                                  tooltip: 'Attach image',
                                  icon: const Icon(Icons.attach_file),
                                ),
                                FilledButton.icon(
                                  onPressed: sending ? null : send,
                                  icon: const Icon(Icons.send),
                                  label: Text(sending ? 'Sending…' : 'Send'),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ] else
                    ...timeline.map((raw) {
                      final item = Map<String, dynamic>.from(raw);
                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const CircleAvatar(radius: 8),
                        title: Text(item['label']?.toString() ?? ''),
                        subtitle: Text(
                          item['occurred_at'] != null
                              ? DateTime.parse(
                                  item['occurred_at'],
                                ).toLocal().toString().substring(0, 16)
                              : '',
                        ),
                      );
                    }),
                  if (request!['has_completion_photo'] == true) ...[
                    const SizedBox(height: 14),
                    Text(
                      'Completion photo',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(14),
                      child: Image.network(
                        '${widget.session.baseUrl}/api/v1/requests/${widget.requestId}/completion-photo',
                        headers: mediaHeaders,
                        height: 220,
                        fit: BoxFit.cover,
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  if (['pending', 'assigned'].contains(status))
                    OutlinedButton.icon(
                      onPressed: cancel,
                      icon: const Icon(Icons.cancel_outlined),
                      label: const Text('Cancel request'),
                    ),
                  if (status == 'completed') ...[
                    FilledButton.icon(
                      onPressed: () => resolve('confirm'),
                      icon: const Icon(Icons.check_circle),
                      label: const Text('Confirm resolved'),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: () => resolve('reopen'),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Still needs attention'),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}

class RequestDialog extends StatefulWidget {
  const RequestDialog({super.key});
  @override
  State<RequestDialog> createState() => _RequestDialogState();
}

class _RequestDialogState extends State<RequestDialog> {
  String type = 'housekeeping';
  final details = TextEditingController();
  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('New request'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        DropdownButtonFormField<String>(
          initialValue: type,
          decoration: const InputDecoration(labelText: 'Request type'),
          items: const [
            DropdownMenuItem(
              value: 'housekeeping',
              child: Text('Housekeeping'),
            ),
            DropdownMenuItem(value: 'linen', child: Text('Fresh linen')),
            DropdownMenuItem(value: 'amenity', child: Text('Amenity')),
            DropdownMenuItem(value: 'maintenance', child: Text('Maintenance')),
          ],
          onChanged: (v) => setState(() => type = v!),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: details,
          maxLines: 4,
          decoration: const InputDecoration(labelText: 'What do you need?'),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: () => Navigator.pop(context, {
          'type': type,
          'details': details.text,
          'priority': 'normal',
        }),
        child: const Text('Send'),
      ),
    ],
  );
}
