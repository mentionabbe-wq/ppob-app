import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../../core/widgets/app_snackbar.dart';
import '../domain/deposit.dart';
import 'deposit_page.dart';

final depositDetailProvider =
    FutureProvider.autoDispose.family<Deposit, int>((ref, id) {
  return ref.watch(depositRepositoryProvider).detail(id);
});

class DepositDetailPage extends ConsumerStatefulWidget {
  const DepositDetailPage({super.key, required this.depositId});

  final int depositId;

  @override
  ConsumerState<DepositDetailPage> createState() => _DepositDetailPageState();
}

class _DepositDetailPageState extends ConsumerState<DepositDetailPage> {
  bool _uploading = false;

  Future<void> _uploadProof(Deposit deposit) async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 80,
      maxWidth: 1600,
    );

    if (picked == null) return;

    setState(() => _uploading = true);

    try {
      await ref.read(depositRepositoryProvider).uploadProof(deposit.id, picked.path);

      if (!mounted) return;

      ref.invalidate(depositDetailProvider(widget.depositId));
      ref.invalidate(depositHistoryProvider);
      AppSnackbar.success(context, 'Bukti transfer terkirim, menunggu verifikasi.');
    } on ApiException catch (e) {
      if (mounted) AppSnackbar.error(context, e.message);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(depositDetailProvider(widget.depositId));

    return Scaffold(
      appBar: AppBar(title: const Text('Instruksi Pembayaran')),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (deposit) => RefreshIndicator(
          onRefresh: () async => ref.refresh(depositDetailProvider(widget.depositId).future),
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              // Status
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: deposit.status.color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    Icon(Icons.info_outline_rounded, color: deposit.status.color),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(deposit.status.label,
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: deposit.status.color,
                              )),
                          if (deposit.rejectReason != null)
                            Text(deposit.rejectReason!, style: const TextStyle(fontSize: 12)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Nominal yang harus ditransfer
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      const Text('Total yang harus dibayar',
                          style: TextStyle(fontSize: 12, color: AppColors.textMutedLight)),
                      const SizedBox(height: 6),
                      Text(
                        Formatter.rupiah(deposit.totalAmount),
                        style: const TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primary,
                        ),
                      ),
                      if (deposit.uniqueCode > 0) ...[
                        const SizedBox(height: 6),
                        Text(
                          'Termasuk kode unik ${deposit.uniqueCode}. '
                          'Transfer TEPAT sampai 3 digit terakhir.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 12, color: AppColors.warning),
                        ),
                      ],
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: () {
                          Clipboard.setData(
                            ClipboardData(text: deposit.totalAmount.toStringAsFixed(0)),
                          );
                          AppSnackbar.success(context, 'Nominal disalin.');
                        },
                        icon: const Icon(Icons.copy_rounded, size: 18),
                        label: const Text('Salin Nominal'),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              if (deposit.vaNumber != null) _VaCard(vaNumber: deposit.vaNumber!),

              if (deposit.method == 'bank_transfer') _BankInstruction(deposit: deposit),

              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      _row('Kode Deposit', deposit.code),
                      _row('Metode', deposit.method.replaceAll('_', ' ').toUpperCase()),
                      if (deposit.channel != null)
                        _row('Kanal', deposit.channel!.toUpperCase()),
                      _row('Berlaku sampai', Formatter.dateTime(deposit.expiredAt)),
                    ],
                  ),
                ),
              ),

              if (deposit.needsManualProof && !deposit.status.isFinal) ...[
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: _uploading ? null : () => _uploadProof(deposit),
                  icon: _uploading
                      ? const SizedBox(
                          height: 18, width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.upload_file_rounded),
                  label: Text(deposit.proofUrl == null
                      ? 'Unggah Bukti Transfer'
                      : 'Ganti Bukti Transfer'),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Unggah bukti transfer opsional — mempercepat verifikasi admin.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 12, color: AppColors.textMutedLight),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _row(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label,
                style: const TextStyle(fontSize: 13, color: AppColors.textMutedLight)),
            Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
          ],
        ),
      );
}

class _VaCard extends StatelessWidget {
  const _VaCard({required this.vaNumber});

  final String vaNumber;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            const Text('Nomor Virtual Account',
                style: TextStyle(fontSize: 12, color: AppColors.textMutedLight)),
            const SizedBox(height: 8),
            SelectableText(
              vaNumber,
              style: const TextStyle(
                fontFamily: 'monospace',
                fontSize: 22,
                fontWeight: FontWeight.bold,
                letterSpacing: 1.5,
              ),
            ),
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: () {
                Clipboard.setData(ClipboardData(text: vaNumber));
                AppSnackbar.success(context, 'Nomor VA disalin.');
              },
              icon: const Icon(Icons.copy_rounded, size: 18),
              label: const Text('Salin Nomor VA'),
            ),
          ],
        ),
      ),
    );
  }
}

class _BankInstruction extends StatelessWidget {
  const _BankInstruction({required this.deposit});

  final Deposit deposit;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Cara Pembayaran', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            ...[
              'Transfer ke rekening tujuan sesuai bank yang dipilih.',
              'Transfer TEPAT sampai 3 digit terakhir agar terverifikasi otomatis.',
              'Unggah bukti transfer (opsional) untuk mempercepat proses.',
              'Saldo masuk setelah admin memverifikasi, maksimal 1x24 jam.',
            ].asMap().entries.map(
                  (entry) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        CircleAvatar(
                          radius: 11,
                          backgroundColor: AppColors.primary.withValues(alpha: 0.12),
                          child: Text('${entry.key + 1}',
                              style: const TextStyle(
                                  fontSize: 11,
                                  color: AppColors.primary,
                                  fontWeight: FontWeight.bold)),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(entry.value, style: const TextStyle(fontSize: 13)),
                        ),
                      ],
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}
