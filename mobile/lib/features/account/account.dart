part of '../../main.dart';

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key, required this.session, required this.onLogout});
  final SessionData session;
  final Future<void> Function() onLogout;
  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> with WidgetsBindingObserver {
  Map<String, dynamic>? guest;
  List<Map<String, dynamic>> devices = [];
  bool revoking = false;
  bool securityUnlocked = false;
  String? error;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if ((state == AppLifecycleState.paused ||
            state == AppLifecycleState.detached) &&
        securityUnlocked) {
      setState(() => securityUnlocked = false);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> load() async {
    try {
      final client = ApiClient(widget.session);
      final responses = await Future.wait([
        client.get('me'),
        client.get('devices'),
      ]);
      guest = Map<String, dynamic>.from(responses[0]['data']);
      devices = List<dynamic>.from(
        responses[1]['data'],
      ).map((item) => Map<String, dynamic>.from(item)).toList();
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() {});
    }
  }

  Future<void> revokeDevice(Map<String, dynamic> device) async {
    if (!await SensitiveAccess.authorize(
      context,
      'Authenticate to revoke a signed-in device.',
    )) {
      return;
    }
    if (!mounted) return;
    final name = device['name']?.toString().trim();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Revoke device access?'),
        content: Text(
          '${name == null || name.isEmpty ? 'This device' : name} will be signed out and will need the account password to connect again.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Revoke access'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => revoking = true);
    try {
      await ApiClient(widget.session).delete('devices/${device['device_id']}');
      await load();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Device access revoked.')));
      }
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.message)));
      }
    } finally {
      if (mounted) setState(() => revoking = false);
    }
  }

  Future<void> revokeOtherDevices() async {
    if (!await SensitiveAccess.authorize(
      context,
      'Authenticate to sign out all other devices.',
    )) {
      return;
    }
    if (!mounted) return;
    final activeOthers = devices
        .where(
          (device) =>
              device['device_id'] != widget.session.deviceId &&
              device['revoked_at'] == null,
        )
        .length;
    if (activeOthers == 0) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sign out other devices?'),
        content: Text(
          'This immediately revokes access for $activeOthers other device(s). This phone remains signed in.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Sign out devices'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => revoking = true);
    try {
      final response = await ApiClient(widget.session).delete('devices/others');
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(response['message']?.toString() ?? 'Done.')),
        );
      }
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.message)));
      }
    } finally {
      if (mounted) setState(() => revoking = false);
    }
  }

  Future<void> changePassword() async {
    if (!await SensitiveAccess.authorize(
      context,
      'Authenticate to change your account password.',
    )) {
      return;
    }
    if (!mounted) return;
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => ChangePasswordDialog(session: widget.session),
    );
    if (changed == true) {
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Password updated. Other devices were signed out.'),
          ),
        );
      }
    }
  }

  Future<void> exportData() async {
    if (!await SensitiveAccess.authorize(
      context,
      'Authenticate to export your personal data.',
    )) {
      return;
    }
    try {
      final response = await ApiClient(widget.session).post('privacy/export');
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Your data export'),
          content: SizedBox(
            width: 520,
            child: SingleChildScrollView(
              child: SelectableText(
                const JsonEncoder.withIndent('  ').convert(response['data']),
                style: const TextStyle(fontFamily: 'monospace', fontSize: 11),
              ),
            ),
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Done'),
            ),
          ],
        ),
      );
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.message)));
      }
    }
  }

  Future<void> requestDeletion() async {
    if (!await SensitiveAccess.authorize(
      context,
      'Authenticate to request account deletion.',
    )) {
      return;
    }
    if (!mounted) return;
    final sent = await showDialog<bool>(
      context: context,
      builder: (_) => AccountDeletionDialog(session: widget.session),
    );
    if (sent == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Deletion request sent to the hotel for review.'),
        ),
      );
    }
  }

  String deviceName(Map<String, dynamic> device) {
    final name = device['name']?.toString().trim();
    if (name != null && name.isNotEmpty) return name;
    return device['platform']?.toString() == 'ios'
        ? 'Apple device'
        : 'Android device';
  }

  Future<void> revealSecurity() async {
    if (await SensitiveAccess.authorize(
      context,
      'Authenticate to view signed-in devices.',
    )) {
      if (mounted) setState(() => securityUnlocked = true);
    }
  }

  String lastSeen(Map<String, dynamic> device) {
    final value = device['last_seen_at']?.toString();
    if (value == null || value.isEmpty) return 'Last active time unavailable';
    final date = DateTime.tryParse(value)?.toLocal();
    if (date == null) return 'Last active time unavailable';
    final minute = date.minute.toString().padLeft(2, '0');
    return 'Last active ${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')} ${date.hour}:$minute';
  }

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(20),
    children: [
      const MobilePageHeader(
        eyebrow: 'YOUR ACCOUNT',
        title: 'Profile & security',
        subtitle: 'Personal details, trusted devices, and account protection.',
        icon: Icons.person_outline_rounded,
      ),
      const SizedBox(height: 16),
      if (guest == null && error == null)
        const Center(child: CircularProgressIndicator())
      else if (error != null)
        ErrorCard(error!)
      else
        Surface(
          child: Column(
            children: [
              CircleAvatar(
                radius: 34,
                child: Text('${guest!['first_name']}'.characters.first),
              ),
              const SizedBox(height: 12),
              Text(
                '${guest!['first_name']} ${guest!['last_name']}',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
              ),
              Text(guest!['email'].toString()),
              const SizedBox(height: 12),
              Chip(label: Text('ID: ${guest!['id_status']}')),
            ],
          ),
        ),
      const SizedBox(height: 18),
      Text(
        'Signed-in devices',
        style: Theme.of(
          context,
        ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
      ),
      const SizedBox(height: 8),
      Surface(
        child: !securityUnlocked
            ? Column(
                children: [
                  Container(
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primaryContainer,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.fingerprint_rounded,
                      size: 30,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Protected security details',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 5),
                  const Text(
                    'Authenticate to view and manage devices signed into this account.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Color(0xff687386)),
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: revealSecurity,
                    icon: const Icon(Icons.lock_open_rounded),
                    label: const Text('View signed-in devices'),
                  ),
                ],
              )
            : devices.isEmpty
            ? const Text('No registered devices were found.')
            : Column(
                children: [
                  for (var index = 0; index < devices.length; index++) ...[
                    Builder(
                      builder: (context) {
                        final device = devices[index];
                        final current =
                            device['device_id'] == widget.session.deviceId;
                        final revoked = device['revoked_at'] != null;
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: Icon(
                            device['platform'] == 'ios'
                                ? Icons.phone_iphone
                                : Icons.phone_android,
                          ),
                          title: Row(
                            children: [
                              Flexible(child: Text(deviceName(device))),
                              if (current) ...[
                                const SizedBox(width: 8),
                                const Chip(
                                  visualDensity: VisualDensity.compact,
                                  label: Text('This device'),
                                ),
                              ],
                            ],
                          ),
                          subtitle: Text(
                            revoked ? 'Access revoked' : lastSeen(device),
                          ),
                          trailing: !current && !revoked
                              ? IconButton(
                                  tooltip: 'Revoke access',
                                  onPressed: revoking
                                      ? null
                                      : () => revokeDevice(device),
                                  icon: const Icon(Icons.logout),
                                )
                              : null,
                        );
                      },
                    ),
                    if (index < devices.length - 1) const Divider(),
                  ],
                ],
              ),
      ),
      const SizedBox(height: 18),
      OutlinedButton.icon(
        onPressed: changePassword,
        icon: const Icon(Icons.password),
        label: const Padding(
          padding: EdgeInsets.all(13),
          child: Text('Change password'),
        ),
      ),
      if (securityUnlocked &&
          devices.any(
            (device) =>
                device['device_id'] != widget.session.deviceId &&
                device['revoked_at'] == null,
          )) ...[
        const SizedBox(height: 10),
        OutlinedButton.icon(
          onPressed: revoking ? null : revokeOtherDevices,
          icon: const Icon(Icons.phonelink_erase),
          label: const Padding(
            padding: EdgeInsets.all(13),
            child: Text('Sign out all other devices'),
          ),
        ),
      ],
      const SizedBox(height: 10),
      Surface(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Privacy & your data',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
            ),
            const SizedBox(height: 5),
            const Text(
              'Review a portable copy of your information or ask the hotel to delete and anonymize your account.',
              style: TextStyle(color: Color(0xff687386)),
            ),
            const SizedBox(height: 14),
            OutlinedButton.icon(
              onPressed: exportData,
              icon: const Icon(Icons.data_object_rounded),
              label: const Text('Export my data'),
            ),
            const SizedBox(height: 9),
            TextButton.icon(
              style: TextButton.styleFrom(foregroundColor: Colors.red.shade700),
              onPressed: requestDeletion,
              icon: const Icon(Icons.delete_outline_rounded),
              label: const Text('Request account deletion'),
            ),
          ],
        ),
      ),
      const SizedBox(height: 10),
      OutlinedButton.icon(
        onPressed: widget.onLogout,
        icon: const Icon(Icons.logout),
        label: const Padding(
          padding: EdgeInsets.all(13),
          child: Text('Sign out'),
        ),
      ),
    ],
  );
}

class ChangePasswordDialog extends StatefulWidget {
  const ChangePasswordDialog({super.key, required this.session});
  final SessionData session;

  @override
  State<ChangePasswordDialog> createState() => _ChangePasswordDialogState();
}

class _ChangePasswordDialogState extends State<ChangePasswordDialog> {
  final current = TextEditingController();
  final password = TextEditingController();
  final confirmation = TextEditingController();
  bool busy = false;
  String? error;

  @override
  void dispose() {
    current.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (password.text.length < 8) {
      setState(() => error = 'The new password must be at least 8 characters.');
      return;
    }
    if (password.text != confirmation.text) {
      setState(() => error = 'The password confirmation does not match.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiClient(widget.session).put('account/password', {
        'current_password': current.text,
        'password': password.text,
        'password_confirmation': confirmation.text,
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (exception) {
      if (mounted) {
        setState(() {
          final first = exception.errors.values.firstOrNull;
          error = first is List && first.isNotEmpty
              ? first.first.toString()
              : exception.message;
        });
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Change password'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'Changing your password will also sign out every other device.',
          ),
          const SizedBox(height: 16),
          TextField(
            controller: current,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Current password'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: password,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'New password'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: confirmation,
            obscureText: true,
            decoration: const InputDecoration(
              labelText: 'Confirm new password',
            ),
          ),
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
        onPressed: busy ? null : submit,
        child: Text(busy ? 'Updating…' : 'Update password'),
      ),
    ],
  );
}

class AccountDeletionDialog extends StatefulWidget {
  const AccountDeletionDialog({super.key, required this.session});
  final SessionData session;
  @override
  State<AccountDeletionDialog> createState() => _AccountDeletionDialogState();
}

class _AccountDeletionDialogState extends State<AccountDeletionDialog> {
  final password = TextEditingController();
  final reason = TextEditingController();
  bool busy = false;
  String? error;

  @override
  void dispose() {
    password.dispose();
    reason.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiClient(widget.session).post('privacy/deletion', {
        'password': password.text,
        'reason': reason.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Request account deletion'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'The hotel must review this request. Active stays must be completed first, and required operational records will be retained in anonymized form.',
          ),
          const SizedBox(height: 16),
          TextField(
            controller: password,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Account password'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: reason,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Reason (optional)'),
          ),
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
        style: FilledButton.styleFrom(backgroundColor: Colors.red.shade700),
        onPressed: busy ? null : submit,
        child: Text(busy ? 'Submitting…' : 'Submit request'),
      ),
    ],
  );
}
