import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/widgets/app_snackbar.dart';

class ResetPasswordPage extends ConsumerStatefulWidget {
  const ResetPasswordPage({super.key, required this.email});

  final String email;

  @override
  ConsumerState<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends ConsumerState<ResetPasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _otp = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();

  bool _loading = false;

  @override
  void dispose() {
    _otp.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _loading) return;

    setState(() => _loading = true);

    try {
      await ref.read(authRepositoryProvider).resetPassword(
            email: widget.email,
            otp: _otp.text.trim(),
            password: _password.text,
          );

      if (!mounted) return;

      AppSnackbar.success(context, 'Kata sandi berhasil diubah. Silakan masuk.');
      context.go('/login');
    } on ApiException catch (e) {
      if (mounted) AppSnackbar.error(context, e.fieldError('otp') ?? e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Atur Ulang Kata Sandi')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Kode OTP dikirim ke ${widget.email}'),
                const SizedBox(height: 24),

                TextFormField(
                  controller: _otp,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  decoration: const InputDecoration(
                    labelText: 'Kode OTP',
                    counterText: '',
                    prefixIcon: Icon(Icons.pin_outlined),
                  ),
                  validator: (v) =>
                      (v?.trim().length ?? 0) != 6 ? 'Kode OTP harus 6 digit.' : null,
                ),
                const SizedBox(height: 16),

                TextFormField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Kata Sandi Baru',
                    prefixIcon: Icon(Icons.lock_outline_rounded),
                  ),
                  validator: (v) {
                    final password = v ?? '';

                    if (password.length < 8) return 'Minimal 8 karakter.';
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
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Ulangi Kata Sandi',
                    prefixIcon: Icon(Icons.lock_reset_rounded),
                  ),
                  validator: (v) => v != _password.text ? 'Kata sandi tidak sama.' : null,
                ),
                const SizedBox(height: 24),

                FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          height: 22,
                          width: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                        )
                      : const Text('Simpan Kata Sandi'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
