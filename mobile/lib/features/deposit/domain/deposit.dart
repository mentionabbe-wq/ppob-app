import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/parse.dart';

enum DepositStatus {
  pending,
  waitingPayment,
  paid,
  approved,
  rejected,
  expired;

  static DepositStatus parse(String? value) => switch (value) {
        'waiting_payment' => DepositStatus.waitingPayment,
        'paid' => DepositStatus.paid,
        'approved' => DepositStatus.approved,
        'rejected' => DepositStatus.rejected,
        'expired' => DepositStatus.expired,
        _ => DepositStatus.pending,
      };

  String get label => switch (this) {
        DepositStatus.pending => 'Menunggu',
        DepositStatus.waitingPayment => 'Menunggu Pembayaran',
        DepositStatus.paid => 'Sedang Diverifikasi',
        DepositStatus.approved => 'Berhasil',
        DepositStatus.rejected => 'Ditolak',
        DepositStatus.expired => 'Kedaluwarsa',
      };

  Color get color => switch (this) {
        DepositStatus.approved => AppColors.success,
        DepositStatus.rejected || DepositStatus.expired => AppColors.danger,
        DepositStatus.paid => AppColors.info,
        _ => AppColors.warning,
      };

  bool get isFinal =>
      this == DepositStatus.approved ||
      this == DepositStatus.rejected ||
      this == DepositStatus.expired;
}

class Deposit {
  const Deposit({
    required this.id,
    required this.code,
    required this.amount,
    required this.totalAmount,
    required this.method,
    required this.status,
    this.uniqueCode = 0,
    this.channel,
    this.vaNumber,
    this.qrisPayload,
    this.proofUrl,
    this.rejectReason,
    this.expiredAt,
    this.createdAt,
  });

  final int id;
  final String code;
  final double amount;
  final int uniqueCode;
  final double totalAmount;
  final String method;
  final String? channel;
  final String? vaNumber;
  final String? qrisPayload;
  final String? proofUrl;
  final DepositStatus status;
  final String? rejectReason;
  final DateTime? expiredAt;
  final DateTime? createdAt;

  bool get needsManualProof => method == 'bank_transfer';

  factory Deposit.fromJson(Map<String, dynamic> json) => Deposit(
        id: asInt(json['id']),
        code: asString(json['code']),
        amount: asDouble(json['amount']),
        uniqueCode: asInt(json['unique_code']),
        totalAmount: asDouble(json['total_amount']),
        method: asString(json['method']),
        channel: json['channel'] as String?,
        vaNumber: json['va_number'] as String?,
        qrisPayload: json['qris_payload'] as String?,
        proofUrl: json['proof_url'] as String?,
        status: DepositStatus.parse(json['status'] as String?),
        rejectReason: json['reject_reason'] as String?,
        expiredAt: asDate(json['expired_at']),
        createdAt: asDate(json['created_at']),
      );
}

class DepositMethod {
  const DepositMethod({
    required this.code,
    required this.name,
    required this.description,
    required this.channels,
  });

  final String code;
  final String name;
  final String description;
  final List<DepositChannel> channels;

  factory DepositMethod.fromJson(Map<String, dynamic> json) => DepositMethod(
        code: asString(json['code']),
        name: asString(json['name']),
        description: asString(json['description']),
        channels: asMapList(json['channels']).map(DepositChannel.fromJson).toList(),
      );
}

class DepositChannel {
  const DepositChannel({
    required this.code,
    required this.name,
    this.accountNumber,
    this.accountName,
  });

  final String code;
  final String name;
  final String? accountNumber;
  final String? accountName;

  factory DepositChannel.fromJson(Map<String, dynamic> json) => DepositChannel(
        code: asString(json['code']),
        name: asString(json['name']),
        accountNumber: json['account_number'] as String?,
        accountName: json['account_name'] as String?,
      );
}
