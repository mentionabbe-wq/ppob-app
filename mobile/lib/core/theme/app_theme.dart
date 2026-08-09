import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

/// Palet fintech: biru sebagai warna utama, putih/abu netral,
/// aksen hijau untuk sukses dan merah untuk gagal.
class AppColors {
  const AppColors._();

  static const primary = Color(0xFF2563EB);
  static const primaryDark = Color(0xFF1D4ED8);
  static const primaryLight = Color(0xFF60A5FA);
  static const secondary = Color(0xFF0EA5E9);

  static const success = Color(0xFF10B981);
  static const warning = Color(0xFFF59E0B);
  static const danger = Color(0xFFEF4444);
  static const info = Color(0xFF0284C7);

  static const surfaceLight = Color(0xFFFFFFFF);
  static const backgroundLight = Color(0xFFF8FAFC);
  static const surfaceDark = Color(0xFF1E293B);
  static const backgroundDark = Color(0xFF0F172A);

  static const textLight = Color(0xFF0F172A);
  static const textMutedLight = Color(0xFF64748B);
  static const textDark = Color(0xFFF1F5F9);
  static const textMutedDark = Color(0xFF94A3B8);

  static const gradient = LinearGradient(
    colors: [primary, Color(0xFF1E40AF)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
}

class AppTheme {
  const AppTheme._();

  static const _radius = 16.0;

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isDark = brightness == Brightness.dark;

    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: brightness,
      primary: AppColors.primary,
      secondary: AppColors.secondary,
      error: AppColors.danger,
      surface: isDark ? AppColors.surfaceDark : AppColors.surfaceLight,
    );

    final textTheme = GoogleFonts.interTextTheme(
      isDark ? ThemeData.dark().textTheme : ThemeData.light().textTheme,
    ).apply(
      bodyColor: isDark ? AppColors.textDark : AppColors.textLight,
      displayColor: isDark ? AppColors.textDark : AppColors.textLight,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor:
          isDark ? AppColors.backgroundDark : AppColors.backgroundLight,
      textTheme: textTheme,
      splashFactory: InkSparkle.splashFactory,

      appBarTheme: AppBarTheme(
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        backgroundColor: isDark ? AppColors.backgroundDark : AppColors.surfaceLight,
        foregroundColor: isDark ? AppColors.textDark : AppColors.textLight,
        titleTextStyle: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600),
        systemOverlayStyle:
            isDark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      ),

      cardTheme: CardTheme(
        elevation: 0,
        color: isDark ? AppColors.surfaceDark : AppColors.surfaceLight,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(_radius)),
        margin: EdgeInsets.zero,
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? const Color(0xFF162032) : const Color(0xFFF1F5F9),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.danger, width: 1.5),
        ),
        hintStyle: TextStyle(
          color: isDark ? AppColors.textMutedDark : AppColors.textMutedLight,
        ),
      ),

      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        type: BottomNavigationBarType.fixed,
        backgroundColor: isDark ? AppColors.surfaceDark : AppColors.surfaceLight,
        selectedItemColor: AppColors.primary,
        unselectedItemColor:
            isDark ? AppColors.textMutedDark : AppColors.textMutedLight,
        elevation: 8,
        showUnselectedLabels: true,
      ),

      bottomSheetTheme: const BottomSheetThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        showDragHandle: true,
      ),

      dividerTheme: DividerThemeData(
        color: isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0),
        thickness: 1,
        space: 1,
      ),

      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),

      // Animasi halus antar halaman.
      pageTransitionsTheme: const PageTransitionsTheme(builders: {
        TargetPlatform.android: CupertinoPageTransitionsBuilder(),
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      }),
    );
  }
}
