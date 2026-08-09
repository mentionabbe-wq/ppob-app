import '../../../core/network/dio_client.dart';
import '../../../core/utils/parse.dart';

class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.isRead,
    this.imageUrl,
    this.data,
    this.createdAt,
  });

  final int id;
  final String type;
  final String title;
  final String body;
  final bool isRead;
  final String? imageUrl;
  final Map<String, dynamic>? data;
  final DateTime? createdAt;

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        id: asInt(json['id']),
        type: asString(json['type'], 'system'),
        title: asString(json['title']),
        body: asString(json['body']),
        isRead: asBool(json['is_read']),
        imageUrl: json['image_url'] as String?,
        data: json['data'] is Map ? Map<String, dynamic>.from(json['data']) : null,
        createdAt: asDate(json['created_at']),
      );
}

class NotificationRepository {
  const NotificationRepository(this._client);

  final DioClient _client;

  Future<List<AppNotification>> list({String? type, bool unreadOnly = false, int page = 1}) async {
    final response = await _client.get('/notifications', query: {
      if (type != null) 'type': type,
      if (unreadOnly) 'unread_only': true,
      'page': page,
    });

    return asMapList(response['data']).map(AppNotification.fromJson).toList();
  }

  Future<int> unreadCount() async {
    final response = await _client.get('/notifications/unread-count');

    return asInt(response['data']?['count']);
  }

  Future<void> markAsRead(int id) => _client.put('/notifications/$id/read');

  Future<void> markAllAsRead() => _client.put('/notifications/read-all');
}
