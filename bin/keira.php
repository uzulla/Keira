#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Keira;

use Amp\Loop;
use Keira\Api\ApiServer;
use Keira\Config\ConfigLoader;
use Keira\Monitor\MonitorManager;
use Keira\Slack\SlackNotifier;
use Keira\Util\DataRetention;
use Keira\Util\Logger;
use Keira\Util\SignalHandler;
use Keira\WebSocket\WebSocketServer;

// Autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed. Run 'composer install' first.\n";
    exit(1);
}

// Parse command line arguments
$options = getopt('', ['config:']);
$configPath = $options['config'] ?? __DIR__ . '/../config.json';

// Create loggers
$appLogger = Logger::createAppLogger();
$monitorLogger = Logger::createMonitorLogger();

$appLogger->info("Starting Keira Web Monitor");

try {
    // Load configuration
    $appLogger->info("Loading configuration from {$configPath}");
    $configLoader = new ConfigLoader($configPath);
    $config = $configLoader->load();
    
    // Create monitor manager
    $monitorManager = new MonitorManager($monitorLogger);
    $monitorManager->configure($config['monitors']);
    
    // Create Slack notifier
    $slackNotifier = new SlackNotifier(
        $config['slack']['webhook_url'],
        $config['slack']['channel'],
        $appLogger
    );
    
    // Register Slack notifier with monitor manager
    $monitorManager->addResultListener(function ($result) use ($monitorManager, $slackNotifier, $config) {
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
    
    // Create signal handler
    $signalHandler = new SignalHandler($configLoader, $monitorManager, $appLogger);
    $signalHandler->register();
    
    // Create data retention manager
    $dataRetention = new DataRetention($monitorManager, $appLogger);
    $dataRetention->start();
    
    // Create API server
    $apiServer = new ApiServer('0.0.0.0', 8080, $monitorManager, $appLogger);
    $apiServer->start();
    
    // Start monitoring
    $monitorManager->start();
    
    $appLogger->info("Keira Web Monitor started successfully");
    
    // Run the event loop
    Loop::run();
} catch (\Throwable $e) {
    $appLogger->error("Fatal error: {$e->getMessage()}");
    exit(1);
}
