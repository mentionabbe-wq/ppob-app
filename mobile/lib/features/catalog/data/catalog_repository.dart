import '../../../core/network/dio_client.dart';
import '../../../core/utils/parse.dart';
import '../domain/product.dart';

class CatalogRepository {
  const CatalogRepository(this._client);

  final DioClient _client;

  Future<List<Category>> categories() async {
    final response = await _client.get('/categories');

    return asMapList(response['data']).map(Category.fromJson).toList();
  }

  Future<List<Product>> products({
    String? categorySlug,
    int? categoryId,
    String? brand,
    String? search,
  }) async {
    final response = await _client.get('/products', query: {
      if (categorySlug != null) 'category_slug': categorySlug,
      if (categoryId != null) 'category_id': categoryId,
      if (brand != null) 'brand': brand,
      if (search != null && search.isNotEmpty) 'search': search,
    });

    return asMapList(response['data']).map(Product.fromJson).toList();
  }

  Future<List<String>> brands(String categorySlug) async {
    final response = await _client.get('/categories/$categorySlug/brands');

    return asStringList(response['data']);
  }

  /// Deteksi operator dari prefiks nomor agar daftar produk langsung
  /// tersaring tanpa pengguna memilih operator manual.
  Future<String?> detectOperator(String phone) async {
    final response = await _client.get(
      '/products/detect-operator',
      query: {'phone': phone},
    );

    return response['data']?['operator'] as String?;
  }

  Future<BillInquiry> inquiry({required int productId, required String customerNo}) async {
    final response = await _client.post('/products/inquiry', data: {
      'product_id': productId,
      'customer_no': customerNo,
    });

    return BillInquiry.fromJson(response['data'] as Map<String, dynamic>);
  }
}
