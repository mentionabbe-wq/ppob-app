import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_page.dart';
import '../../features/auth/presentation/otp_page.dart';
import '../../features/auth/presentation/register_page.dart';
import '../../features/auth/presentation/reset_password_page.dart';
import '../../features/catalog/domain/product.dart';
import '../../features/catalog/presentation/product_page.dart';
import '../../features/deposit/presentation/deposit_detail_page.dart';
import '../../features/deposit/presentation/deposit_page.dart';
import '../../features/home/presentation/home_shell.dart';
import '../../features/profile/presentation/mutation_page.dart';
import '../../features/profile/presentation/settings_page.dart';
import '../../features/splash/splash_page.dart';
import '../../features/transaction/presentation/transaction_detail_page.dart';

/// Rute aplikasi + penjaga autentikasi. Selama status sesi belum
/// diketahui, pengguna ditahan di splash agar tidak berkedip.
final routerProvider = Provider<GoRouter>((ref) {
  final notifier = ValueNotifier<AuthState>(const AuthUnknown());

  ref.listen(authControllerProvider, (_, next) => notifier.value = next);
  ref.onDispose(notifier.dispose);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: notifier,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final path = state.matchedLocation;

      if (auth is AuthUnknown) {
        return path == '/splash' ? null : '/splash';
      }

      final isPublic = _publicRoutes.any(path.startsWith);

      if (auth is AuthUnauthenticated) {
        return isPublic ? null : '/login';
      }

      // Sudah login: jangan biarkan kembali ke splash/login.
      if (path == '/splash' || path == '/login' || path == '/register') {
        return '/';
      }

      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const SplashPage()),
      GoRoute(path: '/login', builder: (_, __) => const LoginPage()),
      GoRoute(path: '/register', builder: (_, __) => const RegisterPage()),
      GoRoute(
        path: '/otp',
        builder: (_, state) {
          final extra = state.extra as Map<String, dynamic>? ?? const {};

          return OtpPage(
            email: extra['email'] as String? ?? '',
            purpose: extra['purpose'] as String? ?? 'register',
          );
        },
      ),
      GoRoute(
        path: '/reset-password',
        builder: (_, state) => ResetPasswordPage(email: state.extra as String? ?? ''),
      ),

      GoRoute(path: '/', builder: (_, __) => const HomeShell()),

      GoRoute(
        path: '/category/:slug',
        builder: (_, state) => ProductPage(
          category: state.extra as Category?,
          categorySlug: state.pathParameters['slug']!,
        ),
      ),

      GoRoute(
        path: '/transactions/:id',
        builder: (_, state) => TransactionDetailPage(
          transactionId: int.parse(state.pathParameters['id']!),
        ),
      ),

      GoRoute(path: '/deposit', builder: (_, __) => const DepositPage()),
      GoRoute(
        path: '/deposit/:id',
        builder: (_, state) => DepositDetailPage(
          depositId: int.parse(state.pathParameters['id']!),
        ),
      ),

      GoRoute(path: '/mutations', builder: (_, __) => const MutationPage()),
      GoRoute(path: '/settings', builder: (_, __) => const SettingsPage()),
    ],
    errorBuilder: (_, state) => Scaffold(
      body: Center(child: Text('Halaman tidak ditemukan:\n${state.uri}')),
    ),
  );
});

const _publicRoutes = ['/splash', '/login', '/register', '/otp', '/reset-password'];
