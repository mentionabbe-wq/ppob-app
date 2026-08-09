import 'package:dio/dio.dart';

import '../../../core/network/dio_client.dart';
import '../../../core/utils/parse.dart';
import '../domain/deposit.dart';

class DepositRepository {
  const DepositRepository(this._client);

  final DioClient _client;

  Future<List<DepositMethod>> methods() async {
    final response = await _client.get('/deposits/methods');

    return asMapList(response['data']?['methods']).map(DepositMethod.fromJson).toList();
  }

  Future<List<Deposit>> list({String? status, int page = 1}) async {
    final response = await _client.get('/deposits', query: {
      if (status != null) 'status': status,
      'page': page,
    });

    return asMapList(response['data']).map(Deposit.fromJson).toList();
  }

  Future<Deposit> create({
    required double amount,
    required String method,
    String? channel,
  }) async {
    final response = await _client.post('/deposits', data: {
      'amount': amount,
      'method': method,
      if (channel != null) 'channel': channel,
    });

    return Deposit.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<Deposit> detail(int id) async {
    final response = await _client.get('/deposits/$id');

    return Deposit.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<Deposit> uploadProof(int id, String filePath) async {
    final form = FormData.fromMap({
      'proof': await MultipartFile.fromFile(filePath),
    });

    final response = await _client.upload('/deposits/$id/proof', form);

    return Deposit.fromJson(response['data'] as Map<String, dynamic>);
  }
}
