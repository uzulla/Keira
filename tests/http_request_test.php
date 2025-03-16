#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Tests;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Loop;
use Keira\Monitor\MonitorResult;
use Keira\Util\Logger;

// Autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed. Run 'composer install' first.\n";
    exit(1);
}

// Create logger
$logger = Logger::createAppLogger();
$logger->info("Starting HTTP request test");

// Test URLs
$successUrl = "https://cfe.jp";
$failureUrl = "https://cfe.jp/404_for_test";

// Create HTTP client
$httpClient = HttpClientBuilder::buildDefault();

// Function to test a URL
$testUrl = function (string $url, string $expectedContent = null) use ($httpClient, $logger) {
    $logger->info("Testing URL: {$url}");
    
    $startTime = microtime(true);
    $request = new Request($url, "GET");
    
    try {
        $response = yield $httpClient->request($request);
        $responseTime = (microtime(true) - $startTime) * 1000;
        $statusCode = $response->getStatus();
        $body = yield $response->getBody()->buffer();
        
        $logger->info("Response received: {$statusCode}, {$responseTime}ms");
        
        // Check if the response contains the expected content
        $contentFound = $expectedContent === null || str_contains($body, $expectedContent);
        
        if ($statusCode === 200 && $contentFound) {
            $result = MonitorResult::createSuccess('test', (int)$responseTime, $statusCode);
            $logger->info("[SUCCESS] URL: {$url}, Status: {$statusCode}, Time: {$responseTime}ms");
        } else {
            $error = !$contentFound ? "Expected content not found" : "Invalid Status Code";
            $result = MonitorResult::createFailure('test', (int)$responseTime, $statusCode, $error);
            $logger->error("[FAILURE] URL: {$url}, Status: {$statusCode}, Time: {$responseTime}ms, Error: {$error}");
        }
        
        return $result;
    } catch (\Throwable $e) {
        $responseTime = (microtime(true) - $startTime) * 1000;
        $result = MonitorResult::createFailure('test', (int)$responseTime, 0, $e->getMessage());
        $logger->error("[ERROR] URL: {$url}, Error: {$e->getMessage()}");
        return $result;
    }
};

// Run tests
Loop::run(function () use ($testUrl, $successUrl, $failureUrl, $logger) {
    // Test success URL
    $logger->info("=== Testing Success URL ===");
    $successResult = yield $testUrl($successUrl, "cfe.jp");
    
    // Test failure URL
    $logger->info("=== Testing Failure URL ===");
    $failureResult = yield $testUrl($failureUrl);
    
    // Print results
    $logger->info("=== Test Results ===");
    $logger->info("Success URL: " . ($successResult->isSuccess() ? "PASSED" : "FAILED"));
    $logger->info("Failure URL: " . (!$failureResult->isSuccess() ? "PASSED" : "FAILED"));
    
    // Overall test result
    if ($successResult->isSuccess() && !$failureResult->isSuccess()) {
        $logger->info("All tests PASSED!");
    } else {
        $logger->error("Some tests FAILED!");
    }
});
