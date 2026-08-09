import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_snackbar.dart';
import 'auth_controller.dart';

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _obscure = true;
  bool _loading = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _loading) return;

    setState(() => _loading = true);

    try {
      await ref.read(authControllerProvider.notifier).login(
            _emailController.text.trim(),
            _passwordController.text,
          );

      if (mounted) context.go('/');
    } on ApiException catch (e) {
      if (!mounted) return;

      // Email belum terverifikasi → arahkan langsung ke halaman OTP.
      if (e.code == 'EMAIL_NOT_VERIFIED') {
        context.push('/otp', extra: {
          'email': _emailController.text.trim(),
          'purpose': 'register',
        });
        return;
      }

      AppSnackbar.error(context, e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _forgotPassword() async {
    final email = _emailController.text.trim();

    if (email.isEmpty) {
      AppSnackbar.info(context, 'Isi email Anda terlebih dahulu.');
      return;
    }

    try {
      await ref.read(authRepositoryProvider).forgotPassword(email);

      if (!mounted) return;

      AppSnackbar.success(context, 'Kode OTP dikirim ke email Anda.');
      context.push('/reset-password', extra: email);
    } on ApiException catch (e) {
      if (mounted) AppSnackbar.error(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 32),
                Center(
                  child: Container(
                    height: 72,
                    width: 72,
                    decoration: BoxDecoration(
                      gradient: AppColors.gradient,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Icon(Icons.bolt_rounded, color: Colors.white, size: 40),
                  ),
                ),
                const SizedBox(height: 28),
                Text('Selamat Datang', style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
                const SizedBox(height: 6),
                Text(
                  'Masuk untuk mulai bertransaksi.',
                  style: theme.textTheme.bodyMedium?.copyWith(color: AppColors.textMutedLight),
                ),
                const SizedBox(height: 32),

                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.email],
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    prefixIcon: Icon(Icons.mail_outline_rounded),
                  ),
                  validator: (value) {
                    final email = value?.trim() ?? '';

                    if (email.isEmpty) return 'Email wajib diisi.';
                    if (!email.contains('@') || !email.contains('.')) {
                      return 'Format email tidak valid.';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 16),

                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscure,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.password],
                  onFieldSubmitted: (_) => _submit(),
                  decoration: InputDecoration(
                    labelText: 'Kata Sandi',
                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                    suffixIcon: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded),
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  validator: (value) =>
                      (value?.isEmpty ?? true) ? 'Kata sandi wajib diisi.' : null,
                ),

                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: _forgotPassword,
                    child: const Text('Lupa kata sandi?'),
                  ),
                ),
                const SizedBox(height: 8),

                FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          height: 22,
                          width: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                        )
                      : const Text('Masuk'),
                ),

                if (AppConfig.enableGoogleSignIn) ...[
                  const SizedBox(height: 16),
                  Row(children: [
                    const Expanded(child: Divider()),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Text('atau', style: theme.textTheme.bodySmall),
                    ),
                    const Expanded(child: Divider()),
                  ]),
                  const SizedBox(height: 16),
                  OutlinedButton.icon(
                    onPressed: _loading ? null : _signInWithGoogle,
                    icon: const Icon(Icons.g_mobiledata_rounded, size: 28),
                    label: const Text('Masuk dengan Google'),
                  ),
                ],

                const SizedBox(height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Text('Belum punya akun?'),
                    TextButton(
                      onPressed: () => context.push('/register'),
                      child: const Text('Daftar sekarang'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _signInWithGoogle() async {
    // Integrasi google_sign_in: ambil idToken lalu tukarkan ke backend.
    AppSnackbar.info(
      context,
      'Aktifkan GOOGLE_CLIENT_ID dan konfigurasi google_sign_in untuk memakai fitur ini.',
    );
  }
}
