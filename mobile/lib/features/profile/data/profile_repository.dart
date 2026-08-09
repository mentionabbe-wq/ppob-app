import 'package:dio/dio.dart';

import '../../../core/network/dio_client.dart';
import '../../../core/utils/parse.dart';
import '../../auth/domain/user.dart';

class WalletMutation {
  const WalletMutation({
    required this.id,
    required this.typeLabel,
    required this.amount,
    required this.balanceAfter,
    required this.description,
    this.createdAt,
  });

  final int id;
  final String typeLabel;
  final double amount;
  final double balanceAfter;
  final String description;
  final DateTime? createdAt;

  bool get isCredit => amount > 0;

  factory WalletMutation.fromJson(Map<String, dynamic> json) => WalletMutation(
        id: asInt(json['id']),
        typeLabel: asString(json['type_label']),
        amount: asDouble(json['amount']),
        balanceAfter: asDouble(json['balance_after']),
        description: asString(json['description']),
        createdAt: asDate(json['created_at']),
      );
}

class BankAccount {
  const BankAccount({
    required this.id,
    required this.bankName,
    required this.accountNumber,
    required this.accountName,
    this.isPrimary = false,
  });

  final int id;
  final String bankName;
  final String accountNumber;
  final String accountName;
  final bool isPrimary;

  factory BankAccount.fromJson(Map<String, dynamic> json) => BankAccount(
        id: asInt(json['id']),
        bankName: asString(json['bank_name']),
        accountNumber: asString(json['account_number']),
        accountName: asString(json['account_name']),
        isPrimary: asBool(json['is_primary']),
      );
}

class ProfileRepository {
  const ProfileRepository(this._client);

  final DioClient _client;

  Future<User> update({String? name, String? phone, String? avatarPath}) async {
    final Map<String, dynamic> data = {
      if (name != null) 'name': name,
      if (phone != null) 'phone': phone,
    };

    // Upload berkas butuh multipart + method spoofing (_method=PUT).
    if (avatarPath != null) {
      final form = FormData.fromMap({
        ...data,
        '_method': 'PUT',
        'avatar': await MultipartFile.fromFile(avatarPath),
      });

      final response = await _client.upload('/profile', form);

      return User.fromJson(response['data'] as Map<String, dynamic>);
    }

    final response = await _client.put('/profile', data: data);

    return User.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> changePassword({
    required String currentPassword,
    required String password,
  }) =>
      _client.put('/profile/password', data: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': password,
      });

  Future<void> setPin({
    required String pin,
    required String password,
    String? currentPin,
  }) =>
      _client.put('/profile/pin', data: {
        'pin': pin,
        'pin_confirmation': pin,
        'password': password,
        if (currentPin != null) 'current_pin': currentPin,
      });

  Future<List<WalletMutation>> mutations({String? type, int page = 1}) async {
    final response = await _client.get('/profile/mutations', query: {
      if (type != null) 'type': type,
      'page': page,
    });

    return asMapList(response['data']?['items']).map(WalletMutation.fromJson).toList();
  }

  Future<List<BankAccount>> bankAccounts() async {
    final response = await _client.get('/profile/bank-accounts');

    return asMapList(response['data']).map(BankAccount.fromJson).toList();
  }

  Future<void> addBankAccount({
    required String bankName,
    required String accountNumber,
    required String accountName,
    bool isPrimary = false,
  }) =>
      _client.post('/profile/bank-accounts', data: {
        'bank_name': bankName,
        'account_number': accountNumber,
        'account_name': accountName,
        'is_primary': isPrimary,
      });

  Future<void> deleteBankAccount(int id) => _client.delete('/profile/bank-accounts/$id');
}
