/// Small helpers for reading decoded JSON under `strict-casts`.
typedef Json = Map<String, dynamic>;

extension JsonRead on Json {
  String str(String key, [String fallback = '']) {
    final value = this[key];
    if (value == null) return fallback;
    return value is String ? value : value.toString();
  }

  String? strOrNull(String key) {
    final value = this[key];
    if (value == null) return null;
    return value is String ? value : value.toString();
  }

  int integer(String key, [int fallback = 0]) => intOrNull(key) ?? fallback;

  int? intOrNull(String key) {
    final value = this[key];
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }

  /// Non-money numbers only (coordinates, metres, scores).
  double? doubleOrNull(String key) {
    final value = this[key];
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  bool boolean(String key, [bool fallback = false]) {
    final value = this[key];
    if (value is bool) return value;
    if (value is num) return value != 0;
    if (value is String) return value == 'true' || value == '1';
    return fallback;
  }

  Json? obj(String key) {
    final value = this[key];
    return value is Map ? value.cast<String, dynamic>() : null;
  }

  List<Json> objList(String key) {
    final value = this[key];
    if (value is! List) return const [];
    return [
      for (final item in value)
        if (item is Map) item.cast<String, dynamic>(),
    ];
  }

  List<String> strList(String key) {
    final value = this[key];
    if (value is! List) return const [];
    return [for (final item in value) item.toString()];
  }

  DateTime? date(String key) {
    final value = this[key];
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}

Json asJson(Object? value) =>
    value is Map ? value.cast<String, dynamic>() : <String, dynamic>{};

List<Json> asJsonList(Object? value) => value is List
    ? [
        for (final item in value)
          if (item is Map) item.cast<String, dynamic>(),
      ]
    : const [];
