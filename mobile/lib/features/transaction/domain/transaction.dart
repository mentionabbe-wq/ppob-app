import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/parse.dart';
import '../../catalog/domain/product.dart';

enum TrxStatus {
  pending,
  processing,
  success,
  failed,
  refunded,
  canceled;

  static TrxStatus parse(String? value) => switch (value) {
        'success' => TrxStatus.success,
        'failed' => TrxStatus.failed,
        'refunded' => TrxStatus.refunded,
        'canceled' => TrxStatus.canceled,
        'processing' => TrxStatus.processing,
        _ => TrxStatus.pending,
      };

  bool get isFinal => this != TrxStatus.pending && this != TrxStatus.processing;

  String get label => switch (this) {
        TrxStatus.pending => 'Menunggu',
        TrxStatus.processing => 'Diproses',
        TrxStatus.success => 'Berhasil',
        TrxStatus.failed => 'Gagal',
        TrxStatus.refunded => 'Dana Dikembalikan',
        TrxStatus.canceled => 'Dibatalkan',
      };

  Color get color => switch (this) {
        TrxStatus.success => AppColors.success,
        TrxStatus.failed || TrxStatus.canceled => AppColors.danger,
        TrxStatus.refunded => AppColors.info,
        _ => AppColors.warning,
      };

  IconData get icon => switch (this) {
        TrxStatus.success => Icons.check_circle_rounded,
        TrxStatus.failed || TrxStatus.canceled => Icons.cancel_rounded,
        TrxStatus.refunded => Icons.replay_circle_filled_rounded,
        _ => Icons.access_time_filled_rounded,
      };
}

class Transaction {
  const Transaction({
    required this.id,
    required this.invoiceNo,
    required this.productName,
    required this.customerNo,
    required this.sellPrice,
    required this.totalPaid,
    required this.status,
    required this.createdAt,
    this.refId = '',
    this.customerName,
    this.adminFee = 0,
    this.discount = 0,
    this.basePrice,
    this.profit,
    this.serialNumber,
    this.message,
    this.invoiceUrl,
    this.completedAt,
    this.product,
  });

  final int id;
  final String invoiceNo;
  final String refId;
  final String productName;
  final String customerNo;
  final String? customerName;
  final double sellPrice;
  final double adminFee;
  final double discount;
  final double totalPaid;

  /// Hanya terisi untuk akun reseller/admin — API menyembunyikannya
  /// dari pengguna ritel.
  final double? basePrice;
  final double? profit;

  final TrxStatus status;
  final String? serialNumber;
  final String? message;
  final String? invoiceUrl;
  final DateTime? createdAt;
  final DateTime? completedAt;
  final Product? product;

  factory Transaction.fromJson(Map<String, dynamic> json) => Transaction(
        id: asInt(json['id']),
        invoiceNo: asString(json['invoice_no']),
        refId: asString(json['ref_id']),
        productName: asString(json['product_name']),
        customerNo: asString(json['customer_no']),
        customerName: json['customer_name'] as String?,
        sellPrice: asDouble(json['sell_price']),
        adminFee: asDouble(json['admin_fee']),
        discount: asDouble(json['discount']),
        totalPaid: asDouble(json['total_paid']),
        basePrice: json['base_price'] == null ? null : asDouble(json['base_price']),
        profit: json['profit'] == null ? null : asDouble(json['profit']),
        status: TrxStatus.parse(json['status'] as String?),
        serialNumber: json['serial_number'] as String?,
        message: json['message'] as String?,
        invoiceUrl: json['invoice_url'] as String?,
        createdAt: asDate(json['created_at']),
        completedAt: asDate(json['completed_at']),
        product: json['product'] is Map
            ? Product.fromJson(Map<String, dynamic>.from(json['product']))
            : null,
      );
}
