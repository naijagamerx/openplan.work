/// Strips HTML markup from note content so notes created in the web rich-text
/// editor don't render as raw `<div>`/`<p>`/`<br>` tags in the Flutter app.
///
/// Mirrors what the web app's browser does when displaying notes: tags become
/// structure, entities become characters. Used for both display and the editor
/// field (so editing happens in clean plain text).
library;

/// Convert HTML to plain text: drop tags, convert block tags to line breaks,
/// decode common HTML entities, and collapse excess whitespace.
String stripHtml(String input) {
  if (input.isEmpty) return input;

  var s = input;

  // Convert HTML tables → markdown pipe tables FIRST, so table structure
  // survives (the generic tag-stripping below would otherwise flatten a table
  // into a run of loose lines). Markdown tables render via flutter_markdown's
  // gitHubWeb extension in the note preview.
  s = _htmlTablesToMarkdown(s);

  // Block-level tags → newline so paragraphs survive.
  s = s.replaceAll(
      RegExp(r'</?(p|div|br|li|h[1-6]|tr|table|hr)[^>]*>', caseSensitive: false),
      '\n');

  // <li> bullets.
  s = s.replaceAll(
      RegExp(r'<li[^>]*>', caseSensitive: false), '• ');

  // Strip all remaining tags.
  s = s.replaceAll(RegExp(r'<[^>]+>'), '');

  // Decode the common HTML entities.
  const entities = {
    '&nbsp;': ' ',
    '&amp;': '&',
    '&lt;': '<',
    '&gt;': '>',
    '&quot;': '"',
    '&#39;': "'",
    '&apos;': "'",
    '&hellip;': '…',
    '&mdash;': '—',
    '&ndash;': '–',
    '&laquo;': '«',
    '&raquo;': '»',
    '&copy;': '©',
    '&reg;': '®',
    '&trade;': '™',
    '&deg;': '°',
    '&euro;': '€',
    '&pound;': '£',
    '&cent;': '¢',
    '&times;': '×',
    '&divide;': '÷',
  };
  entities.forEach((entity, replacement) {
    s = s.replaceAll(entity, replacement);
  });
  // Numeric entities: &#123; / &#x7B;
  s = s.replaceAllMapped(RegExp(r'&#(\d+);'), (m) {
    final code = int.tryParse(m.group(1) ?? '');
    if (code == null) return m.group(0)!;
    return String.fromCharCode(code);
  });
  s = s.replaceAllMapped(RegExp(r'&#x([0-9a-fA-F]+);'), (m) {
    final code = int.tryParse(m.group(1) ?? '', radix: 16);
    if (code == null) return m.group(0)!;
    return String.fromCharCode(code);
  });

  // Collapse 3+ newlines down to 2, trim trailing spaces per line.
  s = s.replaceAll(RegExp(r'\n{3,}'), '\n\n');
  s = s.split('\n').map((line) => line.trimRight()).join('\n');

  return s.trim();
}

/// Convert every `<table>…</table>` block into a GitHub-flavored markdown pipe
/// table so the structure survives ingest and renders in the note preview.
/// Well-formed tables only (the app's own editor output); anything unparseable
/// is left untouched for the generic stripper to flatten.
String _htmlTablesToMarkdown(String input) {
  final tableRe =
      RegExp(r'<table[^>]*>(.*?)</table>', caseSensitive: false, dotAll: true);
  final rowRe = RegExp(r'<tr[^>]*>(.*?)</tr>', caseSensitive: false, dotAll: true);
  final cellRe =
      RegExp(r'<t[hd][^>]*>(.*?)</t[hd]>', caseSensitive: false, dotAll: true);

  return input.replaceAllMapped(tableRe, (tm) {
    final inner = tm.group(1) ?? '';
    final rows = <List<String>>[];
    for (final rm in rowRe.allMatches(inner)) {
      final rowHtml = rm.group(1) ?? '';
      final cells = <String>[];
      for (final cm in cellRe.allMatches(rowHtml)) {
        final cell = (cm.group(1) ?? '')
            .replaceAll(RegExp(r'<[^>]+>'), '') // drop inner tags
            .replaceAll(RegExp(r'\s+'), ' ')
            .trim()
            .replaceAll('|', r'\|'); // escape pipes so cells don't split
        cells.add(cell.isEmpty ? ' ' : cell);
      }
      if (cells.isNotEmpty) rows.add(cells);
    }
    if (rows.isEmpty) return tm.group(0)!; // unparseable — leave for stripper

    final cols = rows.first.length;
    final buf = StringBuffer('\n');
    buf.writeln('| ${rows.first.join(' | ')} |');
    buf.writeln('| ${List.filled(cols, '---').join(' | ')} |');
    for (final r in rows.skip(1)) {
      final padded =
          List<String>.generate(cols, (i) => i < r.length ? r[i] : ' ');
      buf.writeln('| ${padded.join(' | ')} |');
    }
    buf.write('\n');
    return buf.toString();
  });
}
