import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:ppob_app/core/utils/formatter.dart';
import 'package:ppob_app/core/utils/parse.dart';
import 'package:ppob_app/features/transaction/domain/transaction.dart';

void main() {
  setUpAll(() async => initializeDateFormatting('id_ID'));

  group('Formatter', () {
    test('memformat rupiah tanpa desimal', () {
      expect(Formatter.rupiah(15000), 'Rp15.000');
    });

    test('menyamarkan nomor tujuan', () {
      expect(Formatter.maskPhone('081234567890'), '0812****7890');
      expect(Formatter.maskPhone('0812'), '0812');
    });
  });

  group('parse', () {
    test('menerima angka dalam bentuk string maupun numerik', () {
      expect(asDouble('15000.50'), 15000.50);
      expect(asDouble(15000), 15000.0);
      expect(asDouble(null), 0);
      expect(asInt('42'), 42);
    });

    test('bool menerima 1/0 dan "true"', () {
      expect(asBool(1), isTrue);
      expect(asBool('true'), isTrue);
      expect(asBool(0), isFalse);
    });
  });

  group('TrxStatus', () {
    test('status akhir dikenali dengan benar', () {
      expect(TrxStatus.parse('success').isFinal, isTrue);
      expect(TrxStatus.parse('processing').isFinal, isFalse);
      expect(TrxStatus.parse('nilai-tak-dikenal'), TrxStatus.pending);
    });
  });

  group('Transaction.fromJson', () {
    test('menyembunyikan harga modal bila API tidak mengirimkannya', () {
      final trx = Transaction.fromJson({
        'id': 1,
        'invoice_no': 'INV001',
        'product_name': 'Pulsa 10.000',
        'customer_no': '081234567890',
        'sell_price': '11000',
        'total_paid': '11000',
        'status': 'success',
        'created_at': '2026-01-01T10:00:00+07:00',
      });

      expect(trx.totalPaid, 11000);
      expect(trx.basePrice, isNull);
      expect(trx.profit, isNull);
      expect(trx.status, TrxStatus.success);
    });
  });
}
