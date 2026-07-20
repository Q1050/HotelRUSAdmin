part of '../../main.dart';

class MobilePageHeader extends StatelessWidget {
  const MobilePageHeader({
    super.key,
    required this.eyebrow,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.action,
  });

  final String eyebrow;
  final String title;
  final String subtitle;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Icon(icon, color: Theme.of(context).colorScheme.primary),
      ),
      const SizedBox(width: 13),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              eyebrow,
              style: TextStyle(
                color: Theme.of(context).colorScheme.primary,
                fontSize: 10,
                letterSpacing: 1.1,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              title,
              style: const TextStyle(
                fontSize: 23,
                height: 1.1,
                letterSpacing: -0.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              subtitle,
              style: const TextStyle(
                color: Color(0xff687386),
                fontSize: 13,
                height: 1.35,
              ),
            ),
          ],
        ),
      ),
      ?action,
    ],
  );
}

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.status, required this.label});
  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final colors = switch (status) {
      'confirmed' ||
      'completed' ||
      'verified' ||
      'checked_in' => const (Color(0xffdcfce7), Color(0xff166534)),
      'cancelled' ||
      'rejected' ||
      'failed' => const (Color(0xfffee2e2), Color(0xff991b1b)),
      'assigned' ||
      'in_progress' => const (Color(0xffdbeafe), Color(0xff1d4ed8)),
      _ => const (Color(0xfffff7d6), Color(0xff92400e)),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: colors.$1,
        borderRadius: BorderRadius.circular(100),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: colors.$2,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class Surface extends StatelessWidget {
  const Surface({super.key, required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(22),
      border: Border.all(color: const Color(0xffe8ebf0)),
      boxShadow: const [
        BoxShadow(
          color: Color(0x0a000000),
          blurRadius: 22,
          offset: Offset(0, 8),
        ),
      ],
    ),
    child: child,
  );
}

class HotelLogo extends StatelessWidget {
  const HotelLogo({super.key, required this.property, this.size = 48});
  final Map<String, dynamic> property;
  final double size;

  @override
  Widget build(BuildContext context) {
    final logo = property['logo_url']?.toString();
    return Container(
      width: size,
      height: size,
      padding: const EdgeInsets.all(2),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(size * 0.32),
        border: Border.all(color: const Color(0xffe5e9f0)),
      ),
      clipBehavior: Clip.antiAlias,
      child: logo == null || logo.isEmpty
          ? Icon(
              Icons.hotel_rounded,
              size: size * 0.56,
              color: Theme.of(context).colorScheme.primary,
            )
          : Image.network(
              logo,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => Icon(
                Icons.hotel_rounded,
                color: Theme.of(context).colorScheme.primary,
              ),
            ),
    );
  }
}

class ErrorCard extends StatelessWidget {
  const ErrorCard(this.message, {super.key});
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(top: 14),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Colors.red.shade50,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(message, style: TextStyle(color: Colors.red.shade800)),
  );
}

Color colorFromHex(String? value, Color fallback) {
  if (value == null) return fallback;
  final hex = value.replaceFirst('#', '');
  return Color(int.tryParse('ff$hex', radix: 16) ?? fallback.toARGB32());
}
