<?php
/**
 * Documentation PDF Generator
 * Converts OpenPlan Work documentation to PDF
 */

require_once __DIR__ . '/../includes/Helpers.php';

// Documentation file path
$markdownFile = __DIR__ . '/../docs/OpenPlan-Work-Complete-Documentation.md';
$outputPdf = __DIR__ . '/../docs/OpenPlan-Work-Documentation.pdf';

if (!file_exists($markdownFile)) {
    die("Error: Documentation file not found at $markdownFile\n");
}

// Read markdown content
$markdown = file_get_contents($markdownFile);

// Convert markdown to HTML
$html = markdownToHtml($markdown);

// Add CSS styling
$html = wrapInHtmlTemplate($html);

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="OpenPlan-Work-Documentation.pdf"');
header('Content-Length: ' . filesize($outputPdf));

// Generate PDF using a browser-based approach
echo generatePdfFromHtml($html, $outputPdf);

/**
 * Convert Markdown to HTML
 */
function markdownToHtml($markdown) {
    $lines = explode("\n", $markdown);
    $html = '';
    $inCodeBlock = false;
    $inList = false;

    foreach ($lines as $line) {
        // Code blocks
        if (preg_match('/^```(\w+)?$/', $line)) {
            $inCodeBlock = !$inCodeBlock;
            if (!$inCodeBlock) {
                $html .= "</code></pre>\n";
            } else {
                $html .= "<pre><code>";
            }
            continue;
        }

        if ($inCodeBlock) {
            $html .= htmlspecialchars($line) . "\n";
            continue;
        }

        // Headers
        if (preg_match('/^(#{1})\s+(.+)$/', $line, $matches)) {
            $html .= "<h1>" . htmlspecialchars($matches[2]) . "</h1>\n";
        } elseif (preg_match('/^(#{2})\s+(.+)$/', $line, $matches)) {
            $html .= "<h2>" . htmlspecialchars($matches[2]) . "</h2>\n";
        } elseif (preg_match('/^(#{3})\s+(.+)$/', $line, $matches)) {
            $html .= "<h3>" . htmlspecialchars($matches[2]) . "</h3>\n";
        }
        // Tables
        elseif (preg_match('/^\|(.+)\|$/', $line, $matches)) {
            $cells = array_map('trim', explode('|', $matches[1]));
            if (empty($cells[0])) array_shift($cells);
            if (empty($cells[count($cells) - 1])) array_pop($cells);

            if (preg_match('/^\|[-:\s|]+\|$/', $line)) {
                continue; // Skip separator line
            }

            $html .= "<tr>";
            foreach ($cells as $cell) {
                $html .= "<td>" . htmlspecialchars($cell) . "</td>";
            }
            $html .= "</tr>\n";
        }
        // Lists
        elseif (preg_match('/^\*\s+(.+)$/', $line, $matches)) {
            if (!$inList) {
                $html .= "<ul>\n";
                $inList = true;
            }
            $html .= "<li>" . htmlspecialchars($matches[1]) . "</li>\n";
        }
        // Code blocks inline
        elseif (preg_match('/`(.+?)`/', $line, $matches)) {
            $line = preg_replace('/`(.+?)`/', '<code>$1</code>', $line);
            $html .= "<p>" . $line . "</p>\n";
        }
        // Horizontal rule
        elseif (preg_match('/^---+$/', $line)) {
            $html .= "<hr>\n";
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
        }
        // Regular paragraphs
        elseif (!empty(trim($line))) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            // Bold text
            $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
            $html .= "<p>" . $line . "</p>\n";
        }
    }

    if ($inList) {
        $html .= "</ul>\n";
    }

    return $html;
}

/**
 * Wrap HTML in proper template with CSS
 */
function wrapInHtmlTemplate($content) {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenPlan Work - Complete Documentation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
            background: #fff;
        }

        h1 {
            font-size: 2.5rem;
            color: #1e40af;
            margin-bottom: 10px;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 10px;
            page-break-after: avoid;
        }

        h2 {
            font-size: 1.8rem;
            color: #1e3a8a;
            margin-top: 40px;
            margin-bottom: 15px;
            page-break-after: avoid;
        }

        h3 {
            font-size: 1.3rem;
            color: #1e40af;
            margin-top: 25px;
            margin-bottom: 10px;
            page-break-after: avoid;
        }

        p {
            margin-bottom: 15px;
            text-align: justify;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            page-break-inside: avoid;
        }

        table:first-child {
            margin-top: 0;
        }

        td, th {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: 600;
            color: #1f2937;
        }

        tr:nth-child(even) {
            background: #f9fafb;
        }

        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: "Courier New", monospace;
            font-size: 0.9em;
            color: #dc2626;
        }

        pre {
            background: #1f2937;
            color: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 15px 0;
            page-break-inside: avoid;
        }

        pre code {
            background: transparent;
            color: #f9fafb;
            padding: 0;
        }

        ul {
            margin-left: 30px;
            margin-bottom: 15px;
        }

        li {
            margin-bottom: 8px;
        }

        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 30px 0;
            page-break-after: avoid;
        }

        strong {
            color: #1e40af;
            font-weight: 600;
        }

        @media print {
            body {
                padding: 0;
            }

            h1 {
                page-break-before: always;
            }

            h1:first-of-type {
                page-break-before: avoid;
            }

            h2, h3 {
                page-break-after: avoid;
            }

            table, pre, blockquote {
                page-break-inside: avoid;
            }

            a {
                text-decoration: none;
                color: #1e40af;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            page-break-after: avoid;
        }

        .version {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .toc {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            page-break-after: avoid;
        }

        .toc h3 {
            margin-top: 0;
            color: #1f2937;
        }

        .toc ul {
            list-style: none;
            margin-left: 0;
        }

        .toc li {
            margin-bottom: 8px;
        }

        .toc a {
            color: #1e40af;
            text-decoration: none;
        }

        .toc a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>OpenPlan Work</h1>
        <p class="version">Complete Documentation v1.0.0 | March 22, 2026</p>
        <p>Enterprise-grade PHP task management and business operations suite</p>
    </div>

    ' . $content . '

    <div style="margin-top: 60px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 0.85rem;">
        <p><strong>OpenPlan Work</strong> – MIT License</p>
        <p>https://github.com/naijagamerx/openplan.work</p>
        <p style="margin-top: 10px;">This documentation was automatically generated on March 22, 2026</p>
    </div>
</body>
</html>';
}

/**
 * Generate PDF from HTML
 */
function generatePdfFromHtml($html, $outputPath) {
    // Save HTML to temporary file
    $tempHtml = tempnam(sys_get_temp_dir(), 'docs_') . '.html';
    file_put_contents($tempHtml, $html);

    // Try to use wkhtmltopdf if available
    $wkhtmltopdf = exec('which wkhtmltopdf 2>/dev/null || where wkhtmltopdf 2>nul');

    if ($wkhtmltopdf && file_exists($wkhtmltopdf)) {
        exec("\"$wkhtmltopdf\" \"$tempHtml\" \"$outputPath\" 2>&1", $output, $returnCode);
        unlink($tempHtml);

        if ($returnCode === 0 && file_exists($outputPath)) {
            readfile($outputPath);
            return true;
        }
    }

    // Fallback: Display HTML with print instructions
    unlink($tempHtml);

    echo '<!DOCTYPE html>
<html>
<head>
    <title>OpenPlan Work - Documentation PDF</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
        .info { background: #fef3c7; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        h1 { color: #1e40af; }
        button { background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        button:hover { background: #2563eb; }
        #doc-content { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="info">
        <h1>Save as PDF</h1>
        <p>Click the button below and use your browser\'s "Save as PDF" option in the print dialog.</p>
        <button onclick="window.print()">📄 Open Print Dialog</button>
    </div>
    <div id="doc-content">' . $html . '</div>
</body>
</html>';

    return false;
}
