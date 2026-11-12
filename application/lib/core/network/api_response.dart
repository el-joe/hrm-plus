import 'json.dart';

/// A successful `/api/v1` envelope:
/// `{"success": true, "message": string|null, "data": mixed, "meta": object?}`.
class ApiResponse {
  const ApiResponse({
    required this.statusCode,
    this.data,
    this.message,
    this.meta = const {},
  });

  final int statusCode;
  final Object? data;
  final String? message;
  final Json meta;

  Json get json => asJson(data);
  List<Json> get list => asJsonList(data);

  Pagination? get pagination {
    final raw = meta.obj('pagination');
    return raw == null ? null : Pagination.fromJson(raw);
  }
}

/// `meta.pagination` of paginated lists.
class Pagination {
  const Pagination({
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  factory Pagination.fromJson(Json json) => Pagination(
    currentPage: json.integer('current_page', 1),
    perPage: json.integer('per_page', 20),
    total: json.integer('total'),
    lastPage: json.integer('last_page', 1),
  );

  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

/// One page of a paginated list.
class Paged<T> {
  const Paged({required this.items, this.pagination, this.meta = const {}});

  final List<T> items;
  final Pagination? pagination;
  final Json meta;

  bool get hasMore => pagination?.hasMore ?? false;
  int get page => pagination?.currentPage ?? 1;
}
