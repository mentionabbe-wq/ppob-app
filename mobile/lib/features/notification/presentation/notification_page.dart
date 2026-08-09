import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../home/presentation/home_shell.dart';
import '../data/notification_repository.dart';

final notificationsProvider = FutureProvider.autoDispose<List<AppNotification>>(
  (ref) => ref.watch(notificationRepositoryProvider).list(),
);

class NotificationPage extends ConsumerWidget {
  const NotificationPage({super.key});

  static const _icons = {
    'transaction': Icons.receipt_long_rounded,
    'deposit': Icons.account_balance_wallet_rounded,
    'promo': Icons.local_offer_rounded,
    'system': Icons.campaign_rounded,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifications = ref.watch(notificationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifikasi'),
        actions: [
          TextButton(
            onPressed: () async {
              await ref.read(notificationRepositoryProvider).markAllAsRead();
              ref.invalidate(notificationsProvider);
              ref.invalidate(unreadCountProvider);
            },
            child: const Text('Tandai dibaca'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(unreadCountProvider);
          return ref.refresh(notificationsProvider.future);
        },
        child: notifications.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text(e.toString())),
          data: (items) => items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  Icon(Icons.notifications_off_outlined,
                      size: 64, color: AppColors.textMutedLight),
                  SizedBox(height: 12),
                  Center(child: Text('Belum ada notifikasi.')),
                ])
              : ListView.separated(
                  itemCount: items.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final notification = items[index];

                    return ListTile(
                      tileColor: notification.isRead
                          ? null
                          : AppColors.primary.withValues(alpha: 0.04),
                      leading: CircleAvatar(
                        backgroundColor: AppColors.primary.withValues(alpha: 0.12),
                        child: Icon(
                          _icons[notification.type] ?? Icons.notifications_rounded,
                          color: AppColors.primary,
                          size: 20,
                        ),
                      ),
                      title: Text(
                        notification.title,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight:
                              notification.isRead ? FontWeight.normal : FontWeight.bold,
                        ),
                      ),
                      subtitle: Text(
                        '${notification.body}\n${Formatter.relative(notification.createdAt)}',
                        style: const TextStyle(fontSize: 12),
                      ),
                      isThreeLine: true,
                      onTap: () async {
                        if (!notification.isRead) {
                          await ref
                              .read(notificationRepositoryProvider)
                              .markAsRead(notification.id);
                          ref.invalidate(notificationsProvider);
                          ref.invalidate(unreadCountProvider);
                        }

                        // Notifikasi transaksi membuka detailnya langsung.
                        final transactionId = notification.data?['transaction_id'];

                        if (transactionId != null && context.mounted) {
                          context.push('/transactions/$transactionId');
                        }
                      },
                    );
                  },
                ),
        ),
      ),
    );
  }
}
