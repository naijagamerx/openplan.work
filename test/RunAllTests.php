<?php
/**
 * Master Test Runner - Executes all test suites and generates comprehensive report
 */

echo "<!DOCTYPE html><html><head>";
echo "<title>LazyMan Tools - Complete Test Suite Report</title>";
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f8fafc; }
    .container { max-width: 1200px; margin: 0 auto; }
    .header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .test-suite { background: white; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
    .suite-header { padding: 20px; border-bottom: 1px solid #e5e7eb; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background-color 0.2s; }
    .suite-header:hover { background-color: #f9fafb; }
    .suite-title { font-size: 18px; font-weight: 600; color: #1f2937; }
    .suite-status { padding: 6px 12px; border-radius: 20px; font-size: 14px; font-weight: 500; }
    .status-pass { background: #d1fae5; color: #065f46; }
    .status-fail { background: #fee2e2; color: #991b1b; }
    .status-error { background: #fed7aa; color: #92400e; }
    .suite-content { padding: 0; display: none; }
    .suite-details { padding: 20px; }
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
    .summary-card { background: #f3f4f6; padding: 20px; border-radius: 8px; text-align: center; }
    .summary-card.passed { background: #d1fae5; color: #065f46; }
    .summary-card.failed { background: #fee2e2; color: #991b1b; }
    .summary-card.errors { background: #fed7aa; color: #92400e; }
    .summary-number { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
    .summary-label { font-size: 14px; opacity: 0.8; }
    .progress-bar { width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin: 15px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #10b981 0%, #059669 100%); transition: width 0.3s ease; }
    .toggle-icon { transition: transform 0.2s; }
    .toggle-icon.rotated { transform: rotate(180deg); }
    .test-details { font-family: 'Courier New', monospace; font-size: 12px; background: #1f2937; color: #e5e7eb; padding: 15px; border-radius: 4px; margin: 10px 0; overflow-x: auto; }
    .loading { text-align: center; padding: 40px; color: #6b7280; }
    .spinner { border: 3px solid #e5e7eb; border-top: 3px solid #3b82f6; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .final-summary { background: linear-gradient(135deg, #1f2937 0%, #374151 100%); color: white; padding: 30px; border-radius: 12px; margin-top: 30px; }
    .recommendations { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 20px; margin: 20px 0; border-radius: 4px; }
    .recommendations h3 { color: #1e40af; margin-top: 0; }
</style>";
echo "<script>
    function toggleSuite(suiteId) {
        const content = document.getElementById('suite-' + suiteId);
        const icon = document.getElementById('icon-' + suiteId);

        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.classList.add('rotated');
        } else {
            content.style.display = 'none';
            icon.classList.remove('rotated');
        }
    }

    function expandAll() {
        document.querySelectorAll('.suite-content').forEach(el => el.style.display = 'block');
        document.querySelectorAll('.toggle-icon').forEach(el => el.classList.add('rotated'));
    }

    function collapseAll() {
        document.querySelectorAll('.suite-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.toggle-icon').forEach(el => el.classList.remove('rotated'));
    }
</script>";
echo "</head><body>";

echo "<div class='container'>";
echo "<div class='header'>";
echo "<h1>🧪 LazyMan Tools - Complete Test Suite</h1>";
echo "<p>Comprehensive testing of all PHP components and API endpoints</p>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";
echo "<div style='margin-top: 20px;'>";
echo "<button onclick='expandAll()' style='background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 16px; border-radius: 4px; margin-right: 10px; cursor: pointer;'>Expand All</button>";
echo "<button onclick='collapseAll()' style='background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;'>Collapse All</button>";
echo "</div>";
echo "</div>";

// Define test suites
$testSuites = [
    'Encryption' => ['file' => __DIR__ . '/EncryptionTest.php', 'description' => 'AES-256-GCM encryption/decryption functionality'],
    'Database' => ['file' => __DIR__ . '/DatabaseTest.php', 'description' => 'JSON file storage with encryption'],
    'Auth' => ['file' => __DIR__ . '/AuthTest.php', 'description' => 'Authentication and session management'],
    'Helpers' => ['file' => __DIR__ . '/HelpersTest.php', 'description' => 'Utility functions and helpers'],
    'API' => ['file' => __DIR__ . '/ApiTest.php', 'description' => 'REST API endpoints and functionality']
];

// Capture results for summary
$overallResults = [
    'totalTests' => 0,
    'passed' => 0,
    'failed' => 0,
    'errors' => 0,
    'suites' => []
];

// Run each test suite
foreach ($testSuites as $suiteName => $suiteInfo) {
    echo "<div class='test-suite'>";
    echo "<div class='suite-header' onclick='toggleSuite(\"{$suiteName}\")'>";
    echo "<div>";
    echo "<div class='suite-title'>{$suiteName} Tests</div>";
    echo "<div style='color: #6b7280; font-size: 14px; margin-top: 4px;'>{$suiteInfo['description']}</div>";
    echo "</div>";
    echo "<div style='display: flex; align-items: center; gap: 10px;'>";
    echo "<span id='status-{$suiteName}' class='loading'>Running...</span>";
    echo "<span id='icon-{$suiteName}' class='toggle-icon'>▼</span>";
    echo "</div>";
    echo "</div>";
    echo "<div id='suite-{$suiteName}' class='suite-content'>";
    echo "<div class='suite-details'>";
    echo "<div class='loading'>";
    echo "<div class='spinner'></div>";
    echo "<p>Running {$suiteName} tests...</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";

    flush(); // Send output to browser
}

echo "</div>"; // Close container

// Now run the actual tests and update the page
echo "<script>";

foreach ($testSuites as $suiteName => $suiteInfo) {
    echo "
    (function() {
        const suiteName = '{$suiteName}';
        const statusEl = document.getElementById('status-' + suiteName);
        const detailsEl = document.querySelector('#suite-' + suiteName + ' .suite-details');

        // Simulate running tests (in real scenario, this would be AJAX calls)
        setTimeout(function() {
            // Capture test output
            let testOutput = '';
            let testResults = { passed: 0, failed: 0, errors: 0, total: 0 };

            try {
                // Use output buffering to capture test results
                let originalOutput = '';
                // This is a simplified version - in reality, you'd make AJAX calls
            </script>";

    // Actually run the test suite and capture output
    ob_start();
    include $suiteInfo['file'];
    $testOutput = ob_get_clean();

    // Parse results from the test output (simplified)
    $passedCount = substr_count($testOutput, 'PASSED');
    $failedCount = substr_count($testOutput, 'FAILED');
    $errorsCount = substr_count($testOutput, 'ERRORS');
    $totalCount = $passedCount + $failedCount + $errorsCount;

    $overallResults['totalTests'] += $totalCount;
    $overallResults['passed'] += $passedCount;
    $overallResults['failed'] += $failedCount;
    $overallResults['errors'] += $errorsCount;
    $overallResults['suites'][$suiteName] = [
        'passed' => $passedCount,
        'failed' => $failedCount,
        'errors' => $errorsCount,
        'total' => $totalCount
    ];

    echo "
                testResults = { passed: {$passedCount}, failed: {$failedCount}, errors: {$errorsCount}, total: {$totalCount} };
                testOutput = `" . addslashes($testOutput) . "`;

                // Update status
                let statusClass = 'status-pass';
                let statusText = 'PASSED';

                if (testResults.failed > 0 || testResults.errors > 0) {
                    statusClass = testResults.errors > 0 ? 'status-error' : 'status-fail';
                    statusText = testResults.errors > 0 ? 'ERRORS' : 'FAILED';
                }

                statusEl.className = 'suite-status ' + statusClass;
                statusEl.textContent = statusText + ' (' + testResults.total + ' tests)';

                // Update details
                detailsEl.innerHTML = testOutput;

            } catch (error) {
                statusEl.className = 'suite-status status-error';
                statusEl.textContent = 'ERROR';
                detailsEl.innerHTML = '<div class=\"test-details\">Error running tests: ' + error.message + '</div>';
            }
        }, Math.random() * 1000 + 500); // Random delay for visual effect
    })();
    ";
}

echo "
    // Update overall summary after all tests complete
    setTimeout(function() {
        const summaryHtml = `
            <div class='final-summary'>
                <h2>📊 Overall Test Results</h2>
                <div class='summary-grid'>
                    <div class='summary-card passed'>
                        <div class='summary-number'>{$overallResults['passed']}</div>
                        <div class='summary-label'>PASSED</div>
                    </div>
                    <div class='summary-card failed'>
                        <div class='summary-number'>{$overallResults['failed']}</div>
                        <div class='summary-label'>FAILED</div>
                    </div>
                    <div class='summary-card errors'>
                        <div class='summary-number'>{$overallResults['errors']}</div>
                        <div class='summary-label'>ERRORS</div>
                    </div>
                    <div class='summary-card'>
                        <div class='summary-number'>{$overallResults['totalTests']}</div>
                        <div class='summary-label'>TOTAL TESTS</div>
                    </div>
                    <div class='summary-card'>
                        <div class='summary-number'>" . round(($overallResults['passed'] / max($overallResults['totalTests'], 1)) * 100, 1) . "%</div>
                        <div class='summary-label'>SUCCESS RATE</div>
                    </div>
                </div>
                <div class='progress-bar'>
                    <div class='progress-fill' style='width: " . round(($overallResults['passed'] / max($overallResults['totalTests'], 1)) * 100, 1) . "%'></div>
                </div>";

echo "
                <div class='recommendations'>
                    <h3>🔧 Recommendations</h3>";

if ($overallResults['failed'] > 0) {
    echo "<p>• {$overallResults['failed']} test(s) failed - Review failing assertions and fix implementation issues</p>";
}
if ($overallResults['errors'] > 0) {
    echo "<p>• {$overallResults['errors']} test(s) had errors - Check for exceptions and error handling</p>";
}
if ($overallResults['passed'] === $overallResults['totalTests'] && $overallResults['totalTests'] > 0) {
    echo "<p>✅ All tests passed! The codebase is functioning correctly.</p>";
}
if ($overallResults['totalTests'] === 0) {
    echo "<p>⚠️ No tests were executed - Check test configuration and setup</p>";
}

echo "
                    <p>• Continue adding test coverage for new features and edge cases</p>
                    <p>• Consider adding integration tests for complete user workflows</p>
                    <p>• Set up automated testing in CI/CD pipeline for continuous validation</p>
                </div>
            </div>
        `;

        document.querySelector('.container').insertAdjacentHTML('beforeend', summaryHtml);
    }, 3000);
</script>";

echo "</body></html>";

// Also generate a CLI report
echo "\n\n=== CLI TEST REPORT ===\n";
echo "Test Suite Execution Summary\n";
echo "============================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($overallResults['suites'] as $suiteName => $results) {
    echo "{$suiteName} Suite:\n";
    echo "  Total: {$results['total']}\n";
    echo "  Passed: {$results['passed']}\n";
    echo "  Failed: {$results['failed']}\n";
    echo "  Errors: {$results['errors']}\n";
    $passRate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 1) : 0;
    echo "  Success Rate: {$passRate}%\n\n";
}

echo "OVERALL SUMMARY:\n";
echo "================\n";
echo "Total Tests: {$overallResults['totalTests']}\n";
echo "Total Passed: {$overallResults['passed']}\n";
echo "Total Failed: {$overallResults['failed']}\n";
echo "Total Errors: {$overallResults['errors']}\n";
$overallPassRate = $overallResults['totalTests'] > 0 ? round(($overallResults['passed'] / $overallResults['totalTests']) * 100, 1) : 0;
echo "Overall Success Rate: {$overallPassRate}%\n\n";

if ($overallResults['failed'] > 0 || $overallResults['errors'] > 0) {
    echo "⚠️  ISSUES FOUND:\n";
    if ($overallResults['failed'] > 0) {
        echo "- {$overallResults['failed']} test(s) failed\n";
    }
    if ($overallResults['errors'] > 0) {
        echo "- {$overallResults['errors']} test(s) encountered errors\n";
    }
    echo "\nRECOMMENDATION: Review failing tests and fix issues before deployment.\n";
} else {
    echo "✅ ALL TESTS PASSED! System is functioning correctly.\n";
}

echo "\nTest execution completed successfully.\n";
?>