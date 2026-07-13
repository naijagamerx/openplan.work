/// The standard backend response envelope: `{success, data, message, timestamp}`.
///
/// Errors come back two ways across the codebase:
///   - `"error": "some message"` (string), or
///   - `"error": {"code": "...", "message": "..."}` (object).
/// This normalizes both so the client always gets a usable message + code.
class ApiEnvelope {
  ApiEnvelope({
    required this.success,
    this.data,
    this.message,
    this.errorCode,
  });

  final bool success;
  final dynamic data;
  final String? message;
  final String? errorCode;

  factory ApiEnvelope.fromJson(Map<String, dynamic> json) {
    String? message = json['message'] as String?;
    String? code;

    final err = json['error'];
    if (err is Map) {
      message = (err['message'] as String?) ?? message;
      code = err['code'] as String?;
    } else if (err is String) {
      message = err;
    }

    return ApiEnvelope(
      success: json['success'] == true,
      data: json['data'],
      message: message,
      errorCode: code,
    );
  }
}
