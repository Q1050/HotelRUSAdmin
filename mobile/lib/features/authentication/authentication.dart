part of '../../main.dart';

class HotelScreen extends StatefulWidget {
  const HotelScreen({
    super.key,
    required this.initialUrl,
    required this.onSelected,
  });
  final String initialUrl;
  final Future<void> Function(String, String, Map<String, dynamic>) onSelected;
  @override
  State<HotelScreen> createState() => _HotelScreenState();
}

class _HotelScreenState extends State<HotelScreen> {
  late final url = TextEditingController(text: widget.initialUrl);
  final slug = TextEditingController();
  bool busy = false;
  String? error;

  Future<void> connect() async {
    setState(() {
      busy = true;
      error = null;
    });
    final temporary = SessionData(
      baseUrl: url.text.trim().replaceAll(RegExp(r'/$'), ''),
      hotelSlug: slug.text.trim().toLowerCase(),
      deviceId: '00000000-0000-4000-8000-000000000000',
    );
    try {
      final response = await ApiClient(temporary).get('property');
      await widget.onSelected(
        temporary.baseUrl,
        temporary.hotelSlug,
        Map<String, dynamic>.from(response['data']),
      );
    } on ApiException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(Icons.apartment_rounded, size: 62),
                const SizedBox(height: 20),
                Text(
                  'Find your hotel',
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Enter the property code supplied with your reservation.',
                ),
                const SizedBox(height: 28),
                TextField(
                  controller: slug,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Hotel code',
                    hintText: 'ocean-view',
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: url,
                  keyboardType: TextInputType.url,
                  decoration: const InputDecoration(
                    labelText: 'Server address',
                  ),
                ),
                if (error != null) ErrorCard(error!),
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: busy || slug.text.trim().isEmpty
                      ? connect
                      : connect,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: busy
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Continue'),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'For local Android development use http://10.0.2.2:8000. For iOS Simulator use http://127.0.0.1:8000.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

class AuthScreen extends StatefulWidget {
  const AuthScreen({
    super.key,
    required this.session,
    required this.property,
    required this.onAuthenticated,
    required this.onChangeHotel,
  });
  final SessionData session;
  final Map<String, dynamic> property;
  final Future<void> Function(String, DateTime?) onAuthenticated;
  final VoidCallback onChangeHotel;
  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  final first = TextEditingController();
  final last = TextEditingController();
  final phone = TextEditingController();
  bool register = false;
  bool busy = false;
  String? error;

  Future<void> submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    final body = <String, dynamic>{
      'email': email.text.trim(),
      'password': password.text,
      'device_id': widget.session.deviceId,
      'device_name': Platform.localHostname,
      'platform': Platform.isIOS ? 'ios' : 'android',
    };
    if (register) {
      body.addAll({
        'first_name': first.text.trim(),
        'last_name': last.text.trim(),
        'phone': phone.text.trim(),
        'password_confirmation': password.text,
      });
    }
    try {
      final response = await ApiClient(
        widget.session,
      ).post(register ? 'auth/register' : 'auth/login', body);
      await widget.onAuthenticated(
        response['data']['token'].toString(),
        DateTime.tryParse(
          response['data']['expires_at']?.toString() ?? '',
        )?.toUtc(),
      );
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
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (widget.property['logo_url'] != null)
                  Center(
                    child: Image.network(
                      widget.property['logo_url'],
                      height: 72,
                      errorBuilder: (context, error, stackTrace) =>
                          const Icon(Icons.hotel, size: 60),
                    ),
                  )
                else
                  const Icon(Icons.hotel, size: 60),
                const SizedBox(height: 18),
                Text(
                  widget.property['name'].toString(),
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  register ? 'Create your guest account' : 'Welcome back',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 28),
                if (register) ...[
                  TextField(
                    controller: first,
                    decoration: const InputDecoration(labelText: 'First name'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: last,
                    decoration: const InputDecoration(labelText: 'Last name'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Phone (optional)',
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Password'),
                ),
                if (error != null) ErrorCard(error!),
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: busy ? null : submit,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: busy
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(register ? 'Create account' : 'Sign in'),
                  ),
                ),
                if (!register)
                  TextButton(
                    onPressed: busy
                        ? null
                        : () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => PasswordResetScreen(
                                session: widget.session,
                                initialEmail: email.text.trim(),
                              ),
                            ),
                          ),
                    child: const Text('Forgot password?'),
                  ),
                TextButton(
                  onPressed: busy
                      ? null
                      : () => setState(() {
                          register = !register;
                          error = null;
                        }),
                  child: Text(
                    register
                        ? 'Already have an account? Sign in'
                        : 'New guest? Create an account',
                  ),
                ),
                TextButton(
                  onPressed: widget.onChangeHotel,
                  child: const Text('Use a different hotel'),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

class PasswordResetScreen extends StatefulWidget {
  const PasswordResetScreen({
    super.key,
    required this.session,
    this.initialEmail = '',
  });

  final SessionData session;
  final String initialEmail;

  @override
  State<PasswordResetScreen> createState() => _PasswordResetScreenState();
}

class _PasswordResetScreenState extends State<PasswordResetScreen> {
  late final email = TextEditingController(text: widget.initialEmail);
  final code = TextEditingController();
  final password = TextEditingController();
  final confirmation = TextEditingController();
  bool codeSent = false;
  bool completed = false;
  bool busy = false;
  String? error;
  String? notice;

  @override
  void dispose() {
    email.dispose();
    code.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  String apiError(ApiException exception) {
    if (exception.errors.values.isNotEmpty) {
      final first = exception.errors.values.first;
      if (first is List && first.isNotEmpty) return first.first.toString();
    }
    return exception.message;
  }

  Future<void> requestCode() async {
    if (email.text.trim().isEmpty) {
      setState(() => error = 'Enter the email address for your account.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    try {
      final response = await ApiClient(
        widget.session,
      ).post('auth/forgot-password', {'email': email.text.trim()});
      if (mounted) {
        setState(() {
          codeSent = true;
          notice = response['message']?.toString();
        });
      }
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = apiError(exception));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> resetPassword() async {
    if (code.text.trim().length != 6) {
      setState(() => error = 'Enter the six-digit code from your email.');
      return;
    }
    if (password.text.length < 8) {
      setState(
        () => error = 'Your new password must be at least 8 characters.',
      );
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
      await ApiClient(widget.session).post('auth/reset-password', {
        'email': email.text.trim(),
        'token': code.text.trim(),
        'password': password.text,
        'password_confirmation': confirmation.text,
      });
      if (mounted) setState(() => completed = true);
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = apiError(exception));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reset password')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: completed
                  ? Surface(
                      child: Column(
                        children: [
                          const CircleAvatar(
                            radius: 32,
                            backgroundColor: Color(0xffdcfce7),
                            child: Icon(
                              Icons.check,
                              size: 34,
                              color: Color(0xff15803d),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            'Password updated',
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'You can now sign in using your new password.',
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 20),
                          FilledButton(
                            onPressed: () => Navigator.pop(context),
                            child: const Text('Return to sign in'),
                          ),
                        ],
                      ),
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Icon(Icons.lock_reset, size: 58),
                        const SizedBox(height: 18),
                        Text(
                          codeSent ? 'Check your email' : 'Forgot password?',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          codeSent
                              ? 'Enter the six-digit code and choose a new password.'
                              : 'We will email a one-time code to your guest account.',
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 24),
                        TextField(
                          controller: email,
                          readOnly: codeSent,
                          keyboardType: TextInputType.emailAddress,
                          autofillHints: const [AutofillHints.email],
                          decoration: const InputDecoration(
                            labelText: 'Account email',
                          ),
                        ),
                        if (codeSent) ...[
                          const SizedBox(height: 12),
                          TextField(
                            controller: code,
                            keyboardType: TextInputType.number,
                            maxLength: 6,
                            autofillHints: const [AutofillHints.oneTimeCode],
                            decoration: const InputDecoration(
                              labelText: 'Six-digit code',
                              counterText: '',
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: password,
                            obscureText: true,
                            autofillHints: const [AutofillHints.newPassword],
                            decoration: const InputDecoration(
                              labelText: 'New password',
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: confirmation,
                            obscureText: true,
                            decoration: const InputDecoration(
                              labelText: 'Confirm new password',
                            ),
                          ),
                        ],
                        if (notice != null) ...[
                          const SizedBox(height: 12),
                          Text(
                            notice!,
                            style: TextStyle(color: Colors.green.shade800),
                          ),
                        ],
                        if (error != null) ErrorCard(error!),
                        const SizedBox(height: 18),
                        FilledButton(
                          onPressed: busy
                              ? null
                              : codeSent
                              ? resetPassword
                              : requestCode,
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: busy
                                ? const SizedBox.square(
                                    dimension: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : Text(
                                    codeSent
                                        ? 'Update password'
                                        : 'Send reset code',
                                  ),
                          ),
                        ),
                        if (codeSent)
                          TextButton(
                            onPressed: busy ? null : requestCode,
                            child: const Text('Send a new code'),
                          ),
                      ],
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
