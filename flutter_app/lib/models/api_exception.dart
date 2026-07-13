/// Thrown when the API returns `success:false`, a non-2xx status, or a transport
/// error. `isAuthError` lets the UI route back to the login screen on 401.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.code});

  final String message;
  final int? statusCode;
  final String? code;

  bool get isAuthError => statusCode == 401;

  @override
  String toString() => 'ApiException($statusCode${code != null ? '/$code' : ''}): $message';
}
