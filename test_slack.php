#!/usr/bin/env php
<?php

declare(strict_types=1);

use Amp\Loop;
use Keira\Monitor\MonitorResult;
use Keira\Slack\SlackNotifier;
use Keira\Util\Logger;

// Autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed. Run 'composer install' first.\n";
    exit(1);
}

// Create logger
$logger = Logger::createAppLogger();
$logger->info("Starting Slack notification test");

// Slack webhook URL and channel provided by the user
$webhookUrl = 'https://hooks.slack.com/services/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';
$channel = '#dev_null';

// Create Slack notifier
$notifier = new SlackNotifier($webhookUrl, $channel, $logger);

// Create test results
$successResult = MonitorResult::createSuccess('test-service', 150, 200);
$failureResult = MonitorResult::createFailure('test-service', 1500, 500, 'Server Error');

// Run tests
Loop::run(function () use ($notifier, $successResult, $failureResult, $logger) {
    $logger->info("Sending alert notification...");
    $notifier->sendAlert('test-service', 'https://example.com/api', $failureResult, 3);
    
    // Wait a bit before sending recovery
    Loop::delay(3000, function () use ($notifier, $successResult, $logger) {
        $logger->info("Sending recovery notification...");
        $notifier->sendRecovery('test-service', 'https://example.com/api', $successResult);
        
        // Wait a bit to ensure notifications are sent before exiting
        Loop::delay(3000, function () use ($logger) {
            $logger->info("Test completed");
            Loop::stop();
        });
    });
});
