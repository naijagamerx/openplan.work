<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$phpunitEntrypoint = $root . '/vendor/phpunit/phpunit/phpunit';

if (!is_file($phpunitEntrypoint)) {
    fwrite(STDERR, "PHPUnit entrypoint not found at {$phpunitEntrypoint}\n");
    exit(1);
}

$candidates = [
    PHP_BINARY,
    $root . '/php/php.exe',
    'C:/MAMP/bin/php/php8.3.1/php.exe',
    'C:/MAMP/bin/php/php8.3.0/php.exe',
    'C:/MAMP/bin/php/php8.2.14/php.exe',
];

$candidates = array_values(array_unique(array_filter($candidates, static function (string $path): bool {
    return $path !== '' && file_exists($path);
})));

$selectedPhp = PHP_BINARY;
foreach ($candidates as $candidate) {
    $output = [];
    $code = 0;
    @exec('"' . $candidate . '" -m', $output, $code);
    if ($code === 0) {
        $modules = array_map('strtolower', $output);
        if (in_array('openssl', $modules, true)) {
            $selectedPhp = $candidate;
            break;
        }
    }
}

$args = array_slice($_SERVER['argv'], 1);
$escapedArgs = implode(' ', array_map(static fn(string $arg): string => escapeshellarg($arg), $args));
$command = '"' . $selectedPhp . '" "' . $phpunitEntrypoint . '"' . ($escapedArgs !== '' ? ' ' . $escapedArgs : '');

fwrite(STDOUT, "Using PHP runtime: {$selectedPhp}\n");
passthru($command, $exitCode);
exit($exitCode);
