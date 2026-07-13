import 'package:flutter/material.dart';

import '../utils/html_stripper.dart';

/// A note as returned by `GET /api/notes.php`. Mirrors the backend schema
/// (see includes/NotesAPI.php::getAllowedFields). Notes are plain rich-text
/// blobs with tags, a color, and pin/favorite flags.
class Note {
  Note({
    required this.id,
    required this.title,
    this.content = '',
    this.tags = const [],
    this.color,
    this.isPinned = false,
    this.isFavorite = false,
    this.linkedEntityType,
    this.linkedEntityId,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String title;
  final String content; // max 10000 chars per the API
  final List<String> tags; // normalized lower-case server-side
  final String? color; // hex, e.g. '#fef3c7'
  final bool isPinned;
  final bool isFavorite;
  final String? linkedEntityType;
  final String? linkedEntityId;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// First ~80 chars of content, whitespace-trimmed, for list previews.
  String get preview {
    final raw = content.replaceAll(RegExp(r'\s+'), ' ').trim();
    if (raw.length <= 80) return raw;
    return '${raw.substring(0, 80).trim()}…';
  }

  bool get hasContent => content.trim().isNotEmpty;

  /// Parsed [Color] for [color]; null when unset or unparseable.
  Color? get colorValue => parseHex(color);

  factory Note.fromJson(Map<String, dynamic> j) {
    final rawTags = j['tags'];
    List<String> tags;
    if (rawTags is List) {
      tags = rawTags.map((t) => t.toString()).toList();
    } else if (rawTags is String && rawTags.isNotEmpty) {
      tags = rawTags
          .split(',')
          .map((t) => t.trim())
          .where((t) => t.isNotEmpty)
          .toList();
    } else {
      tags = const [];
    }
    return Note(
      id: (j['id'] ?? '').toString(),
      title: (j['title'] ?? '').toString(),
      // Notes created in the web rich-text editor store HTML; strip it so the
      // native editor never shows raw <div>/<p> markup.
      content: stripHtml((j['content'] ?? '').toString()),
      tags: tags,
      color: _normalizeHex(j['color']),
      isPinned: j['isPinned'] == true,
      isFavorite: j['isFavorite'] == true,
      linkedEntityType: j['linkedEntityType']?.toString(),
      linkedEntityId: j['linkedEntityId']?.toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  /// Build a JSON body for POST/PUT to /api/notes.php.
  Map<String, dynamic> toBody() => {
        'title': title,
        'content': content,
        'tags': tags,
        if (color != null) 'color': color,
        'isPinned': isPinned,
        'isFavorite': isFavorite,
      };

  Note copyWith({
    String? title,
    String? content,
    List<String>? tags,
    String? color,
    bool? isPinned,
    bool? isFavorite,
    String? linkedEntityType,
    String? linkedEntityId,
  }) {
    return Note(
      id: id,
      title: title ?? this.title,
      content: content ?? this.content,
      tags: tags ?? this.tags,
      color: color ?? this.color,
      isPinned: isPinned ?? this.isPinned,
      isFavorite: isFavorite ?? this.isFavorite,
      linkedEntityType: linkedEntityType ?? this.linkedEntityType,
      linkedEntityId: linkedEntityId ?? this.linkedEntityId,
      createdAt: createdAt,
      updatedAt: updatedAt,
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  /// Normalize a hex string to `#rrggbb`, dropping anything malformed/empty.
  static String? _normalizeHex(dynamic v) {
    if (v == null) return null;
    var s = v.toString().trim();
    if (s.isEmpty) return null;
    if (!s.startsWith('#')) s = '#$s';
    return s;
  }
}

/// Parse a `#rrggbb` / `#rgb` / `rrggbb` hex string into a [Color], or null.
Color? parseHex(String? hex) {
  if (hex == null) return null;
  var s = hex.trim();
  if (s.isEmpty) return null;
  if (s.startsWith('#')) s = s.substring(1);
  if (s.length == 3) {
    s = s.split('').map((c) => c + c).join();
  }
  if (s.length != 6) return null;
  final value = int.tryParse('FF$s', radix: 16);
  if (value == null) return null;
  return Color(value);
}

/// Preset swatch options offered in the note color picker (hex, monochrome-friendly).
const List<String> kNoteColorSwatches = [
  '#fef3c7', // amber-100
  '#fee2e2', // red-100
  '#dcfce7', // green-100
  '#dbeafe', // blue-100
  '#f3e8ff', // purple-100
  '#fce7f3', // pink-100
];
