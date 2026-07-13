import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';

final kbRepositoryProvider = Provider<KnowledgeBaseRepository>(
    (ref) => KnowledgeBaseRepository(ref.watch(apiClientProvider)));

/// A knowledge-base folder as returned by `GET /api/knowledge-base.php?action=list_folders`.
/// Mirrors the backend schema (see api/knowledge-base.php::handleListFolders).
class KbFolder {
  KbFolder({
    required this.id,
    required this.name,
    this.fileCount = 0,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String name;
  final int fileCount;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  factory KbFolder.fromJson(Map<String, dynamic> j) => KbFolder(
        id: (j['id'] ?? '').toString(),
        name: (j['name'] ?? '').toString(),
        fileCount: j['fileCount'] is num ? (j['fileCount'] as num).toInt() : 0,
        createdAt: _parseDate(j['createdAt']),
        updatedAt: _parseDate(j['updatedAt']),
      );

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}

/// A knowledge-base file as returned by `GET /api/knowledge-base.php?action=list_files`
/// (metadata only — `content` is omitted) or `get_file` (full record, content base64).
/// Mirrors api/knowledge-base.php.
class KbFile {
  KbFile({
    required this.id,
    required this.name,
    required this.folderId,
    this.type = '',
    this.size = 0,
    this.contentBase64,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String name;
  final String folderId;
  final String type; // markdown | xml
  final int size; // decoded byte length
  final String? contentBase64; // base64-encoded text (absent on list responses)
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// Decoded text content, or '' when content hasn't been loaded yet.
  String get content {
    if (contentBase64 == null || contentBase64!.isEmpty) return '';
    final decoded = base64Decode(contentBase64!);
    return utf8.decode(decoded, allowMalformed: true);
  }

  factory KbFile.fromJson(Map<String, dynamic> j) {
    final rawContent = j['content'];
    return KbFile(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? '').toString(),
      folderId: (j['folderId'] ?? '').toString(),
      type: (j['type'] ?? '').toString(),
      size: _toInt(j['size']),
      contentBase64: rawContent?.toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  static int _toInt(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? 0;
  }
}

/// Repository for the Knowledge Base module. Each method calls the
/// `api/knowledge-base.php` endpoint and returns decoded domain objects.
class KnowledgeBaseRepository {
  KnowledgeBaseRepository(this._api);
  final ApiClient _api;

  static const _endpoint = '/api/knowledge-base.php';

  /// Top-level folders for the current user.
  Future<List<KbFolder>> listFolders() async {
    final data = await _api.get(_endpoint, query: {'action': 'list_folders'});
    final folders = _readList(data, 'folders');
    return folders.map((f) => KbFolder.fromJson(f)).toList();
  }

  /// Files in a folder (metadata only — use [fetchFile] for content).
  Future<List<KbFile>> listFiles(String folderId) async {
    final data = await _api.get(_endpoint, query: {
      'action': 'list_files',
      'folderId': folderId,
    });
    final files = _readList(data, 'files');
    return files.map((f) => KbFile.fromJson(f)).toList();
  }

  /// Full file record including base64 content.
  Future<KbFile> fetchFile(String id) async {
    final data = await _api.get(_endpoint, query: {
      'action': 'get_file',
      'id': id,
    });
    final file = (data is Map ? data['file'] : null);
    if (file is! Map) {
      throw StateError('File not found');
    }
    return KbFile.fromJson(file.cast<String, dynamic>());
  }

  Future<KbFolder> createFolder(String name) async {
    final data = await _api.post(_endpoint, query: {'action': 'create_folder'}, body: {
      'name': name,
    });
    final folder = (data is Map ? data['folder'] : null);
    if (folder is! Map) {
      throw StateError('Folder was not returned');
    }
    return KbFolder.fromJson(folder.cast<String, dynamic>());
  }

  /// Create a text file. [content] is plain text (encoded base64 on the wire).
  Future<KbFile> createFile({
    required String folderId,
    required String name,
    required String content,
  }) async {
    final data = await _api.post(_endpoint, query: {'action': 'upload_file'}, body: {
      'folderId': folderId,
      'name': name,
      'content': base64.encode(utf8.encode(content)),
    });
    final file = (data is Map ? data['file'] : null);
    if (file is! Map) {
      throw StateError('File was not returned');
    }
    return KbFile.fromJson(file.cast<String, dynamic>());
  }

  /// Update a file's name and/or content. [content], when provided, is plain text.
  Future<void> updateFile({
    required String id,
    String? name,
    String? content,
  }) async {
    await _api.put(_endpoint, query: {'action': 'update_file', 'id': id}, body: {
      if (name != null) 'name': name,
      if (content != null) 'content': base64.encode(utf8.encode(content)),
    });
  }

  Future<void> deleteFile(String id) async {
    await _api.delete(_endpoint, query: {'action': 'delete_file', 'id': id});
  }

  Future<void> deleteFolder(String id) async {
    await _api.delete(_endpoint, query: {'action': 'delete_folder', 'id': id});
  }

  /// Unwrap the backend's `{<key>:[...]}` envelope into a typed list.
  List<Map<String, dynamic>> _readList(dynamic data, String key) {
    if (data is! Map) return const [];
    final list = data[key];
    if (list is! List) return const [];
    return list
        .whereType<Map>()
        .map((m) => m.cast<String, dynamic>())
        .toList(growable: false);
  }
}
