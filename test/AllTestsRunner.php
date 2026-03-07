<?php
/**
 * Individual Test Suite Runner for LazyMan Tools
 */

echo "🧪 LazyMan Tools - Comprehensive Test Suite Report\n";
echo "===============================================\n\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$testSuites = [
    'Encryption' => 'test/EncryptionTest.php',
    'Database' => 'test/DatabaseTest.php',
    'Auth' => 'test/AuthTest.php',
    'Helpers' => 'test/HelpersTest.php',
    'API' => 'test/ApiTest.php'
];

$results = [
    'totalSuites' => count($testSuites),
    'passedSuites' => 0,
    'failedSuites' => 0,
    'totalTests' => 0,
    'totalPassed' => 0,
    'totalFailed' => 0,
    'totalErrors' => 0,
    'suiteDetails' => []
];

foreach ($testSuites as $suiteName => $testFile) {
    echo "Running {$suiteName} Tests...\n";
    echo str_repeat("-", 50) . "\n";

    // Capture the output from each test suite
    ob_start();
    $exitCode = 0;

    // Use a separate process to avoid conflicts
    $command = "php -f " . escapeshellarg(__DIR__ . "/" . $testFile) . " 2>&1";
    $output = shell_exec($command);

    $suiteOutput = ob_get_clean();

    // Extract test results from the output
    $passed = substr_count($output ?? $suiteOutput, 'PASSED');
    $failed = substr_count($output ?? $suiteOutput, 'FAILED');
    $errors = substr_count($output ?? $suiteOutput, 'ERROR');
    $total = $passed + $failed + $errors;

    echo "Results for {$suiteName}:\n";
    echo "  Total Tests: {$total}\n";
    echo "  Passed: {$passed}\n";
    echo "  Failed: {$failed}\n";
    echo "  Errors: {$errors}\n";

    if ($failed === 0 && $errors === 0) {
        echo "  Status: ✅ PASSED\n\n";
        $results['passedSuites']++;
    } else {
        echo "  Status: ❌ FAILED/ERRORS\n\n";
        $results['failedSuites']++;
    }

    $results['totalTests'] += $total;
    $results['totalPassed'] += $passed;
    $results['totalFailed'] += $failed;
    $results['totalErrors'] += $errors;

    $results['suiteDetails'][$suiteName] = [
        'total' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'errors' => $errors
    ];
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 FINAL TEST REPORT\n";
echo str_repeat("=", 60) . "\n";

echo "\nSUMMARY BY SUITE:\n";
foreach ($results['suiteDetails'] as $suite => $stats) {
    $passRate = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 1) : 0;
    $status = ($stats['failed'] === 0 && $stats['errors'] === 0) ? '✅' : '❌';
    echo "{$status} {$suite}: {$stats['passed']}/{$stats['total']} tests passed ({$passRate}%)\n";
}

echo "\nOVERALL SUMMARY:\n";
echo "✅ Test Suites Passed: {$results['passedSuites']}/{$results['totalSuites']}\n";
echo "📈 Total Tests Run: {$results['totalTests']}\n";
echo "✅ Total Tests Passed: {$results['totalPassed']}\n";
echo "❌ Total Tests Failed: {$results['totalFailed']}\n";
echo "⚠️  Total Errors: {$results['totalErrors']}\n";

$overallPassRate = $results['totalTests'] > 0 ? round(($results['totalPassed'] / $results['totalTests']) * 100, 1) : 0;
echo "🎯 Overall Success Rate: {$overallPassRate}%\n";

echo "\n" . str_repeat("=", 60) . "\n";

if ($results['failedSuites'] === 0 && $results['totalFailed'] === 0 && $results['totalErrors'] === 0) {
    echo "🎉 ALL TESTS PASSED! The LazyMan Tools application is functioning correctly.\n";
    echo "✅ Core functionality is working as expected\n";
    echo "✅ Security features (encryption/auth) are operational\n";
    echo "✅ API endpoints are responding properly\n";
    echo "✅ Helper functions are operating correctly\n";
} else {
    echo "⚠️  SOME TESTS FAILED. Please review the detailed results above.\n";
    if ($results['totalFailed'] > 0) {
        echo "• {$results['totalFailed']} tests failed - check assertions\n";
    }
    if ($results['totalErrors'] > 0) {
        echo "• {$results['totalErrors']} errors occurred - check for exceptions\n";
    }
    if ($results['failedSuites'] > 0) {
        echo "• {$results['failedSuites']} test suites did not pass completely\n";
    }
    echo "\nRECOMMENDATION: Fix failing tests before deployment.\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test execution completed at " . date('Y-m-d H:i:s') . "\n";
?>