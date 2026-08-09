import 'package:carousel_slider/carousel_slider.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../catalog/domain/product.dart';
import '../../transaction/domain/transaction.dart';
import '../data/dashboard_repository.dart';

final dashboardRepositoryProvider = Provider<DashboardRepository>(
  (ref) => DashboardRepository(ref.watch(dioClientProvider)),
);

final dashboardProvider = FutureProvider.autoDispose<DashboardData>((ref) async {
  final data = await ref.watch(dashboardRepositoryProvider).load();

  // Sinkronkan saldo di AuthController agar tampil konsisten di semua tab.
  ref.read(authControllerProvider.notifier).refreshUser();

  return data;
});

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboard = ref.watch(dashboardProvider);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(dashboardProvider.future),
        child: dashboard.when(
          loading: () => const _HomeSkeleton(),
          error: (error, _) => _ErrorView(
            message: error.toString(),
            onRetry: () => ref.invalidate(dashboardProvider),
          ),
          data: (data) => CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: _BalanceHeader(data: data)),
              SliverToBoxAdapter(child: _MenuGrid(menus: data.menus)),
              if (data.banners.isNotEmpty)
                SliverToBoxAdapter(child: _BannerCarousel(banners: data.banners)),
              if (data.favorites.isNotEmpty)
                SliverToBoxAdapter(child: _FavoriteRow(favorites: data.favorites)),
              if (data.promos.isNotEmpty)
                SliverToBoxAdapter(child: _PromoList(promos: data.promos)),
              SliverToBoxAdapter(child: _RecentTransactions(items: data.recentTransactions)),
              const SliverToBoxAdapter(child: SizedBox(height: 24)),
            ],
          ),
        ),
      ),
    );
  }
}

class _BalanceHeader extends ConsumerWidget {
  const _BalanceHeader({required this.data});

  final DashboardData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);

    return Container(
      padding: EdgeInsets.fromLTRB(20, MediaQuery.paddingOf(context).top + 16, 20, 28),
      decoration: const BoxDecoration(
        gradient: AppColors.gradient,
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: Colors.white24,
                backgroundImage: user?.avatarUrl != null
                    ? CachedNetworkImageProvider(user!.avatarUrl!)
                    : null,
                child: user?.avatarUrl == null
                    ? Text(
                        (user?.name.characters.first ?? '?').toUpperCase(),
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Selamat datang,', style: TextStyle(color: Colors.white70, fontSize: 12)),
                    Text(
                      user?.name ?? '-',
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed: () => context.push('/settings'),
                icon: const Icon(Icons.settings_outlined, color: Colors.white),
              ),
            ],
          ),
          const SizedBox(height: 20),

          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: Colors.white24),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Saldo Anda', style: TextStyle(color: Colors.white70, fontSize: 12)),
                const SizedBox(height: 4),
                Text(
                  Formatter.rupiah(data.balance),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: () => context.push('/deposit'),
                        style: FilledButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: AppColors.primary,
                          minimumSize: const Size.fromHeight(44),
                        ),
                        icon: const Icon(Icons.add_rounded, size: 20),
                        label: const Text('Isi Saldo'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => context.push('/mutations'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.white,
                          side: const BorderSide(color: Colors.white54),
                          minimumSize: const Size.fromHeight(44),
                        ),
                        icon: const Icon(Icons.receipt_long_outlined, size: 20),
                        label: const Text('Mutasi'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          Row(
            children: [
              _StatChip(label: 'Total Transaksi', value: '${data.totalTransaction}'),
              const SizedBox(width: 10),
              _StatChip(label: 'Berhasil', value: '${data.successTransaction}'),
              const SizedBox(width: 10),
              _StatChip(label: 'Diproses', value: '${data.pendingTransaction}'),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Text(value,
                style: const TextStyle(
                    color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 2),
            Text(label,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white70, fontSize: 10)),
          ],
        ),
      ),
    );
  }
}

class _MenuGrid extends StatelessWidget {
  const _MenuGrid({required this.menus});

  final List<Category> menus;

  static const _icons = {
    'pulsa': Icons.smartphone_rounded,
    'paket-data': Icons.wifi_rounded,
    'token-listrik': Icons.bolt_rounded,
    'tagihan-listrik': Icons.receipt_rounded,
    'bpjs': Icons.local_hospital_rounded,
    'pdam': Icons.water_drop_rounded,
    'telkom': Icons.phone_in_talk_rounded,
    'tv-kabel': Icons.tv_rounded,
    'e-wallet': Icons.account_balance_wallet_rounded,
    'voucher-game': Icons.sports_esports_rounded,
    'internet': Icons.public_rounded,
    'multi-finance': Icons.account_balance_rounded,
  };

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: menus.length,
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 4,
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: 0.85,
        ),
        itemBuilder: (context, index) {
          final menu = menus[index];
          final color = menu.color != null
              ? Color(int.parse(menu.color!.replaceFirst('#', '0xFF')))
              : AppColors.primary;

          return InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () => context.push('/category/${menu.slug}', extra: menu),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  height: 46,
                  width: 46,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(_icons[menu.slug] ?? Icons.apps_rounded, color: color, size: 24),
                ),
                const SizedBox(height: 6),
                Text(
                  menu.name,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 11, height: 1.2),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _BannerCarousel extends StatelessWidget {
  const _BannerCarousel({required this.banners});

  final List<Banner> banners;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: CarouselSlider(
        options: CarouselOptions(
          height: 140,
          autoPlay: banners.length > 1,
          viewportFraction: 0.88,
          enlargeCenterPage: true,
          autoPlayInterval: const Duration(seconds: 5),
        ),
        items: banners.map((banner) {
          return ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: CachedNetworkImage(
              imageUrl: banner.imageUrl,
              width: double.infinity,
              fit: BoxFit.cover,
              placeholder: (_, __) => Container(color: Colors.black12),
              errorWidget: (_, __, ___) => Container(
                color: Colors.black12,
                alignment: Alignment.center,
                child: Text(banner.title),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _FavoriteRow extends StatelessWidget {
  const _FavoriteRow({required this.favorites});

  final List<Category> favorites;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Sering Dipakai', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),
          SizedBox(
            height: 40,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: favorites.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) => ActionChip(
                label: Text(favorites[index].name),
                onPressed: () => context.push('/category/${favorites[index].slug}'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PromoList extends StatelessWidget {
  const _PromoList({required this.promos});

  final List<Promo> promos;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Promo Berjalan', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),
          ...promos.take(3).map(
                (promo) => Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: AppColors.warning,
                      child: Icon(Icons.local_offer_rounded, color: Colors.white, size: 20),
                    ),
                    title: Text(promo.title, style: const TextStyle(fontSize: 14)),
                    subtitle: Text(
                      'Kode: ${promo.code}',
                      style: const TextStyle(fontSize: 12),
                    ),
                    trailing: Text(
                      promo.discountType == 'percent'
                          ? '${promo.discountValue.toStringAsFixed(0)}%'
                          : Formatter.rupiahCompact(promo.discountValue),
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        color: AppColors.success,
                      ),
                    ),
                  ),
                ),
              ),
        ],
      ),
    );
  }
}

class _RecentTransactions extends StatelessWidget {
  const _RecentTransactions({required this.items});

  final List<Transaction> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(32),
        child: Center(
          child: Text(
            'Belum ada transaksi.\nMulai dengan membeli pulsa atau token listrik.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.textMutedLight),
          ),
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Transaksi Terakhir', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),
          ...items.map(
            (trx) => Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                onTap: () => context.push('/transactions/${trx.id}'),
                leading: CircleAvatar(
                  backgroundColor: trx.status.color.withValues(alpha: 0.12),
                  child: Icon(trx.status.icon, color: trx.status.color, size: 20),
                ),
                title: Text(trx.productName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 14)),
                subtitle: Text(
                  '${Formatter.maskPhone(trx.customerNo)} · ${Formatter.relative(trx.createdAt)}',
                  style: const TextStyle(fontSize: 12),
                ),
                trailing: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(Formatter.rupiah(trx.totalPaid),
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    const SizedBox(height: 2),
                    Text(trx.status.label,
                        style: TextStyle(fontSize: 11, color: trx.status.color)),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(height: 210, decoration: _box(context)),
        const SizedBox(height: 16),
        Container(height: 180, decoration: _box(context)),
        const SizedBox(height: 16),
        Container(height: 140, decoration: _box(context)),
      ],
    );
  }

  BoxDecoration _box(BuildContext context) => BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
      );
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(32),
      children: [
        const SizedBox(height: 80),
        const Icon(Icons.cloud_off_rounded, size: 64, color: AppColors.textMutedLight),
        const SizedBox(height: 16),
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 24),
        FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
      ],
    );
  }
}
