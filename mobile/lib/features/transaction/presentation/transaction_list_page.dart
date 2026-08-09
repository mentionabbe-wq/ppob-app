import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../domain/transaction.dart';

final _statusFilterProvider = StateProvider.autoDispose<String?>((ref) => null);

final _transactionsProvider =
    FutureProvider.autoDispose<List<Transaction>>((ref) {
  return ref.watch(transactionRepositoryProvider).list(
        status: ref.watch(_statusFilterProvider),
      );
});

class TransactionListPage extends ConsumerWidget {
  const TransactionListPage({super.key});

  static const _filters = {
    null: 'Semua',
    'pending': 'Diproses',
    'success': 'Berhasil',
    'failed': 'Gagal',
    'refunded': 'Refund',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final selected = ref.watch(_statusFilterProvider);
    final transactions = ref.watch(_transactionsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Riwayat Transaksi')),
      body: Column(
        children: [
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              children: _filters.entries.map((entry) {
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(entry.value),
                    selected: selected == entry.key,
                    onSelected: (_) =>
                        ref.read(_statusFilterProvider.notifier).state = entry.key,
                  ),
                );
              }).toList(),
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async => ref.refresh(_transactionsProvider.future),
              child: transactions.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => ListView(
                  children: [
                    const SizedBox(height: 120),
                    Center(child: Text(e.toString(), textAlign: TextAlign.center)),
                  ],
                ),
                data: (items) => items.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 120),
                          Icon(Icons.receipt_long_outlined,
                              size: 64, color: AppColors.textMutedLight),
                          SizedBox(height: 12),
                          Center(child: Text('Belum ada transaksi.')),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, index) {
                          final trx = items[index];

                          return Card(
                            child: ListTile(
                              onTap: () => context.push('/transactions/${trx.id}'),
                              leading: CircleAvatar(
                                backgroundColor: trx.status.color.withValues(alpha: 0.12),
                                child: Icon(trx.status.icon,
                                    color: trx.status.color, size: 20),
                              ),
                              title: Text(trx.productName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 14)),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(trx.customerNo, style: const TextStyle(fontSize: 12)),
                                  Text(
                                    '${trx.invoiceNo} · ${Formatter.relative(trx.createdAt)}',
                                    style: const TextStyle(fontSize: 11),
                                  ),
                                ],
                              ),
                              isThreeLine: true,
                              trailing: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(Formatter.rupiah(trx.totalPaid),
                                      style: const TextStyle(
                                          fontWeight: FontWeight.bold, fontSize: 13)),
                                  const SizedBox(height: 2),
                                  Text(trx.status.label,
                                      style: TextStyle(fontSize: 11, color: trx.status.color)),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
