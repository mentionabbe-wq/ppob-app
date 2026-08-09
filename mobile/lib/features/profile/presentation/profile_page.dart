import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatter.dart';
import '../../../core/widgets/app_snackbar.dart';
import '../../auth/presentation/auth_controller.dart';

class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);

    if (user == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Profil')),
      body: ListView(
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            color: Theme.of(context).cardTheme.color,
            child: Row(
              children: [
                CircleAvatar(
                  radius: 32,
                  backgroundColor: AppColors.primary.withValues(alpha: 0.12),
                  backgroundImage: user.avatarUrl != null
                      ? CachedNetworkImageProvider(user.avatarUrl!)
                      : null,
                  child: user.avatarUrl == null
                      ? Text(
                          user.name.characters.first.toUpperCase(),
                          style: const TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                            color: AppColors.primary,
                          ),
                        )
                      : null,
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user.name,
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      Text(user.email, style: const TextStyle(fontSize: 13)),
                      if (user.phone != null)
                        Text(user.phone!, style: const TextStyle(fontSize: 13)),
                      const SizedBox(height: 6),
                      Text(
                        'Saldo ${Formatter.rupiah(user.balance)}',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          if (user.referralCode != null)
            Container(
              margin: const EdgeInsets.all(16),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: AppColors.gradient,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Kode Referral Anda',
                            style: TextStyle(color: Colors.white70, fontSize: 12)),
                        const SizedBox(height: 4),
                        Text(
                          user.referralCode!,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 2,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () {
                      Clipboard.setData(ClipboardData(text: user.referralCode!));
                      AppSnackbar.success(context, 'Kode referral disalin.');
                    },
                    icon: const Icon(Icons.copy_rounded, color: Colors.white),
                  ),
                ],
              ),
            ),

          _Group(title: 'Akun', items: [
            _Item(
              icon: Icons.person_outline_rounded,
              title: 'Edit Profil',
              onTap: () => context.push('/settings'),
            ),
            _Item(
              icon: Icons.lock_outline_rounded,
              title: 'Ganti Kata Sandi',
              onTap: () => context.push('/settings'),
            ),
            _Item(
              icon: Icons.pin_outlined,
              title: user.hasPin ? 'Ubah PIN Transaksi' : 'Buat PIN Transaksi',
              onTap: () => context.push('/settings'),
            ),
            _Item(
              icon: Icons.account_balance_outlined,
              title: 'Rekening Bank',
              onTap: () => context.push('/settings'),
            ),
          ]),

          _Group(title: 'Keuangan', items: [
            _Item(
              icon: Icons.account_balance_wallet_outlined,
              title: 'Isi Saldo',
              onTap: () => context.push('/deposit'),
            ),
            _Item(
              icon: Icons.swap_vert_rounded,
              title: 'Mutasi Saldo',
              onTap: () => context.push('/mutations'),
            ),
          ]),

          _Group(title: 'Lainnya', items: [
            _Item(
              icon: Icons.help_outline_rounded,
              title: 'Pusat Bantuan',
              onTap: () => _openWhatsApp(context),
            ),
            _Item(
              icon: Icons.settings_outlined,
              title: 'Pengaturan',
              onTap: () => context.push('/settings'),
            ),
            _Item(
              icon: Icons.info_outline_rounded,
              title: 'Tentang Aplikasi',
              onTap: () => showAboutDialog(
                context: context,
                applicationName: AppConfig.appName,
                applicationVersion: '1.0.0',
                applicationLegalese: '© ${DateTime.now().year} ${AppConfig.appName}',
                children: const [
                  SizedBox(height: 12),
                  Text('Aplikasi jualan pulsa, paket data, dan pembayaran tagihan.'),
                ],
              ),
            ),
          ]),

          Padding(
            padding: const EdgeInsets.all(20),
            child: OutlinedButton.icon(
              onPressed: () => _confirmLogout(context, ref),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.danger,
                side: const BorderSide(color: AppColors.danger),
              ),
              icon: const Icon(Icons.logout_rounded),
              label: const Text('Keluar'),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openWhatsApp(BuildContext context) async {
    final uri = Uri.parse('https://wa.me/6281234567890');

    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (context.mounted) {
        AppSnackbar.error(context, 'Tidak dapat membuka WhatsApp.');
      }
    }
  }

  Future<void> _confirmLogout(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Keluar dari akun?'),
        content: const Text('Anda harus masuk kembali untuk bertransaksi.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(backgroundColor: AppColors.danger),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );

    if (confirmed ?? false) {
      await ref.read(authControllerProvider.notifier).logout();
    }
  }
}

class _Group extends StatelessWidget {
  const _Group({required this.title, required this.items});

  final String title;
  final List<Widget> items;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 4, bottom: 8),
            child: Text(title,
                style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textMutedLight)),
          ),
          Card(child: Column(children: items)),
        ],
      ),
    );
  }
}

class _Item extends StatelessWidget {
  const _Item({required this.icon, required this.title, required this.onTap});

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, size: 22),
      title: Text(title, style: const TextStyle(fontSize: 14)),
      trailing: const Icon(Icons.chevron_right_rounded),
      onTap: onTap,
    );
  }
}
