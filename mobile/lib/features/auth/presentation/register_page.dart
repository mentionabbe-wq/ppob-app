import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/widgets/app_snackbar.dart';

class RegisterPage extends ConsumerStatefulWidget {
  const RegisterPage({super.key});

  @override
  ConsumerState<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends ConsumerState<RegisterPage> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _referral = TextEditingController();

  bool _obscure = true;
  bool _agree = false;
  bool _loading = false;

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _password, _confirm, _referral]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _loading) return;

    if (!_agree) {
      AppSnackbar.info(context, 'Anda harus menyetujui syarat & ketentuan.');
      return;
    }

    setState(() => _loading = true);

    try {
      await ref.read(authRepositoryProvider).register(
            name: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            phone: _phone.text.trim(),
            referralCode: _referral.text.trim(),
          );

      if (!mounted) return;

      AppSnackbar.success(context, 'Registrasi berhasil. Cek email untuk kode OTP.');
      context.push('/otp', extra: {'email': _email.text.trim(), 'purpose': 'register'});
    } on ApiException catch (e) {
      if (!mounted) return;

      // Tampilkan galat validasi tepat di field terkait.
      final fieldError = e.fieldError('email') ?? e.fieldError('phone');
      AppSnackbar.error(context, fieldError ?? e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Buat Akun')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Nama Lengkap',
                    prefixIcon: Icon(Icons.person_outline_rounded),
                  ),
                  validator: (v) =>
                      (v?.trim().length ?? 0) < 3 ? 'Nama minimal 3 karakter.' : null,
                ),
                const SizedBox(height: 16),

                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    prefixIcon: Icon(Icons.mail_outline_rounded),
                  ),
                  validator: (v) {
                    final email = v?.trim() ?? '';

                    if (email.isEmpty) return 'Email wajib diisi.';
                    if (!email.contains('@') || !email.contains('.')) {
                      return 'Format email tidak valid.';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 16),

                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Nomor HP',
                    hintText: '081234567890',
                    prefixIcon: Icon(Icons.phone_outlined),
                  ),
                  validator: (v) {
                    final phone = v?.trim() ?? '';

                    if (phone.isEmpty) return null; // opsional
                    if (!RegExp(r'^(\+62|62|0)8[1-9][0-9]{6,11}$').hasMatch(phone)) {
                      return 'Format nomor HP tidak valid.';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 16),

                TextFormField(
                  controller: _password,
                  obscureText: _obscure,
                  decoration: InputDecoration(
                    labelText: 'Kata Sandi',
                    helperText: 'Minimal 8 karakter, kombinasi huruf dan angka.',
                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                    suffixIcon: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded),
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  validator: (v) {
                    final password = v ?? '';

                    if (password.length < 8) return 'Kata sandi minimal 8 karakter.';
                    if (!RegExp(r'[A-Za-z]').hasMatch(password) ||
                        !RegExp(r'[0-9]').hasMatch(password)) {
                      return 'Harus mengandung huruf dan angka.';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 16),

                TextFormField(
                  controller: _confirm,
                  obscureText: _obscure,
                  decoration: const InputDecoration(
                    labelText: 'Ulangi Kata Sandi',
                    prefixIcon: Icon(Icons.lock_reset_rounded),
                  ),
                  validator: (v) =>
                      v != _password.text ? 'Kata sandi tidak sama.' : null,
                ),
                const SizedBox(height: 16),

                TextFormField(
                  controller: _referral,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(
                    labelText: 'Kode Referral (opsional)',
                    prefixIcon: Icon(Icons.card_giftcard_rounded),
                  ),
                ),
                const SizedBox(height: 12),

                CheckboxListTile(
                  value: _agree,
                  onChanged: (v) => setState(() => _agree = v ?? false),
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  title: const Text(
                    'Saya menyetujui Syarat & Ketentuan serta Kebijakan Privasi.',
                    style: TextStyle(fontSize: 13),
                  ),
                ),
                const SizedBox(height: 12),

                FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          height: 22,
                          width: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                        )
                      : const Text('Daftar'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
