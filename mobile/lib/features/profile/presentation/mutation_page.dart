import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/profile_repository.dart';

final _mutationsProvider = FutureProvider.autoDispose<List<WalletMutation>>(
  (ref) => ref.watch(profileRepositoryProvider).mutations(),
);

class MutationPage extends ConsumerWidget {
  const MutationPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mutations = ref.watch(_mutationsProvider);
    final balance = ref.watch(currentUserProvider)?.balance ?? 0;

    return Scaffold(
      appBar: AppBar(title: const Text('Mutasi Saldo')),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: const BoxDecoration(gradient: AppColors.gradient),
            child: Column(
              children: [
                const Text('Saldo Saat Ini',
                    style: TextStyle(color: Colors.white70, fontSize: 12)),
                const SizedBox(height: 4),
                Text(
                  Formatter.rupiah(balance),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async => ref.refresh(_mutationsProvider.future),
              child: mutations.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Center(child: Text(e.toString())),
                data: (items) => items.isEmpty
                    ? ListView(children: const [
                        SizedBox(height: 100),
                        Center(child: Text('Belum ada mutasi saldo.')),
                      ])
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (context, index) {
                          final mutation = items[index];

                          return ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: CircleAvatar(
                              backgroundColor: (mutation.isCredit
                                      ? AppColors.success
                                      : AppColors.danger)
                                  .withValues(alpha: 0.12),
                              child: Icon(
                                mutation.isCredit
                                    ? Icons.arrow_downward_rounded
                                    : Icons.arrow_upward_rounded,
                                color: mutation.isCredit
                                    ? AppColors.success
                                    : AppColors.danger,
                                size: 20,
                              ),
                            ),
                            title: Text(mutation.typeLabel,
                                style: const TextStyle(fontSize: 14)),
                            subtitle: Text(
                              '${mutation.description}\n${Formatter.dateTime(mutation.createdAt)}',
                              style: const TextStyle(fontSize: 12),
                            ),
                            isThreeLine: true,
                            trailing: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(
                                  '${mutation.isCredit ? '+' : ''}${Formatter.rupiah(mutation.amount)}',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 13,
                                    color: mutation.isCredit
                                        ? AppColors.success
                                        : AppColors.danger,
                                  ),
                                ),
                                Text(
                                  Formatter.rupiah(mutation.balanceAfter),
                                  style: const TextStyle(
                                      fontSize: 11, color: AppColors.textMutedLight),
                                ),
                              ],
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
