import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Notifikasi ringkas yang konsisten di seluruh aplikasi.
class AppSnackbar {
  const AppSnackbar._();

  static void success(BuildContext context, String message) =>
      _show(context, message, AppColors.success, Icons.check_circle_rounded);

  static void error(BuildContext context, String message) =>
      _show(context, message, AppColors.danger, Icons.error_rounded);

  static void info(BuildContext context, String message) =>
      _show(context, message, AppColors.info, Icons.info_rounded);

  static void _show(BuildContext context, String message, Color color, IconData icon) {
    if (!context.mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Row(
            children: [
              Icon(icon, color: Colors.white, size: 20),
              const SizedBox(width: 12),
              Expanded(child: Text(message)),
            ],
          ),
          backgroundColor: color,
          duration: const Duration(seconds: 3),
        ),
      );
  }
}
