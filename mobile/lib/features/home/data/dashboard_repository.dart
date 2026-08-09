import '../../../core/network/dio_client.dart';
import '../../../core/utils/parse.dart';
import '../../catalog/domain/product.dart';
import '../../transaction/domain/transaction.dart';

class Banner {
  const Banner({
    required this.id,
    required this.title,
    required this.imageUrl,
    this.actionType = 'none',
    this.actionValue,
  });

  final int id;
  final String title;
  final String imageUrl;
  final String actionType;
  final String? actionValue;

  factory Banner.fromJson(Map<String, dynamic> json) => Banner(
        id: asInt(json['id']),
        title: asString(json['title']),
        imageUrl: asString(json['image_url']),
        actionType: asString(json['action_type'], 'none'),
        actionValue: json['action_value'] as String?,
      );
}

class Promo {
  const Promo({
    required this.code,
    required this.title,
    this.description,
    this.discountValue = 0,
    this.discountType = 'fixed',
  });

  final String code;
  final String title;
  final String? description;
  final double discountValue;
  final String discountType;

  factory Promo.fromJson(Map<String, dynamic> json) => Promo(
        code: asString(json['code']),
        title: asString(json['title']),
        description: json['description'] as String?,
        discountValue: asDouble(json['discount_value']),
        discountType: asString(json['discount_type'], 'fixed'),
      );
}

/// Seluruh isi beranda diambil dalam satu panggilan agar layar
/// terisi sekaligus (bukan lima permintaan paralel).
class DashboardData {
  const DashboardData({
    required this.balance,
    required this.totalTransaction,
    required this.successTransaction,
    required this.pendingTransaction,
    required this.banners,
    required this.promos,
    required this.menus,
    required this.favorites,
    required this.recentTransactions,
  });

  final double balance;
  final int totalTransaction;
  final int successTransaction;
  final int pendingTransaction;
  final List<Banner> banners;
  final List<Promo> promos;
  final List<Category> menus;
  final List<Category> favorites;
  final List<Transaction> recentTransactions;

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'] as Map<String, dynamic>? ?? const {};

    return DashboardData(
      balance: asDouble(json['balance']),
      totalTransaction: asInt(summary['total_transaction']),
      successTransaction: asInt(summary['success_transaction']),
      pendingTransaction: asInt(summary['pending_transaction']),
      banners: asMapList(json['banners']).map(Banner.fromJson).toList(),
      promos: asMapList(json['promos']).map(Promo.fromJson).toList(),
      menus: asMapList(json['menus']).map(Category.fromJson).toList(),
      favorites: asMapList(json['favorites']).map(Category.fromJson).toList(),
      recentTransactions:
          asMapList(json['recent_transactions']).map(Transaction.fromJson).toList(),
    );
  }
}

class DashboardRepository {
  const DashboardRepository(this._client);

  final DioClient _client;

  Future<DashboardData> load() async {
    final response = await _client.get('/dashboard');

    return DashboardData.fromJson(response['data'] as Map<String, dynamic>);
  }
}
