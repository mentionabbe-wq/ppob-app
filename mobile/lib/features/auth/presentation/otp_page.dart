import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:pin_code_fields/pin_code_fields.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_snackbar.dart';

class OtpPage extends ConsumerStatefulWidget {
  const OtpPage({super.key, required this.email, required this.purpose});

  final String email;
  final String purpose;

  @override
  ConsumerState<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends ConsumerState<OtpPage> {
  final _controller = TextEditingController();

  Timer? _timer;
  int _cooldown = 60;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _startCooldown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _startCooldown() {
    setState(() => _cooldown = 60);

    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_cooldown <= 1) {
        timer.cancel();
      }

      if (mounted) setState(() => _cooldown--);
    });
  }

  Future<void> _verify(String code) async {
    if (_loading) return;

    setState(() => _loading = true);

    try {
      await ref.read(authRepositoryProvider).verifyOtp(email: widget.email, otp: code);

      if (!mounted) return;

      AppSnackbar.success(context, 'Verifikasi berhasil. Silakan masuk.');
      context.go('/login');
    } on ApiException catch (e) {
      if (!mounted) return;

      _controller.clear();
      AppSnackbar.error(context, e.fieldError('otp') ?? e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _resend() async {
    try {
      await ref
          .read(authRepositoryProvider)
          .sendOtp(email: widget.email, purpose: widget.purpose);

      if (!mounted) return;

      AppSnackbar.success(context, 'Kode OTP baru telah dikirim.');
      _startCooldown();
    } on ApiException catch (e) {
      if (mounted) AppSnackbar.error(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Verifikasi OTP')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 16),
              const Icon(Icons.mark_email_read_outlined, size: 72, color: AppColors.primary),
              const SizedBox(height: 24),
              Text(
                'Masukkan Kode Verifikasi',
                textAlign: TextAlign.center,
                style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'Kami mengirim ${AppConfig.otpLength} digit kode ke\n${widget.email}',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(color: AppColors.textMutedLight),
              ),
              const SizedBox(height: 32),

              PinCodeTextField(
                appContext: context,
                controller: _controller,
                length: AppConfig.otpLength,
                autoFocus: true,
                keyboardType: TextInputType.number,
                animationType: AnimationType.fade,
                enableActiveFill: true,
                pinTheme: PinTheme(
                  shape: PinCodeFieldShape.box,
                  borderRadius: BorderRadius.circular(12),
                  fieldHeight: 52,
                  fieldWidth: 46,
                  activeColor: AppColors.primary,
                  selectedColor: AppColors.primary,
                  inactiveColor: theme.dividerColor,
                  activeFillColor: theme.inputDecorationTheme.fillColor!,
                  selectedFillColor: theme.inputDecorationTheme.fillColor!,
                  inactiveFillColor: theme.inputDecorationTheme.fillColor!,
                ),
                onCompleted: _verify,
                onChanged: (_) {},
              ),
              const SizedBox(height: 24),

              FilledButton(
                onPressed: _loading || _controller.text.length < AppConfig.otpLength
                    ? null
                    : () => _verify(_controller.text),
                child: _loading
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                      )
                    : const Text('Verifikasi'),
              ),
              const SizedBox(height: 16),

              Center(
                child: _cooldown > 0
                    ? Text(
                        'Kirim ulang kode dalam $_cooldown detik',
                        style: theme.textTheme.bodySmall,
                      )
                    : TextButton(onPressed: _resend, child: const Text('Kirim ulang kode')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
