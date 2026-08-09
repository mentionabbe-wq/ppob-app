import 'package:flutter/material.dart';

import '../../core/config/app_config.dart';
import '../../core/theme/app_theme.dart';

/// Ditampilkan selama AuthController memverifikasi token tersimpan.
class SplashPage extends StatelessWidget {
  const SplashPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppColors.gradient),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                height: 88,
                width: 88,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: const Icon(Icons.bolt_rounded, size: 48, color: AppColors.primary),
              ),
              const SizedBox(height: 20),
              Text(
                AppConfig.appName,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Pulsa, Paket Data & PPOB',
                style: TextStyle(color: Colors.white70),
              ),
              const SizedBox(height: 36),
              const SizedBox(
                height: 26,
                width: 26,
                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
