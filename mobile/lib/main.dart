import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/config/app_config.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await initializeDateFormatting('id_ID');

  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  final prefs = await SharedPreferences.getInstance();

  runApp(
    ProviderScope(
      overrides: [themeModeProvider.overrideWith((ref) => ThemeModeController(prefs))],
      child: const PpobApp(),
    ),
  );
}

/// Mode tema disimpan lokal agar pilihan pengguna bertahan.
class ThemeModeController extends StateNotifier<ThemeMode> {
  ThemeModeController(this._prefs) : super(_read(_prefs));

  final SharedPreferences _prefs;

  static const _key = 'theme_mode';

  static ThemeMode _read(SharedPreferences prefs) => switch (prefs.getString(_key)) {
        'light' => ThemeMode.light,
        'dark' => ThemeMode.dark,
        _ => ThemeMode.system,
      };

  Future<void> set(ThemeMode mode) async {
    state = mode;
    await _prefs.setString(_key, mode.name);
  }
}

final themeModeProvider = StateNotifierProvider<ThemeModeController, ThemeMode>(
  (ref) => throw UnimplementedError('Di-override di main()'),
);

class PpobApp extends ConsumerWidget {
  const PpobApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp.router(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: ref.watch(themeModeProvider),
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) {
        // Kunci skala teks agar tata letak fintech tetap rapi.
        final scale = MediaQuery.textScalerOf(context).clamp(maxScaleFactor: 1.3);

        return MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: scale),
          child: child ?? const SizedBox.shrink(),
        );
      },
    );
  }
}
