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
use Psr\Log\LoggerInterface;

/**
 * Main application class for Keira Web Monitor
 */
class Application
{
    private ConfigLoader $configLoader;
    private MonitorManager $monitorManager;
    private SlackNotifier $slackNotifier;
    private SignalHandler $signalHandler;
    private DataRetention $dataRetention;
    private ApiServer $apiServer;
    private LoggerInterface $appLogger;
    private LoggerInterface $monitorLogger;
    private string $configPath;
    
    /**
     * Constructor
     */
    public function __construct(string $configPath = null)
    {
        // Set default config path if not provided
        $this->configPath = $configPath ?? __DIR__ . '/../../config.json';
        
        // Create loggers
        $this->appLogger = Logger::createAppLogger();
        $this->monitorLogger = Logger::createMonitorLogger();
    }
    
    /**
     * Initialize the application
     */
    public function initialize(): void
    {
        $pid = getmypid();
        $this->appLogger->info("Starting Keira Web Monitor (PID: {$pid})");
        
        try {
            // Load configuration
            $this->appLogger->info("Loading configuration from {$this->configPath}");
            $this->configLoader = new ConfigLoader($this->configPath);
            $config = $this->configLoader->load();
            
            // Create monitor manager
            $this->monitorManager = new MonitorManager($this->monitorLogger);
            $this->monitorManager->configure($config['monitors']);
            
            // Create Slack notifier
            $this->slackNotifier = new SlackNotifier(
                $config['slack']['webhook_url'],
                $config['slack']['channel'],
                $this->appLogger
            );
            
            // Register Slack notifier with monitor manager
            $this->registerSlackNotifier();
            
            // Create signal handler
            $this->signalHandler = new SignalHandler($this->configLoader, $this->monitorManager, $this->appLogger);
            $this->signalHandler->register();
            
            // Create data retention manager
            $this->dataRetention = new DataRetention($this->monitorManager, $this->appLogger);
            $this->dataRetention->start();
            
            // Create API server
            $this->apiServer = new ApiServer('0.0.0.0', 8080, $this->monitorManager, $this->appLogger);
            $this->apiServer->start();
            
            // Start monitoring
            $this->monitorManager->start();
            
            $this->appLogger->info("Keira Web Monitor started successfully");
        } catch (\Throwable $e) {
            $this->appLogger->error("Fatal error: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Register Slack notifier with monitor manager
     */
    private function registerSlackNotifier(): void
    {
        $this->monitorManager->addResultListener(function ($result) {
            $id = $result->getId();
            $monitorConfig = $this->monitorManager->getMonitor($id);
            
            if ($monitorConfig === null) {
                return;
            }
            
            $url = $monitorConfig->getUrl();
            $threshold = $monitorConfig->getAlertThreshold();
            $consecutiveErrors = $this->monitorManager->getConsecutiveErrors($id);
            
            // Check if we need to send an alert
            if (!$result->isSuccess() && $consecutiveErrors === $threshold) {
                $this->slackNotifier->sendAlert($id, $url, $result, $threshold);
            }
            
            // Check if we need to send a recovery notification
            if ($result->isSuccess() && $consecutiveErrors === 0 && $this->monitorManager->wasInErrorState($id)) {
                $this->slackNotifier->sendRecovery($id, $url, $result);
            }
        });
    }
    
    /**
     * Run the application
     */
    public function run(): void
    {
        // Run the event loop
        Loop::run();
    }
}
