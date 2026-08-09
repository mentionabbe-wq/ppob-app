import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/storage/secure_storage.dart';
import '../data/auth_repository.dart';
import '../domain/user.dart';

/// Status sesi aplikasi.
sealed class AuthState {
  const AuthState();
}

class AuthUnknown extends AuthState {
  const AuthUnknown();
}

class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated();
}

class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);

  final User user;
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._repository, this._storage) : super(const AuthUnknown()) {
    restore();
  }

  final AuthRepository _repository;
  final SecureStorage _storage;

  /// Dipanggil saat aplikasi dibuka: token tersimpan divalidasi
  /// dengan sekali panggilan /auth/me.
  Future<void> restore() async {
    if (!await _storage.hasToken()) {
      state = const AuthUnauthenticated();
      return;
    }

    try {
      state = AuthAuthenticated(await _repository.me());
    } catch (_) {
      await _storage.clearToken();
      state = const AuthUnauthenticated();
    }
  }

  Future<void> login(String email, String password, {String? fcmToken}) async {
    final user = await _repository.login(
      email: email,
      password: password,
      fcmToken: fcmToken,
    );

    state = AuthAuthenticated(user);
  }

  Future<void> loginWithGoogle(String idToken, {String? fcmToken}) async {
    state = AuthAuthenticated(
      await _repository.loginWithGoogle(idToken, fcmToken: fcmToken),
    );
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AuthUnauthenticated();
  }

  /// Menyegarkan saldo & profil setelah transaksi atau deposit.
  Future<void> refreshUser() async {
    if (state is! AuthAuthenticated) return;

    try {
      state = AuthAuthenticated(await _repository.me());
    } catch (_) {
      // Gagal menyegarkan bukan alasan untuk mengeluarkan pengguna.
    }
  }

  void updateUser(User user) => state = AuthAuthenticated(user);
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(
    ref.watch(authRepositoryProvider),
    ref.watch(secureStorageProvider),
  );
});

/// Pengguna aktif (null bila belum login) — dipakai banyak widget.
final currentUserProvider = Provider<User?>((ref) {
  final state = ref.watch(authControllerProvider);

  return state is AuthAuthenticated ? state.user : null;
});
