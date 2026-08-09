/// Konversi aman dari JSON dinamis. Backend mengembalikan angka
/// sebagai int maupun string tergantung driver, jadi jangan pernah
/// melakukan cast langsung.
int asInt(dynamic value, [int fallback = 0]) => switch (value) {
      int v => v,
      double v => v.toInt(),
      String v => int.tryParse(v) ?? fallback,
      _ => fallback,
    };

double asDouble(dynamic value, [double fallback = 0]) => switch (value) {
      double v => v,
      int v => v.toDouble(),
      String v => double.tryParse(v) ?? fallback,
      _ => fallback,
    };

String asString(dynamic value, [String fallback = '']) =>
    value?.toString() ?? fallback;

bool asBool(dynamic value, [bool fallback = false]) => switch (value) {
      bool v => v,
      int v => v == 1,
      String v => v == '1' || v.toLowerCase() == 'true',
      _ => fallback,
    };

DateTime? asDate(dynamic value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;

List<String> asStringList(dynamic value) =>
    value is List ? value.map((e) => e.toString()).toList() : const [];

List<Map<String, dynamic>> asMapList(dynamic value) => value is List
    ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : const [];
