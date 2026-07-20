part of '../../main.dart';

class GuestFolioScreen extends StatefulWidget {
  const GuestFolioScreen({
    super.key,
    required this.session,
    required this.reservationId,
    required this.reference,
  });
  final SessionData session;
  final int reservationId;
  final String reference;
  @override
  State<GuestFolioScreen> createState() => _GuestFolioScreenState();
}

class _GuestFolioScreenState extends State<GuestFolioScreen> {
  Map<String, dynamic>? folio;
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
      final response = await ApiClient(
        widget.session,
      ).get('reservations/${widget.reservationId}/folio');
      folio = response['data'] is Map
          ? Map<String, dynamic>.from(response['data'])
          : null;
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String money(dynamic value) =>
      '${folio?['currency'] ?? 'USD'} ${(num.tryParse(value.toString()) ?? 0).toStringAsFixed(2)}';
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('Folio · ${widget.reference}')),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
        ? Center(child: ErrorCard(error!))
        : folio == null
        ? const Center(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Text(
                'No charges have been posted to this reservation yet.',
                textAlign: TextAlign.center,
              ),
            ),
          )
        : RefreshIndicator(
            onRefresh: load,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                MobilePageHeader(
                  eyebrow: 'GUEST FOLIO',
                  title: folio!['number'].toString(),
                  subtitle:
                      'Itemized charges, payments, refunds, and current balance.',
                  icon: Icons.receipt_long_outlined,
                ),
                const SizedBox(height: 16),
                Surface(
                  child: Column(
                    children: [
                      total('Charges', folio!['charges']),
                      total('Payments', folio!['payments']),
                      total('Refunds', folio!['refunds']),
                      const Divider(),
                      total('Balance due', folio!['balance'], strong: true),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                const Text(
                  'Charges',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                ...List<dynamic>.from(folio!['items'] ?? []).map((raw) {
                  final item = Map<String, dynamic>.from(raw);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Surface(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.receipt_outlined),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item['description'].toString(),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                Text(
                                  '${item['service_date']} · ${item['type'].toString().replaceAll('_', ' ')}',
                                  style: const TextStyle(
                                    color: Color(0xff687386),
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Text(
                            money(item['total_amount']),
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ],
                      ),
                    ),
                  );
                }),
                const SizedBox(height: 12),
                const Text(
                  'Payment activity',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                ),
                ...List<dynamic>.from(folio!['payment_activity'] ?? []).map((
                  raw,
                ) {
                  final payment = Map<String, dynamic>.from(raw);
                  return ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      payment['type'] == 'refund'
                          ? Icons.undo
                          : Icons.payments_outlined,
                    ),
                    title: Text(
                      '${payment['type']} · ${payment['method'].toString().replaceAll('_', ' ')}',
                    ),
                    subtitle: Text(payment['processed_at'].toString()),
                    trailing: Text(
                      money(payment['amount']),
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                  );
                }),
                const SizedBox(height: 30),
              ],
            ),
          ),
  );
  Widget total(String label, dynamic value, {bool strong = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontWeight: strong ? FontWeight.w800 : FontWeight.w500,
          ),
        ),
        Text(
          money(value),
          style: TextStyle(
            fontSize: strong ? 20 : 15,
            fontWeight: strong ? FontWeight.w900 : FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}
