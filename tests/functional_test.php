#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Tests;

use Amp\Loop;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\WebSocket\Client\Connection;
use Amp\WebSocket\Client\Connector;
use Keira\Config\ConfigLoader;
use Keira\Monitor\MonitorConfig;
use Keira\Monitor\MonitorManager;
use Keira\Monitor\MonitorResult;
use Keira\Api\ApiServer;
use Keira\Util\Logger;
use Keira\WebSocket\WebSocketHandler;
use Keira\Slack\SlackNotifier;
use Psr\Log\LoggerInterface;

// Autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed. Run 'composer install' first.\n";
    exit(1);
}

// Create test logger
$logger = Logger::createAppLogger();
$logger->info("Starting functional test");

// Create a mock HTTP server for testing
class MockServer {
    private $server;
    private $logger;
    private $statusCode = 200;
    private $responseBody = "OK";
    
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }
    
    public function start(): void {
        $this->server = new \Amp\Http\Server\HttpServer(
            [\Amp\Socket\Server::listen("127.0.0.1:8082")],
            new \Amp\Http\Server\RequestHandler\CallableRequestHandler(function () {
                return new \Amp\Http\Server\Response(
                    $this->statusCode,
                    ['content-type' => 'text/plain'],
                    $this->responseBody
                );
            }),
            $this->logger
        );
        
        $this->server->start();
        $this->logger->info("Mock server started on 127.0.0.1:8082");
    }
    
    public function stop(): void {
        $this->server->stop();
        $this->logger->info("Mock server stopped");
    }
    
    public function setStatusCode(int $statusCode): void {
        $this->statusCode = $statusCode;
    }
    
    public function setResponseBody(string $responseBody): void {
        $this->responseBody = $responseBody;
    }
}

// Create a test config file
$configFile = __DIR__ . '/test_config.json';
$config = [
    'slack' => [
        'webhook_url' => 'https://hooks.slack.com/services/T00000000/B0000000/XXXXXXXXXXXXXXXXXXXXXXXX',
        'channel' => '#alerts-channel'
    ],
    'monitors' => [
        [
            'id' => 'test-service-1',
            'url' => 'http://127.0.0.1:8082',
            'interval_ms' => 500,
            'timeout_ms' => 1000,
            'expected_status' => 200,
            'expected_content' => 'OK',
            'alert_threshold' => 3,
            'ignore_tls_error' => true,
            'is_active' => true
        ],
        [
            'id' => 'test-service-2',
            'url' => 'http://127.0.0.1:8082/health',
            'interval_ms' => 1000,
            'timeout_ms' => 2000,
            'expected_status' => 200,
            'expected_content' => 'OK',
            'alert_threshold' => 2,
            'ignore_tls_error' => false,
            'is_active' => true
        ]
    ]
];

file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
$logger->info("Created test config file at {$configFile}");

// Start the mock server
$mockServer = new MockServer($logger);
$mockServer->start();

// Create monitor manager
$monitorLogger = Logger::createMonitorLogger();
$monitorManager = new MonitorManager($monitorLogger);

// Create a mock Slack notifier
class MockSlackNotifier extends SlackNotifier {
    private $alerts = [];
    private $recoveries = [];
    
    public function __construct(LoggerInterface $logger) {
        parent::__construct('https://hooks.slack.com/services/TXXXXXX/BXXXXXX/XXXXXXXX', '#test-channel', $logger);
    }
    
    public function sendAlert(string $id, string $url, MonitorResult $result, int $threshold): void {
        $this->alerts[] = [
            'id' => $id,
            'url' => $url,
            'result' => $result,
            'threshold' => $threshold
        ];
        
        $this->logger->info("Mock Slack alert sent for {$id}");
    }
    
    public function sendRecovery(string $id, string $url, MonitorResult $result): void {
        $this->recoveries[] = [
            'id' => $id,
            'url' => $url,
            'result' => $result
        ];
        
        $this->logger->info("Mock Slack recovery sent for {$id}");
    }
    
    public function getAlerts(): array {
        return $this->alerts;
    }
    
    public function getRecoveries(): array {
        return $this->recoveries;
    }
}

// Create a mock Slack notifier
$slackNotifier = new MockSlackNotifier($logger);

// Load configuration
$configLoader = new ConfigLoader($configFile);
$loadedConfig = $configLoader->load();
$monitorManager->configure($loadedConfig['monitors']);

// Register Slack notifier with monitor manager
$monitorManager->addResultListener(function ($result) use ($monitorManager, $slackNotifier) {
    $id = $result->getId();
    $monitorConfig = $monitorManager->getMonitor($id);
    
    if ($monitorConfig === null) {
        return;
    }
    
    $url = $monitorConfig->getUrl();
    $threshold = $monitorConfig->getAlertThreshold();
    $consecutiveErrors = $monitorManager->getConsecutiveErrors($id);
    
    // Check if we need to send an alert
    if (!$result->isSuccess() && $consecutiveErrors === $threshold) {
        $slackNotifier->sendAlert($id, $url, $result, $threshold);
    }
    
    // Check if we need to send a recovery notification
    if ($result->isSuccess() && $consecutiveErrors === 0 && $monitorManager->wasInErrorState($id)) {
        $slackNotifier->sendRecovery($id, $url, $result);
    }
});

// Start API server
$apiServer = new ApiServer('127.0.0.1', 8083, $monitorManager, $logger);
$apiServer->start();
$logger->info("API server started on 127.0.0.1:8083");

// Start monitoring
$monitorManager->start();
$logger->info("Monitoring started");

// Run tests
$testsPassed = true;
$testResults = [];

// Test 1: Check that monitoring works
$testResults['monitoring'] = false;
Loop::delay(2000, function () use ($monitorManager, $logger, &$testResults) {
    $results = $monitorManager->getMonitorResults('test-service-1');
    if (count($results) >= 2) {
        $logger->info("Test 1 passed: Monitoring is working");
        $testResults['monitoring'] = true;
    } else {
        $logger->error("Test 1 failed: Expected at least 2 results, but got " . count($results));
    }
});

// Test 2: Check API endpoints
$testResults['api'] = false;
Loop::delay(3000, function () use ($logger, &$testResults) {
    try {
        $client = HttpClientBuilder::buildDefault();
        
        // Test /monitors endpoint
        $request = new Request('http://127.0.0.1:8083/monitors');
        $response = $client->request($request);
        $body = $response->getBody()->buffer();
        $data = json_decode($body, true);
        
        if ($response->getStatus() === 200 && is_array($data) && count($data) === 2) {
            $logger->info("API test 1 passed: /monitors endpoint works");
            
            // Test /monitor/{id} endpoint
            $request = new Request('http://127.0.0.1:8083/monitor/test-service-1');
            $response = $client->request($request);
            $body = $response->getBody()->buffer();
            $data = json_decode($body, true);
            
            if ($response->getStatus() === 200 && isset($data['id']) && $data['id'] === 'test-service-1') {
                $logger->info("API test 2 passed: /monitor/{id} endpoint works");
                $testResults['api'] = true;
            } else {
                $logger->error("API test 2 failed: /monitor/{id} endpoint returned unexpected data");
            }
        } else {
            $logger->error("API test 1 failed: /monitors endpoint returned unexpected data");
        }
    } catch (\Throwable $e) {
        $logger->error("API test failed: " . $e->getMessage());
    }
});

// Test 3: Test error detection and Slack notifications
$testResults['error_detection'] = false;
Loop::delay(5000, function () use ($mockServer, $logger, &$testResults) {
    // Make the server return an error
    $mockServer->setStatusCode(500);
    $mockServer->setResponseBody("Error");
    $logger->info("Mock server now returning 500 error");
});

// Test 4: Test recovery detection
$testResults['recovery'] = false;
Loop::delay(10000, function () use ($mockServer, $slackNotifier, $logger, &$testResults) {
    // Make the server return success again
    $mockServer->setStatusCode(200);
    $mockServer->setResponseBody("OK");
    $logger->info("Mock server now returning 200 OK");
});

// Check Slack notifications
Loop::delay(15000, function () use ($slackNotifier, $logger, &$testResults) {
    $alerts = $slackNotifier->getAlerts();
    $recoveries = $slackNotifier->getRecoveries();
    
    if (count($alerts) > 0) {
        $logger->info("Test 3 passed: Error detection works and Slack alerts were sent");
        $testResults['error_detection'] = true;
    } else {
        $logger->error("Test 3 failed: No Slack alerts were sent");
    }
    
    if (count($recoveries) > 0) {
        $logger->info("Test 4 passed: Recovery detection works and Slack recovery notifications were sent");
        $testResults['recovery'] = true;
    } else {
        $logger->error("Test 4 failed: No Slack recovery notifications were sent");
    }
});

// Finish tests and clean up
Loop::delay(20000, function () use ($monitorManager, $apiServer, $mockServer, $configFile, $logger, &$testResults, &$testsPassed) {
    // Stop everything
    $monitorManager->stop();
    $apiServer->stop();
    $mockServer->stop();
    
    // Remove test config file
    unlink($configFile);
    
    // Check if all tests passed
    foreach ($testResults as $test => $passed) {
        if (!$passed) {
            $testsPassed = false;
            $logger->error("Test '{$test}' failed");
        }
    }
    
    if ($testsPassed) {
        $logger->info("All functional tests passed!");
    } else {
        $logger->error("Some functional tests failed");
    }
    
    // Stop the event loop
    Loop::stop();
});

// Run the event loop
Loop::run();
