import 'package:intl/intl.dart';

class Formatter {
  const Formatter._();

  static final _currency = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp',
    decimalDigits: 0,
  );

  static final _compact = NumberFormat.compactCurrency(
    locale: 'id_ID',
    symbol: 'Rp',
    decimalDigits: 0,
  );

  static String rupiah(num value) => _currency.format(value);

  static String rupiahCompact(num value) => _compact.format(value);

  static String number(num value) => NumberFormat.decimalPattern('id_ID').format(value);

  static String date(DateTime? value) =>
      value == null ? '-' : DateFormat('d MMM yyyy', 'id_ID').format(value);

  static String dateTime(DateTime? value) =>
      value == null ? '-' : DateFormat('d MMM yyyy, HH:mm', 'id_ID').format(value);

  /// Menyamarkan nomor tujuan pada daftar transaksi: 0812****7890.
  static String maskPhone(String value) {
    if (value.length < 8) return value;

    return '${value.substring(0, 4)}****${value.substring(value.length - 4)}';
  }

  static String relative(DateTime? value) {
    if (value == null) return '-';

    final diff = DateTime.now().difference(value);

    if (diff.inMinutes < 1) return 'Baru saja';
    if (diff.inMinutes < 60) return '${diff.inMinutes} menit lalu';
    if (diff.inHours < 24) return '${diff.inHours} jam lalu';
    if (diff.inDays < 7) return '${diff.inDays} hari lalu';

    return date(value);
  }
}
