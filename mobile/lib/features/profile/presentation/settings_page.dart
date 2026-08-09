import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/widgets/app_snackbar.dart';
import '../../../main.dart';
import '../../auth/presentation/auth_controller.dart';

class SettingsPage extends ConsumerWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(themeModeProvider);
    final user = ref.watch(currentUserProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const _SectionTitle('Tampilan'),
          Card(
            child: Column(
              children: ThemeMode.values.map((mode) {
                return RadioListTile<ThemeMode>(
                  value: mode,
                  groupValue: themeMode,
                  onChanged: (value) =>
                      ref.read(themeModeProvider.notifier).set(value ?? ThemeMode.system),
                  title: Text(switch (mode) {
                    ThemeMode.system => 'Ikuti Sistem',
                    ThemeMode.light => 'Mode Terang',
                    ThemeMode.dark => 'Mode Gelap',
                  }),
                );
              }).toList(),
            ),
          ),

          const SizedBox(height: 16),
          const _SectionTitle('Akun'),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.person_outline_rounded),
                  title: const Text('Edit Profil'),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => _editProfile(context, ref),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.lock_outline_rounded),
                  title: const Text('Ganti Kata Sandi'),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => _changePassword(context, ref),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.pin_outlined),
                  title: Text((user?.hasPin ?? false)
                      ? 'Ubah PIN Transaksi'
                      : 'Buat PIN Transaksi'),
                  subtitle: const Text(
                    'PIN diminta setiap kali melakukan pembelian.',
                    style: TextStyle(fontSize: 12),
                  ),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => _setPin(context, ref, hasPin: user?.hasPin ?? false),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),
          const _SectionTitle('Informasi'),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.info_outline_rounded),
                  title: const Text('Versi Aplikasi'),
                  trailing: const Text('1.0.0'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.dns_outlined),
                  title: const Text('Server API'),
                  subtitle: Text(AppConfig.apiBaseUrl, style: const TextStyle(fontSize: 11)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _editProfile(BuildContext context, WidgetRef ref) async {
    final user = ref.read(currentUserProvider);
    final name = TextEditingController(text: user?.name);
    final phone = TextEditingController(text: user?.phone);

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _FormSheet(
        title: 'Edit Profil',
        fields: [
          TextField(
            controller: name,
            decoration: const InputDecoration(labelText: 'Nama Lengkap'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: phone,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'Nomor HP'),
          ),
        ],
        onSubmit: () async {
          final updated = await ref.read(profileRepositoryProvider).update(
                name: name.text.trim(),
                phone: phone.text.trim(),
              );

          ref.read(authControllerProvider.notifier).updateUser(updated);
        },
      ),
    );

    if ((saved ?? false) && context.mounted) {
      AppSnackbar.success(context, 'Profil diperbarui.');
    }
  }

  Future<void> _changePassword(BuildContext context, WidgetRef ref) async {
    final current = TextEditingController();
    final password = TextEditingController();

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _FormSheet(
        title: 'Ganti Kata Sandi',
        fields: [
          TextField(
            controller: current,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Kata Sandi Saat Ini'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: password,
            obscureText: true,
            decoration: const InputDecoration(
              labelText: 'Kata Sandi Baru',
              helperText: 'Minimal 8 karakter, huruf dan angka.',
            ),
          ),
        ],
        onSubmit: () => ref.read(profileRepositoryProvider).changePassword(
              currentPassword: current.text,
              password: password.text,
            ),
      ),
    );

    if ((saved ?? false) && context.mounted) {
      AppSnackbar.success(context, 'Kata sandi berhasil diubah.');
    }
  }

  Future<void> _setPin(BuildContext context, WidgetRef ref, {required bool hasPin}) async {
    final currentPin = TextEditingController();
    final pin = TextEditingController();
    final password = TextEditingController();

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _FormSheet(
        title: hasPin ? 'Ubah PIN Transaksi' : 'Buat PIN Transaksi',
        fields: [
          if (hasPin) ...[
            TextField(
              controller: currentPin,
              obscureText: true,
              keyboardType: TextInputType.number,
              maxLength: AppConfig.pinLength,
              decoration: const InputDecoration(labelText: 'PIN Lama', counterText: ''),
            ),
            const SizedBox(height: 12),
          ],
          TextField(
            controller: pin,
            obscureText: true,
            keyboardType: TextInputType.number,
            maxLength: AppConfig.pinLength,
            decoration: const InputDecoration(labelText: 'PIN Baru (6 digit)', counterText: ''),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: password,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Kata Sandi Akun'),
          ),
        ],
        onSubmit: () async {
          await ref.read(profileRepositoryProvider).setPin(
                pin: pin.text,
                password: password.text,
                currentPin: hasPin ? currentPin.text : null,
              );

          await ref.read(authControllerProvider.notifier).refreshUser();
        },
      ),
    );

    if ((saved ?? false) && context.mounted) {
      AppSnackbar.success(context, 'PIN transaksi tersimpan.');
    }
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(title,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
    );
  }
}

/// Bottom sheet form generik: menampilkan galat API di tempat.
class _FormSheet extends StatefulWidget {
  const _FormSheet({
    required this.title,
    required this.fields,
    required this.onSubmit,
  });

  final String title;
  final List<Widget> fields;
  final Future<void> Function() onSubmit;

  @override
  State<_FormSheet> createState() => _FormSheetState();
}

class _FormSheetState extends State<_FormSheet> {
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await widget.onSubmit();

      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
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
            Text(widget.title,
                style: Theme.of(context)
                    .textTheme
                    .titleLarge
                    ?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 20),
            ...widget.fields,
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _loading ? null : _submit,
              child: _loading
                  ? const SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                    )
                  : const Text('Simpan'),
            ),
          ],
        ),
      ),
    );
  }
}
