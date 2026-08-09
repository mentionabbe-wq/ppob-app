import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../../core/widgets/app_snackbar.dart';
import '../domain/deposit.dart';

final depositMethodsProvider = FutureProvider.autoDispose<List<DepositMethod>>(
  (ref) => ref.watch(depositRepositoryProvider).methods(),
);

final depositHistoryProvider = FutureProvider.autoDispose<List<Deposit>>(
  (ref) => ref.watch(depositRepositoryProvider).list(),
);

class DepositPage extends ConsumerStatefulWidget {
  const DepositPage({super.key});

  @override
  ConsumerState<DepositPage> createState() => _DepositPageState();
}

class _DepositPageState extends ConsumerState<DepositPage> {
  final _amount = TextEditingController();

  static const _presets = [50000, 100000, 200000, 500000, 1000000, 2000000];

  DepositMethod? _method;
  DepositChannel? _channel;
  bool _loading = false;

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_amount.text.replaceAll(RegExp(r'\D'), '')) ?? 0;

    if (amount <= 0) {
      AppSnackbar.info(context, 'Masukkan nominal deposit.');
      return;
    }

    if (_method == null) {
      AppSnackbar.info(context, 'Pilih metode pembayaran.');
      return;
    }

    if (_method!.channels.isNotEmpty && _channel == null) {
      AppSnackbar.info(context, 'Pilih kanal pembayaran.');
      return;
    }

    setState(() => _loading = true);

    try {
      final deposit = await ref.read(depositRepositoryProvider).create(
            amount: amount,
            method: _method!.code,
            channel: _channel?.code,
          );

      if (!mounted) return;

      ref.invalidate(depositHistoryProvider);
      context.pushReplacement('/deposit/${deposit.id}');
    } on ApiException catch (e) {
      if (mounted) AppSnackbar.error(context, e.fieldError('amount') ?? e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final methods = ref.watch(depositMethodsProvider);
    final history = ref.watch(depositHistoryProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Isi Saldo')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text('Nominal Deposit', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),

          TextField(
            controller: _amount,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: const InputDecoration(
              prefixText: 'Rp ',
              hintText: '100000',
            ),
          ),
          const SizedBox(height: 12),

          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _presets.map((preset) {
              return ActionChip(
                label: Text(Formatter.rupiahCompact(preset)),
                onPressed: () => setState(() => _amount.text = '$preset'),
              );
            }).toList(),
          ),
          const SizedBox(height: 24),

          const Text('Metode Pembayaran', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),

          methods.when(
            loading: () => const Center(child: Padding(
              padding: EdgeInsets.all(24),
              child: CircularProgressIndicator(),
            )),
            error: (e, _) => Text(e.toString()),
            data: (items) => Column(
              children: items.map((method) {
                final selected = _method?.code == method.code;

                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: Column(
                    children: [
                      RadioListTile<String>(
                        value: method.code,
                        groupValue: _method?.code,
                        onChanged: (_) => setState(() {
                          _method = method;
                          _channel = null;
                        }),
                        title: Text(method.name),
                        subtitle: Text(method.description,
                            style: const TextStyle(fontSize: 12)),
                      ),

                      // Kanal (bank/e-wallet) muncul setelah metode dipilih.
                      if (selected && method.channels.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                          child: Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: method.channels.map((channel) {
                              return ChoiceChip(
                                label: Text(channel.name),
                                selected: _channel?.code == channel.code,
                                onSelected: (_) => setState(() => _channel = channel),
                              );
                            }).toList(),
                          ),
                        ),
                    ],
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 20),

          FilledButton(
            onPressed: _loading ? null : _submit,
            child: _loading
                ? const SizedBox(
                    height: 22,
                    width: 22,
                    child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                  )
                : const Text('Lanjutkan Pembayaran'),
          ),
          const SizedBox(height: 28),

          const Text('Riwayat Deposit', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),

          history.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Text(e.toString()),
            data: (items) => items.isEmpty
                ? const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(child: Text('Belum ada deposit.')),
                  )
                : Column(
                    children: items.map((deposit) {
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          onTap: () => context.push('/deposit/${deposit.id}'),
                          leading: CircleAvatar(
                            backgroundColor: deposit.status.color.withValues(alpha: 0.12),
                            child: Icon(Icons.account_balance_wallet_rounded,
                                color: deposit.status.color, size: 20),
                          ),
                          title: Text(Formatter.rupiah(deposit.amount),
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                          subtitle: Text(
                            '${deposit.code} · ${Formatter.relative(deposit.createdAt)}',
                            style: const TextStyle(fontSize: 12),
                          ),
                          trailing: Text(
                            deposit.status.label,
                            style: TextStyle(fontSize: 11, color: deposit.status.color),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
          ),
        ],
      ),
    );
  }
}
