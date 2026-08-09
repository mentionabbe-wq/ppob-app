import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../notification/presentation/notification_page.dart';
import '../../profile/presentation/profile_page.dart';
import '../../transaction/presentation/transaction_list_page.dart';
import 'home_page.dart';

/// Jumlah notifikasi belum dibaca untuk badge di navigasi bawah.
final unreadCountProvider = FutureProvider.autoDispose<int>(
  (ref) => ref.watch(notificationRepositoryProvider).unreadCount(),
);

class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _index = 0;

  static const _pages = [
    HomePage(),
    TransactionListPage(),
    NotificationPage(),
    ProfilePage(),
  ];

  @override
  Widget build(BuildContext context) {
    final unread = ref.watch(unreadCountProvider).valueOrNull ?? 0;

    return Scaffold(
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _index,
        onTap: (value) {
          setState(() => _index = value);

          // Segarkan badge saat pengguna membuka tab notifikasi.
          if (value == 2) ref.invalidate(unreadCountProvider);
        },
        items: [
          const BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),
            activeIcon: Icon(Icons.home_rounded),
            label: 'Beranda',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.receipt_long_outlined),
            activeIcon: Icon(Icons.receipt_long_rounded),
            label: 'Transaksi',
          ),
          BottomNavigationBarItem(
            icon: Badge(
              isLabelVisible: unread > 0,
              label: Text('$unread'),
              child: const Icon(Icons.notifications_outlined),
            ),
            activeIcon: const Icon(Icons.notifications_rounded),
            label: 'Notifikasi',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.person_outline_rounded),
            activeIcon: Icon(Icons.person_rounded),
            label: 'Profil',
          ),
        ],
      ),
    );
  }
}
