import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/auth/data/auth_repository.dart';
import '../features/catalog/data/catalog_repository.dart';
import '../features/deposit/data/deposit_repository.dart';
import '../features/notification/data/notification_repository.dart';
import '../features/profile/data/profile_repository.dart';
import '../features/transaction/data/transaction_repository.dart';
import 'network/dio_client.dart';
import 'storage/secure_storage.dart';

/// Container dependency injection aplikasi. Semua repository dibuat
/// di sini sehingga mudah di-override saat pengujian.

final secureStorageProvider = Provider<SecureStorage>((ref) => SecureStorage());

/// Diberi nilai true oleh DioClient saat refresh token gagal, sehingga
/// router dapat memaksa kembali ke halaman login.
final sessionExpiredProvider = StateProvider<bool>((ref) => false);

final dioClientProvider = Provider<DioClient>((ref) {
  return DioClient(
    storage: ref.watch(secureStorageProvider),
    onSessionExpired: () {
      ref.read(sessionExpiredProvider.notifier).state = true;
    },
  );
});

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(dioClientProvider), ref.watch(secureStorageProvider)),
);

final catalogRepositoryProvider = Provider<CatalogRepository>(
  (ref) => CatalogRepository(ref.watch(dioClientProvider)),
);

final transactionRepositoryProvider = Provider<TransactionRepository>(
  (ref) => TransactionRepository(ref.watch(dioClientProvider)),
);

final depositRepositoryProvider = Provider<DepositRepository>(
  (ref) => DepositRepository(ref.watch(dioClientProvider)),
);

final profileRepositoryProvider = Provider<ProfileRepository>(
  (ref) => ProfileRepository(ref.watch(dioClientProvider)),
);

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) => NotificationRepository(ref.watch(dioClientProvider)),
);
