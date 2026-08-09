import '../../../core/utils/parse.dart';

class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.balance,
    this.phone,
    this.avatarUrl,
    this.referralCode,
    this.emailVerified = false,
    this.hasPin = false,
    this.roles = const [],
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? avatarUrl;
  final String? referralCode;
  final double balance;
  final bool emailVerified;
  final bool hasPin;
  final List<String> roles;

  bool get isReseller => roles.contains('reseller');

  factory User.fromJson(Map<String, dynamic> json) => User(
        id: asInt(json['id']),
        name: asString(json['name']),
        email: asString(json['email']),
        phone: json['phone'] as String?,
        avatarUrl: json['avatar_url'] as String?,
        referralCode: json['referral_code'] as String?,
        balance: asDouble(json['balance']),
        emailVerified: asBool(json['email_verified']),
        hasPin: asBool(json['has_pin']),
        roles: asStringList(json['roles']),
      );

  User copyWith({String? name, String? phone, String? avatarUrl, double? balance, bool? hasPin}) =>
      User(
        id: id,
        name: name ?? this.name,
        email: email,
        phone: phone ?? this.phone,
        avatarUrl: avatarUrl ?? this.avatarUrl,
        referralCode: referralCode,
        balance: balance ?? this.balance,
        emailVerified: emailVerified,
        hasPin: hasPin ?? this.hasPin,
        roles: roles,
      );
}
