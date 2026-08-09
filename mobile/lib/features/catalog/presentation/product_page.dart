import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../../core/widgets/app_snackbar.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/product.dart';
import 'checkout_sheet.dart';

final _categoryProvider =
    FutureProvider.autoDispose.family<Category?, String>((ref, slug) async {
  final categories = await ref.watch(catalogRepositoryProvider).categories();

  for (final category in categories) {
    if (category.slug == slug) return category;

    for (final child in category.children) {
      if (child.slug == slug) return child;
    }
  }

  return null;
});

final _productsProvider = FutureProvider.autoDispose
    .family<List<Product>, ({String slug, String? brand})>((ref, args) {
  return ref.watch(catalogRepositoryProvider).products(
        categorySlug: args.slug,
        brand: args.brand,
      );
});

class ProductPage extends ConsumerStatefulWidget {
  const ProductPage({super.key, required this.categorySlug, this.category});

  final String categorySlug;
  final Category? category;

  @override
  ConsumerState<ProductPage> createState() => _ProductPageState();
}

class _ProductPageState extends ConsumerState<ProductPage> {
  final _customerNo = TextEditingController();

  String? _brand;
  String? _detectedOperator;
  BillInquiry? _inquiry;
  bool _checkingBill = false;

  @override
  void dispose() {
    _customerNo.dispose();
    super.dispose();
  }

  /// Untuk pulsa/paket data, operator dideteksi dari prefiks nomor
  /// sehingga daftar produk langsung tersaring.
  Future<void> _onNumberChanged(String value, Category category) async {
    setState(() => _inquiry = null);

    if (category.inputType != 'phone' || value.length < 4) return;

    try {
      final operator = await ref.read(catalogRepositoryProvider).detectOperator(value);

      if (mounted && operator != _detectedOperator) {
        setState(() {
          _detectedOperator = operator;
          _brand = operator;
        });
      }
    } catch (_) {
      // Deteksi operator bersifat opsional — abaikan bila gagal.
    }
  }

  Future<void> _checkBill(Category category, List<Product> products) async {
    if (_customerNo.text.trim().length < 4 || products.isEmpty) {
      AppSnackbar.info(context, 'Masukkan ${category.inputLabel} terlebih dahulu.');
      return;
    }

    setState(() => _checkingBill = true);

    try {
      final result = await ref.read(catalogRepositoryProvider).inquiry(
            productId: products.first.id,
            customerNo: _customerNo.text.trim(),
          );

      if (mounted) setState(() => _inquiry = result);
    } on ApiException catch (e) {
      if (mounted) AppSnackbar.error(context, e.message);
    } finally {
      if (mounted) setState(() => _checkingBill = false);
    }
  }

  void _openCheckout(Product product, Category category) {
    final customerNo = _customerNo.text.trim();

    if (customerNo.length < 4) {
      AppSnackbar.info(context, '${category.inputLabel} belum diisi dengan benar.');
      return;
    }

    if (category.isPostpaid && _inquiry == null) {
      AppSnackbar.info(context, 'Cek tagihan terlebih dahulu.');
      return;
    }

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => CheckoutSheet(
        product: product,
        customerNo: customerNo,
        inquiry: _inquiry,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final categoryAsync = widget.category != null
        ? AsyncValue.data(widget.category)
        : ref.watch(_categoryProvider(widget.categorySlug));

    return Scaffold(
      appBar: AppBar(title: Text(categoryAsync.valueOrNull?.name ?? 'Produk')),
      body: categoryAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (category) {
          if (category == null) {
            return const Center(child: Text('Kategori tidak ditemukan.'));
          }

          final products = ref.watch(
            _productsProvider((slug: widget.categorySlug, brand: _brand)),
          );

          return Column(
            children: [
              _InputSection(
                category: category,
                controller: _customerNo,
                detectedOperator: _detectedOperator,
                onChanged: (value) => _onNumberChanged(value, category),
                onCheckBill: category.isPostpaid
                    ? () => _checkBill(category, products.valueOrNull ?? [])
                    : null,
                checking: _checkingBill,
                inquiry: _inquiry,
              ),
              const Divider(height: 1),
              Expanded(
                child: products.when(
                  loading: () => const Center(child: CircularProgressIndicator()),
                  error: (e, _) => Center(child: Text(e.toString())),
                  data: (items) => items.isEmpty
                      ? const Center(child: Text('Produk belum tersedia.'))
                      : _ProductGrid(
                          products: items,
                          isPostpaid: category.isPostpaid,
                          inquiry: _inquiry,
                          onTap: (product) => _openCheckout(product, category),
                        ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _InputSection extends StatelessWidget {
  const _InputSection({
    required this.category,
    required this.controller,
    required this.onChanged,
    required this.checking,
    this.detectedOperator,
    this.onCheckBill,
    this.inquiry,
  });

  final Category category;
  final TextEditingController controller;
  final ValueChanged<String> onChanged;
  final String? detectedOperator;
  final VoidCallback? onCheckBill;
  final bool checking;
  final BillInquiry? inquiry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: controller,
            onChanged: onChanged,
            keyboardType: category.inputType == 'text'
                ? TextInputType.text
                : TextInputType.number,
            inputFormatters: category.inputType == 'text'
                ? null
                : [FilteringTextInputFormatter.digitsOnly],
            decoration: InputDecoration(
              labelText: category.inputLabel,
              prefixIcon: const Icon(Icons.dialpad_rounded),
              suffixIcon: detectedOperator != null
                  ? Padding(
                      padding: const EdgeInsets.only(right: 12),
                      child: Center(
                        widthFactor: 1,
                        child: Text(
                          detectedOperator!,
                          style: const TextStyle(
                            color: AppColors.primary,
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    )
                  : null,
            ),
          ),

          if (onCheckBill != null) ...[
            const SizedBox(height: 12),
            FilledButton.tonal(
              onPressed: checking ? null : onCheckBill,
              child: checking
                  ? const SizedBox(
                      height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Cek Tagihan'),
            ),
          ],

          if (inquiry != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.success.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.success.withValues(alpha: 0.3)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(inquiry!.customerName ?? '-',
                      style: const TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 4),
                  if (inquiry!.period != null)
                    Text('Periode: ${inquiry!.period}',
                        style: const TextStyle(fontSize: 12)),
                  Text('Tagihan: ${Formatter.rupiah(inquiry!.billAmount)}',
                      style: const TextStyle(fontSize: 12)),
                  Text('Biaya admin: ${Formatter.rupiah(inquiry!.adminFee)}',
                      style: const TextStyle(fontSize: 12)),
                  const Divider(height: 16),
                  Text('Total: ${Formatter.rupiah(inquiry!.total)}',
                      style: const TextStyle(fontWeight: FontWeight.bold)),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ProductGrid extends ConsumerWidget {
  const _ProductGrid({
    required this.products,
    required this.onTap,
    required this.isPostpaid,
    this.inquiry,
  });

  final List<Product> products;
  final ValueChanged<Product> onTap;
  final bool isPostpaid;
  final BillInquiry? inquiry;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final balance = ref.watch(currentUserProvider)?.balance ?? 0;

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: products.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.45,
      ),
      itemBuilder: (context, index) {
        final product = products[index];
        final price = isPostpaid && inquiry != null ? inquiry!.total : product.total;
        final affordable = balance >= price;

        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: product.isAvailable ? () => onTap(product) : null,
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Theme.of(context).cardTheme.color,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: affordable && product.isAvailable
                    ? Theme.of(context).dividerColor
                    : AppColors.danger.withValues(alpha: 0.4),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      Formatter.rupiah(price),
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                      ),
                    ),
                    if (!product.isAvailable)
                      const Text('Stok kosong',
                          style: TextStyle(fontSize: 11, color: AppColors.danger))
                    else if (!affordable)
                      const Text('Saldo kurang',
                          style: TextStyle(fontSize: 11, color: AppColors.danger)),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
