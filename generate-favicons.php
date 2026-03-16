<?php
/**
 * Generate favicon files from the four-square logo design
 * Run this script to create the initial favicon files
 */

// Ensure GD is available
if (!extension_loaded('gd')) {
    echo "Error: GD extension is required\n";
    exit(1);
}

$faviconDir = __DIR__ . '/assets/favicons/';

// Create directory if it doesn't exist
if (!is_dir($faviconDir)) {
    if (!mkdir($faviconDir, 0755, true)) {
        echo "Error: Could not create favicons directory\n";
        exit(1);
    }
}

// Generate four-square logo favicon (matching mobile app design)
$sizes = [
    ['w' => 16, 'h' => 16, 'name' => 'favicon-16x16.png'],
    ['w' => 32, 'h' => 32, 'name' => 'favicon-32x32.png'],
    ['w' => 180, 'h' => 180, 'name' => 'apple-touch-icon.png'],
];

foreach ($sizes as $size) {
    $w = $size['w'];
    $h = $size['h'];
    $img = imagecreatetruecolor($w, $h);

    // Colors (matching mobile: black background, white squares)
    $black = imagecolorallocate($img, 0, 0, 0);
    $white = imagecolorallocate($img, 255, 255, 255);
    $white50 = imagecolorallocate($img, 128, 128, 128); // 50% opacity white

    // Fill background with black
    imagefilledrectangle($img, 0, 0, $w, $h, $black);

    // Calculate grid dimensions
    $centerX = $w / 2;
    $centerY = $h / 2;
    $gap = max(1, round($w * 0.04)); // Small gap between squares
    $squareSize = ($w - $gap * 3) / 2; // Two squares + gap on each side

    // Top-left: Full white
    $tlX = $gap;
    $tlY = $gap;
    imagefilledrectangle($img, $tlX, $tlY, $tlX + $squareSize, $tlY + $squareSize, $white);

    // Bottom-right: Full white
    $brX = $centerX + $gap / 2;
    $brY = $centerY + $gap / 2;
    imagefilledrectangle($img, $brX, $brY, $brX + $squareSize, $brY + $squareSize, $white);

    // Top-right: 50% white
    $trX = $centerX + $gap / 2;
    $trY = $gap;
    imagefilledrectangle($img, $trX, $trY, $trX + $squareSize, $trY + $squareSize, $white50);

    // Bottom-left: 50% white
    $blX = $gap;
    $blY = $centerY + $gap / 2;
    imagefilledrectangle($img, $blX, $blY, $blX + $squareSize, $blY + $squareSize, $white50);

    imagepng($img, $faviconDir . $size['name']);
    imagedestroy($img);

    echo "Created: {$size['name']}\n";
}

// Create ICO file (basic ICO header + PNG data)
$pngPath = $faviconDir . 'favicon-32x32.png';
$icoPath = $faviconDir . 'favicon.ico';

$pngData = file_get_contents($pngPath);
$pngSize = strlen($pngData);

// ICO header: reserved (2) + type (2) + count (2) = 6 bytes
$header = pack('v', 0);      // Reserved
$header .= pack('v', 1);     // Type: 1 = icon
$header .= pack('v', 1);     // Count: 1 image

// Directory entry: width (1) + height (1) + colors (1) + reserved (1) + planes (2) + bpp (2) + size (4) + offset (4) = 16 bytes
$width = 32;
$height = 32;
$entry = pack('C', $width);
$entry .= pack('C', $height);
$entry .= pack('C', 0);      // Colors (0 = more than 256)
$entry .= pack('C', 0);      // Reserved
$entry .= pack('v', 1);      // Color planes
$entry .= pack('v', 32);     // Bits per pixel
$entry .= pack('V', $pngSize); // Size of image data
$entry .= pack('V', 22);     // Offset to image data (6 + 16)

// Write ICO file
file_put_contents($icoPath, $header . $entry . $pngData);

echo "Created: favicon.ico\n";
echo "\nFavicon files generated successfully!\n";
