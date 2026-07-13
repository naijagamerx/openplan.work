/// Helpers for the rich-content blocks supported in note bodies: tables,
/// checklists, and image data-URIs. These render as plain-text markdown-style
/// fragments embedded in the note `content` string (so they survive the
/// plain-text storage and round-trip through the API).
library;

/// Insert a markdown table snippet at the end of [content]. Returns the new
/// content. The table has the given number of columns and one header row +
/// [rows] empty body rows.
String insertTable(String content, {int columns = 3, int rows = 2}) {
  final cols = columns.clamp(1, 6);
  final buf = StringBuffer();
  if (content.isNotEmpty && !content.endsWith('\n')) buf.writeln();
  buf.writeln();
  // Header
  buf.write('| ');
  buf.write(List.filled(cols, 'Column').join(' | '));
  buf.writeln(' |');
  // Separator
  buf.write('| ');
  buf.write(List.filled(cols, '---').join(' | '));
  buf.writeln(' |');
  // Body rows
  for (var r = 0; r < rows; r++) {
    buf.write('| ');
    buf.write(List.filled(cols, ' ').join(' | '));
    buf.writeln(' |');
  }
  return content + buf.toString();
}

/// Insert a checklist block (N `- [ ]` items) at the end of [content].
String insertChecklist(String content, {int items = 3}) {
  final buf = StringBuffer();
  if (content.isNotEmpty && !content.endsWith('\n')) buf.writeln();
  buf.writeln();
  for (var i = 0; i < items; i++) {
    buf.writeln('- [ ] Checklist item ${i + 1}');
  }
  return content + buf.toString();
}

/// Insert an inline image (as a base64 data-URI markdown image) at the end of
/// [content]. [bytes] is the raw image file; [mime] is its content type.
String insertImage(String content, List<int> bytes, String mime) {
  final b64 = _encodeBase64(bytes);
  final buf = StringBuffer();
  if (content.isNotEmpty && !content.endsWith('\n')) buf.writeln();
  buf.writeln();
  buf.writeln('![image](data:$mime;base64,$b64)');
  buf.writeln();
  return content + buf.toString();
}

/// Append the text of another note (import-recent) at the end of [content].
String appendNote(String content, String otherTitle, String otherContent) {
  final buf = StringBuffer();
  if (content.isNotEmpty && !content.endsWith('\n')) buf.writeln();
  buf.writeln();
  buf.writeln('---');
  buf.writeln('**$otherTitle**');
  buf.writeln();
  buf.writeln(otherContent);
  return content + buf.toString();
}

String _encodeBase64(List<int> bytes) {
  // Base64 encode without dart:io dependency on the codec name.
  const alphabet =
      'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
  final out = StringBuffer();
  for (var i = 0; i < bytes.length; i += 3) {
    final b1 = bytes[i];
    final b2 = i + 1 < bytes.length ? bytes[i + 1] : 0;
    final b3 = i + 2 < bytes.length ? bytes[i + 2] : 0;
    out.write(alphabet[(b1 >> 2) & 0x3F]);
    out.write(alphabet[((b1 << 4) | (b2 >> 4)) & 0x3F]);
    out.write(i + 1 < bytes.length ? alphabet[((b2 << 2) | (b3 >> 6)) & 0x3F] : '=');
    out.write(i + 2 < bytes.length ? alphabet[b3 & 0x3F] : '=');
  }
  return out.toString();
}

/// Count how many checklist items appear in [content] and how many are checked.
({int total, int done}) checklistStats(String content) {
  final re = RegExp(r'^\s*[-*] \[( |x|X)\] ');
  var total = 0;
  var done = 0;
  for (final line in content.split('\n')) {
    final m = re.firstMatch(line);
    if (m != null) {
      total++;
      if (m.group(1)!.toLowerCase() == 'x') done++;
    }
  }
  return (total: total, done: done);
}
