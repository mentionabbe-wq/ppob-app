import '../../../core/utils/parse.dart';

class Category {
  const Category({
    required this.id,
    required this.name,
    required this.slug,
    required this.type,
    this.icon,
    this.color,
    this.inputLabel = 'Nomor Tujuan',
    this.inputType = 'phone',
    this.description,
    this.children = const [],
  });

  final int id;
  final String name;
  final String slug;
  final String type;
  final String? icon;
  final String? color;
  final String inputLabel;
  final String inputType;
  final String? description;
  final List<Category> children;

  /// Produk pascabayar wajib melewati cek tagihan sebelum dibayar.
  bool get isPostpaid => type == 'postpaid';

  factory Category.fromJson(Map<String, dynamic> json) => Category(
        id: asInt(json['id']),
        name: asString(json['name']),
        slug: asString(json['slug']),
        type: asString(json['type'], 'prepaid'),
        icon: json['icon'] as String?,
        color: json['color'] as String?,
        inputLabel: asString(json['input_label'], 'Nomor Tujuan'),
        inputType: asString(json['input_type'], 'phone'),
        description: json['description'] as String?,
        children: asMapList(json['children']).map(Category.fromJson).toList(),
      );
}

class Product {
  const Product({
    required this.id,
    required this.sku,
    required this.name,
    required this.price,
    this.brand,
    this.type,
    this.adminFee = 0,
    this.description,
    this.isAvailable = true,
    this.isFeatured = false,
    this.category,
  });

  final int id;
  final String sku;
  final String name;
  final String? brand;
  final String? type;
  final double price;
  final double adminFee;
  final String? description;
  final bool isAvailable;
  final bool isFeatured;
  final Category? category;

  double get total => price + adminFee;

  factory Product.fromJson(Map<String, dynamic> json) => Product(
        id: asInt(json['id']),
        sku: asString(json['sku']),
        name: asString(json['name']),
        brand: json['brand'] as String?,
        type: json['type'] as String?,
        price: asDouble(json['price']),
        adminFee: asDouble(json['admin_fee']),
        description: json['description'] as String?,
        isAvailable: asBool(json['is_available'], true),
        isFeatured: asBool(json['is_featured']),
        category: json['category'] is Map
            ? Category.fromJson(Map<String, dynamic>.from(json['category']))
            : null,
      );
}

/// Hasil cek tagihan pascabayar.
class BillInquiry {
  const BillInquiry({
    required this.customerNo,
    required this.billAmount,
    required this.adminFee,
    required this.total,
    this.customerName,
    this.period,
  });

  final String customerNo;
  final String? customerName;
  final double billAmount;
  final double adminFee;
  final double total;
  final String? period;

  factory BillInquiry.fromJson(Map<String, dynamic> json) => BillInquiry(
        customerNo: asString(json['customer_no']),
        customerName: json['customer_name'] as String?,
        billAmount: asDouble(json['bill_amount']),
        adminFee: asDouble(json['admin_fee']),
        total: asDouble(json['total']),
        period: json['period'] as String?,
      );
}
