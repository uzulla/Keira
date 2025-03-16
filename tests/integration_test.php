#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Tests;

use Amp\Loop;
use Keira\Config\ConfigLoader;
use Keira\Monitor\MonitorConfig;
use Keira\Monitor\MonitorManager;
use Keira\Monitor\MonitorResult;
use Keira\Util\Logger;

// Autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed. Run 'composer install' first.\n";
    exit(1);
}

// Create test logger
$logger = Logger::createAppLogger();
$logger->info("Starting integration test");

// Create a test server
$testServer = new class($logger) {
    private $server;
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function start(): void {
        $this->server = new \Amp\Http\Server\HttpServer(
            [\Amp\Socket\Server::listen("127.0.0.1:8081")],
            new \Amp\Http\Server\RequestHandler\CallableRequestHandler(function () {
                return new \Amp\Http\Server\Response(
                    200,
                    ['content-type' => 'text/plain'],
                    "OK"
                );
            }),
            $this->logger
        );
        
        $this->server->start();
        $this->logger->info("Test server started on 127.0.0.1:8081");
    }
    
    public function stop(): void {
        $this->server->stop();
        $this->logger->info("Test server stopped");
    }
};

// Start the test server
$testServer->start();

// Create a test monitor
$monitorConfig = new MonitorConfig(
    'test-service',
    'http://127.0.0.1:8081',
    500,
    1000,
    200,
    'OK',
    3,
    false,
    true
);

// Create monitor manager
$monitorLogger = Logger::createMonitorLogger();
$monitorManager = new MonitorManager($monitorLogger);
$monitorManager->configure([
    $monitorConfig->toArray()
]);

// Add result listener
$results = [];
$monitorManager->addResultListener(function (MonitorResult $result) use (&$results, $logger) {
    $results[] = $result;
    $logger->info("Received result: " . json_encode($result->toArray()));
});

// Start monitoring
$monitorManager->start();

// Run for 5 seconds
$logger->info("Running test for 5 seconds...");
Loop::delay(5000, function () use ($monitorManager, $testServer, $logger, &$results) {
    // Stop monitoring
    $monitorManager->stop();
    
    // Stop test server
    $testServer->stop();
    
    // Check results
    $logger->info("Test completed with " . count($results) . " results");
    
    // Verify we got at least some results
    if (count($results) < 5) {
        $logger->error("Expected at least 5 results, but got " . count($results));
        exit(1);
    }
    
    // Verify all results are successful
    foreach ($results as $result) {
        if (!$result->isSuccess()) {
            $logger->error("Expected all results to be successful, but got a failure: " . json_encode($result->toArray()));
            exit(1);
        }
    }
    
    $logger->info("All tests passed!");
    
    // Stop the event loop
    Loop::stop();
});

// Run the event loop
Loop::run();
