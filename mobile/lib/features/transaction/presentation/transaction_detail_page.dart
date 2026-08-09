import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/config/app_config.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../../core/widgets/app_snackbar.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/transaction.dart';

final transactionDetailProvider =
    FutureProvider.autoDispose.family<Transaction, int>((ref, id) {
  return ref.watch(transactionRepositoryProvider).detail(id);
});

class TransactionDetailPage extends ConsumerStatefulWidget {
  const TransactionDetailPage({super.key, required this.transactionId});

  final int transactionId;

  @override
  ConsumerState<TransactionDetailPage> createState() => _TransactionDetailPageState();
}

class _TransactionDetailPageState extends ConsumerState<TransactionDetailPage> {
  Timer? _poll;
  int _attempt = 0;

  @override
  void initState() {
    super.initState();
    _startPolling();
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  /// Status transaksi diperbarui via polling ringan sampai final
  /// atau batas percobaan tercapai (push FCM tetap jalan paralel).
  void _startPolling() {
    _poll = Timer.periodic(AppConfig.statusPollInterval, (timer) async {
      final current = ref.read(transactionDetailProvider(widget.transactionId)).valueOrNull;

      if (current != null && current.status.isFinal) {
        timer.cancel();
        return;
      }

      if (++_attempt > AppConfig.statusPollMaxAttempt) {
        timer.cancel();
        return;
      }

      ref.invalidate(transactionDetailProvider(widget.transactionId));
      ref.read(authControllerProvider.notifier).refreshUser();
    });
  }

  /// Invoice diambil lewat Dio (bukan browser) karena endpoint-nya
  /// butuh header Authorization, lalu dibuka dari penyimpanan lokal.
  Future<void> _openInvoice(Transaction trx) async {
    try {
      final directory = await getTemporaryDirectory();
      final path = '${directory.path}/invoice-${trx.invoiceNo}.pdf';

      await ref.read(dioClientProvider).raw.download(
            '/transactions/${trx.id}/invoice',
            path,
          );

      final result = await OpenFilex.open(path);

      if (result.type != ResultType.done && mounted) {
        AppSnackbar.error(context, 'Tidak ada aplikasi pembaca PDF di perangkat ini.');
      }
    } catch (_) {
      if (mounted) AppSnackbar.error(context, 'Gagal mengunduh invoice.');
    }
  }

  Future<void> _share(Transaction trx) async {
    final text = StringBuffer()
      ..writeln('*Bukti Transaksi ${AppConfig.appName}*')
      ..writeln('Invoice: ${trx.invoiceNo}')
      ..writeln('Produk: ${trx.productName}')
      ..writeln('Tujuan: ${trx.customerNo}')
      ..writeln('Total: ${Formatter.rupiah(trx.totalPaid)}')
      ..writeln('Status: ${trx.status.label}');

    if (trx.serialNumber != null) {
      text.writeln('SN/Token: ${trx.serialNumber}');
    }

    text.writeln('Waktu: ${Formatter.dateTime(trx.createdAt)}');

    await Share.share(text.toString());
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(transactionDetailProvider(widget.transactionId));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Detail Transaksi'),
        actions: [
          if (async.valueOrNull != null)
            IconButton(
              onPressed: () => _share(async.value!),
              icon: const Icon(Icons.share_outlined),
              tooltip: 'Bagikan',
            ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (trx) => ListView(
          padding: const EdgeInsets.all(20),
          children: [
            _StatusHeader(transaction: trx),
            const SizedBox(height: 20),

            if (trx.serialNumber != null) ...[
              _SerialCard(serial: trx.serialNumber!),
              const SizedBox(height: 20),
            ],

            _Section(title: 'Informasi Transaksi', children: [
              _DetailRow(label: 'Invoice', value: trx.invoiceNo, copyable: true),
              _DetailRow(label: 'Produk', value: trx.productName),
              _DetailRow(label: 'Nomor Tujuan', value: trx.customerNo, copyable: true),
              if (trx.customerName != null)
                _DetailRow(label: 'Atas Nama', value: trx.customerName!),
              _DetailRow(label: 'Waktu', value: Formatter.dateTime(trx.createdAt)),
              if (trx.completedAt != null)
                _DetailRow(label: 'Selesai', value: Formatter.dateTime(trx.completedAt)),
            ]),
            const SizedBox(height: 16),

            _Section(title: 'Rincian Pembayaran', children: [
              // base_price & profit hanya dikirim API untuk akun reseller.
              if (trx.basePrice != null)
                _DetailRow(label: 'Harga Modal', value: Formatter.rupiah(trx.basePrice!)),
              _DetailRow(label: 'Harga Jual', value: Formatter.rupiah(trx.sellPrice)),
              if (trx.adminFee > 0)
                _DetailRow(label: 'Biaya Admin', value: Formatter.rupiah(trx.adminFee)),
              if (trx.discount > 0)
                _DetailRow(label: 'Diskon', value: '-${Formatter.rupiah(trx.discount)}'),
              _DetailRow(
                label: 'Total Dibayar',
                value: Formatter.rupiah(trx.totalPaid),
                emphasized: true,
              ),
              if (trx.profit != null)
                _DetailRow(
                  label: 'Keuntungan',
                  value: Formatter.rupiah(trx.profit!),
                  valueColor: AppColors.success,
                ),
            ]),

            if (trx.message != null) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: trx.status.color.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(trx.message!, style: const TextStyle(fontSize: 13)),
              ),
            ],

            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: () => _openInvoice(trx),
              icon: const Icon(Icons.picture_as_pdf_outlined),
              label: const Text('Unduh Invoice PDF'),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () => _share(trx),
              icon: const Icon(Icons.share_outlined),
              label: const Text('Bagikan Bukti Transaksi'),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusHeader extends StatelessWidget {
  const _StatusHeader({required this.transaction});

  final Transaction transaction;

  @override
  Widget build(BuildContext context) {
    final status = transaction.status;

    return Column(
      children: [
        Container(
          height: 84,
          width: 84,
          decoration: BoxDecoration(
            color: status.color.withValues(alpha: 0.12),
            shape: BoxShape.circle,
          ),
          child: status.isFinal
              ? Icon(status.icon, size: 44, color: status.color)
              : Padding(
                  padding: const EdgeInsets.all(24),
                  child: CircularProgressIndicator(color: status.color, strokeWidth: 3),
                ),
        ),
        const SizedBox(height: 14),
        Text(
          status.label,
          style: Theme.of(context)
              .textTheme
              .titleLarge
              ?.copyWith(fontWeight: FontWeight.bold, color: status.color),
        ),
        const SizedBox(height: 4),
        Text(
          Formatter.rupiah(transaction.totalPaid),
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.bold,
              ),
        ),
        if (!status.isFinal)
          const Padding(
            padding: EdgeInsets.only(top: 8),
            child: Text(
              'Transaksi sedang diproses. Halaman ini diperbarui otomatis.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: AppColors.textMutedLight),
            ),
          ),
      ],
    );
  }
}

class _SerialCard extends StatelessWidget {
  const _SerialCard({required this.serial});

  final String serial;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.3)),
      ),
      child: Column(
        children: [
          const Text('SERIAL NUMBER / TOKEN',
              style: TextStyle(fontSize: 11, color: AppColors.textMutedLight, letterSpacing: 1)),
          const SizedBox(height: 8),
          SelectableText(
            serial,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: 'monospace',
              fontSize: 18,
              fontWeight: FontWeight.bold,
              letterSpacing: 1.5,
            ),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: () {
              Clipboard.setData(ClipboardData(text: serial));
              AppSnackbar.success(context, 'Token disalin.');
            },
            icon: const Icon(Icons.copy_rounded, size: 18),
            label: const Text('Salin'),
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
        const SizedBox(height: 10),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(children: children),
          ),
        ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    required this.value,
    this.copyable = false,
    this.emphasized = false,
    this.valueColor,
  });

  final String label;
  final String value;
  final bool copyable;
  final bool emphasized;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(label,
                style: const TextStyle(fontSize: 13, color: AppColors.textMutedLight)),
          ),
          Expanded(
            flex: 3,
            child: GestureDetector(
              onTap: copyable
                  ? () {
                      Clipboard.setData(ClipboardData(text: value));
                      AppSnackbar.success(context, '$label disalin.');
                    }
                  : null,
              child: Text(
                value,
                textAlign: TextAlign.right,
                style: TextStyle(
                  fontSize: emphasized ? 15 : 13,
                  fontWeight: emphasized ? FontWeight.bold : FontWeight.w600,
                  color: valueColor,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
