import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../../core/widgets/app_snackbar.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../transaction/data/transaction_repository.dart';
import '../domain/product.dart';

/// Lembar konfirmasi pembelian: rincian harga, promo, PIN, lalu bayar.
class CheckoutSheet extends ConsumerStatefulWidget {
  const CheckoutSheet({
    super.key,
    required this.product,
    required this.customerNo,
    this.inquiry,
  });

  final Product product;
  final String customerNo;
  final BillInquiry? inquiry;

  @override
  ConsumerState<CheckoutSheet> createState() => _CheckoutSheetState();
}

class _CheckoutSheetState extends ConsumerState<CheckoutSheet> {
  final _promo = TextEditingController();
  final _pin = TextEditingController();

  bool _loading = false;

  /// Dibuat sekali saat sheet dibuka: menekan "Bayar" dua kali karena
  /// jaringan lambat tidak akan menghasilkan dua transaksi.
  late final String _refId = TransactionRepository.generateRefId();

  @override
  void dispose() {
    _promo.dispose();
    _pin.dispose();
    super.dispose();
  }

  double get _total => widget.inquiry?.total ?? widget.product.total;

  Future<void> _pay() async {
    final user = ref.read(currentUserProvider);

    if (user == null) return;

    if (user.balance < _total) {
      AppSnackbar.error(context, 'Saldo tidak mencukupi. Silakan isi saldo dahulu.');
      return;
    }

    if (user.hasPin && _pin.text.length != AppConfig.pinLength) {
      AppSnackbar.info(context, 'Masukkan PIN transaksi Anda.');
      return;
    }

    setState(() => _loading = true);

    try {
      final transaction = await ref.read(transactionRepositoryProvider).purchase(
            productId: widget.product.id,
            customerNo: widget.customerNo,
            promoCode: _promo.text.trim(),
            pin: _pin.text,
            refId: _refId,
          );

      await ref.read(authControllerProvider.notifier).refreshUser();

      if (!mounted) return;

      Navigator.of(context).pop();
      context.push('/transactions/${transaction.id}');
    } on ApiException catch (e) {
      if (!mounted) return;

      AppSnackbar.error(context, e.fieldError('pin') ?? e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(currentUserProvider);
    final insufficient = (user?.balance ?? 0) < _total;

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 8,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 24,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Konfirmasi Pembelian',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    )),
            const SizedBox(height: 20),

            _Row(label: 'Produk', value: widget.product.name),
            _Row(label: 'Nomor Tujuan', value: widget.customerNo),
            if (widget.inquiry?.customerName != null)
              _Row(label: 'Atas Nama', value: widget.inquiry!.customerName!),

            const Divider(height: 28),

            _Row(
              label: widget.inquiry != null ? 'Tagihan' : 'Harga',
              value: Formatter.rupiah(widget.inquiry?.billAmount ?? widget.product.price),
            ),
            if ((widget.inquiry?.adminFee ?? widget.product.adminFee) > 0)
              _Row(
                label: 'Biaya Admin',
                value: Formatter.rupiah(widget.inquiry?.adminFee ?? widget.product.adminFee),
              ),

            const SizedBox(height: 8),
            TextField(
              controller: _promo,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(
                labelText: 'Kode Promo (opsional)',
                prefixIcon: Icon(Icons.local_offer_outlined),
              ),
            ),

            if (user?.hasPin ?? false) ...[
              const SizedBox(height: 12),
              TextField(
                controller: _pin,
                obscureText: true,
                keyboardType: TextInputType.number,
                maxLength: AppConfig.pinLength,
                decoration: const InputDecoration(
                  labelText: 'PIN Transaksi',
                  counterText: '',
                  prefixIcon: Icon(Icons.lock_outline_rounded),
                ),
              ),
            ],

            const Divider(height: 28),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Total Bayar', style: TextStyle(fontWeight: FontWeight.bold)),
                Text(
                  Formatter.rupiah(_total),
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 20,
                    color: AppColors.primary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              'Saldo Anda: ${Formatter.rupiah(user?.balance ?? 0)}',
              style: TextStyle(
                fontSize: 12,
                color: insufficient ? AppColors.danger : AppColors.textMutedLight,
              ),
            ),
            const SizedBox(height: 20),

            if (insufficient)
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(context).pop();
                  context.push('/deposit');
                },
                icon: const Icon(Icons.add_rounded),
                label: const Text('Isi Saldo'),
              )
            else
              FilledButton(
                onPressed: _loading ? null : _pay,
                child: _loading
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                      )
                    : const Text('Bayar Sekarang'),
              ),
          ],
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(label,
                style: const TextStyle(color: AppColors.textMutedLight, fontSize: 13)),
          ),
          Expanded(
            flex: 3,
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
