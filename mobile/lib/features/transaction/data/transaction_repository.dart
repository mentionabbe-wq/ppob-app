import 'package:uuid/uuid.dart';

import '../../../core/network/dio_client.dart';
import '../../../core/utils/parse.dart';
import '../domain/transaction.dart';

class TransactionRepository {
  const TransactionRepository(this._client);

  final DioClient _client;

  static const _uuid = Uuid();

  Future<List<Transaction>> list({
    String? status,
    String? from,
    String? to,
    String? search,
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _client.get('/transactions', query: {
      if (status != null) 'status': status,
      if (from != null) 'from': from,
      if (to != null) 'to': to,
      if (search != null && search.isNotEmpty) 'search': search,
      'page': page,
      'per_page': perPage,
    });

    return asMapList(response['data']).map(Transaction.fromJson).toList();
  }

  /// [refId] adalah kunci idempotency: bila permintaan diulang karena
  /// jaringan putus, server mengembalikan transaksi yang sama.
  Future<Transaction> purchase({
    required int productId,
    required String customerNo,
    String? promoCode,
    String? pin,
    String? refId,
  }) async {
    final response = await _client.post('/transactions', data: {
      'product_id': productId,
      'customer_no': customerNo,
      'ref_id': refId ?? generateRefId(),
      if (promoCode != null && promoCode.isNotEmpty) 'promo_code': promoCode,
      if (pin != null && pin.isNotEmpty) 'pin': pin,
    });

    return Transaction.fromJson(response['data'] as Map<String, dynamic>);
  }

  static String generateRefId() =>
      'TRX${_uuid.v4().replaceAll('-', '').substring(0, 24).toUpperCase()}';

  Future<Transaction> detail(int id) async {
    final response = await _client.get('/transactions/$id');

    return Transaction.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<TrxStatus> status(int id) async {
    final response = await _client.get('/transactions/$id/status');

    return TrxStatus.parse(response['data']?['status'] as String?);
  }
}
