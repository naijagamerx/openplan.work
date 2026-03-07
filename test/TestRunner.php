<?php
/**
 * LazyMan Tools - Custom Unit Test Runner
 */

class TestRunner {
    private array $results = [];
    private string $currentSuite = '';

    public function runSuite(string $name, callable $tests) {
        $this->currentSuite = $name;
        $this->results[$name] = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        echo "Running Suite: {$name}\n";
        $tests($this);
        echo "\n";
    }

    public function assert(string $message, bool $condition) {
        $this->results[$this->currentSuite]['total']++;
        if ($condition) {
            $this->results[$this->currentSuite]['passed']++;
            echo "  ✅ {$message}\n";
        } else {
            $this->results[$this->currentSuite]['failed']++;
            echo "  ❌ {$message}\n";
        }
    }

    public function reportError(string $message) {
        $this->results[$this->currentSuite]['total']++;
        $this->results[$this->currentSuite]['errors'][] = $message;
        echo "  ⚠️ ERROR: {$message}\n";
    }

    public function getResults(): array {
        return $this->results;
    }

    public function printSummary() {
        echo "\n--- TEST SUMMARY ---\n";
        $grandTotal = 0;
        $grandPassed = 0;
        $grandFailed = 0;
        $grandErrors = 0;

        foreach ($this->results as $suite => $data) {
            $errorsCount = count($data['errors']);
            echo sprintf(
                "%-20s | Total: %d | Passed: %d | Failed: %d | Errors: %d\n",
                $suite, $data['total'], $data['passed'], $data['failed'], $errorsCount
            );
            $grandTotal += $data['total'];
            $grandPassed += $data['passed'];
            $grandFailed += $data['failed'];
            $grandErrors += $errorsCount;
        }

        echo str_repeat("-", 60) . "\n";
        echo sprintf(
            "%-20s | Total: %d | Passed: %d | Failed: %d | Errors: %d\n",
            "GRAND TOTAL", $grandTotal, $grandPassed, $grandFailed, $grandErrors
        );
        
        $successRate = $grandTotal > 0 ? ($grandPassed / $grandTotal) * 100 : 0;
        echo "Success Rate: " . number_format($successRate, 2) . "%\n";
    }
}