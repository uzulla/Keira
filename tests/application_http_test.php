#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Tests;

use Amp\Loop;
use Keira\Application;
use Keira\Config\ConfigLoader;
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
$logger->info("Starting Application HTTP test");

// Test config path
$configPath = __DIR__ . '/../config.test.json';

// Create a test application
$app = new Application($configPath);

// Override the run method to stop after a few seconds
Loop::run(function () use ($app, $logger) {
    // Initialize the application
    try {
        $app->initialize();
        
        $logger->info("Application initialized successfully");
        
        // Run for 5 seconds to collect some results
        Loop::delay(5000, function () use ($logger) {
            $logger->info("Test completed, stopping application");
            Loop::stop();
        });
    } catch (\Throwable $e) {
        $logger->error("Error initializing application: " . $e->getMessage());
        Loop::stop();
    }
});

$logger->info("Application HTTP test completed");
