<?php

declare(strict_types=1);

namespace Keira\Monitor;

use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\TimeoutCancellation;
use Keira\Util\Logger;
use Psr\Log\LoggerInterface;
use function Amp\async;
use function Amp\delay;

/**
 * Manages monitoring of multiple servers
 */
class MonitorManager
{
    /** @var array<string, MonitorConfig> */
    private array $monitors = [];
    
    /** @var array<string, array<MonitorResult>> */
    private array $results = [];
    
    /** @var array<string, int> */
    private array $consecutiveErrors = [];
    
    /** @var array<string, bool> */
    private array $alertSent = [];
    
    /** @var array<string, Future> */
    private array $monitorTasks = [];
    
    /** @var array<callable> */
    private array $resultListeners = [];
    
    private DeferredCancellation $cancellation;
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private bool $isRunning = false;

    /**
     * Constructor
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->httpClient = HttpClientBuilder::buildDefault();
        $this->cancellation = new DeferredCancellation();
    }

    /**
     * Configure monitors
     *
     * @param array<array> $monitorsConfig
     */
    public function configure(array $monitorsConfig): void
    {
        $newMonitors = [];
        
        foreach ($monitorsConfig as $monitorConfig) {
            $config = MonitorConfig::fromArray($monitorConfig);
            $id = $config->getId();
            $newMonitors[$id] = $config;
            
            // Initialize tracking arrays for new monitors
            if (!isset($this->results[$id])) {
                $this->results[$id] = [];
            }
            
            if (!isset($this->consecutiveErrors[$id])) {
                $this->consecutiveErrors[$id] = 0;
            }
            
            if (!isset($this->alertSent[$id])) {
                $this->alertSent[$id] = false;
            }
        }
        
        $this->monitors = $newMonitors;
    }

    /**
     * Start monitoring
     */
    public function start(): void
    {
        if ($this->isRunning) {
            return;
        }
        
        $this->isRunning = true;
        $this->cancellation = new DeferredCancellation();
        
        foreach ($this->monitors as $id => $config) {
            if ($config->isActive()) {
                $this->startMonitor($id, $config, $this->cancellation->getCancellation());
            }
        }
    }

    /**
     * Stop monitoring
     */
    public function stop(): void
    {
        if (!$this->isRunning) {
            return;
        }
        
        $this->isRunning = false;
        $this->cancellation->cancel();
        
        // Wait for all tasks to complete
        foreach ($this->monitorTasks as $task) {
            $task->await();
        }
        
        $this->monitorTasks = [];
    }

    /**
     * Add result listener
     */
    public function addResultListener(callable $listener): void
    {
        $this->resultListeners[] = $listener;
    }

    /**
     * Get all monitor configs
     *
     * @return array<string, MonitorConfig>
     */
    public function getMonitors(): array
    {
        return $this->monitors;
    }

    /**
     * Get monitor config by ID
     */
    public function getMonitor(string $id): ?MonitorConfig
    {
        return $this->monitors[$id] ?? null;
    }

    /**
     * Get monitor results
     *
     * @return array<MonitorResult>
     */
    public function getMonitorResults(string $id): array
    {
        return $this->results[$id] ?? [];
    }

    /**
     * Get latest monitor result
     */
    public function getLatestResult(string $id): ?MonitorResult
    {
        $results = $this->results[$id] ?? [];
        if (empty($results)) {
            return null;
        }
        
        return end($results);
    }

    /**
     * Get monitor status
     */
    public function getMonitorStatus(string $id): array
    {
        $monitor = $this->getMonitor($id);
        if ($monitor === null) {
            return [
                'id' => $id,
                'current_status' => 'UNKNOWN',
                'last_checked' => null,
                'last_response_time_ms' => null,
                'recent_errors_count' => 0
            ];
        }
        
        $latestResult = $this->getLatestResult($id);
        if ($latestResult === null) {
            return [
                'id' => $id,
                'current_status' => 'PENDING',
                'last_checked' => null,
                'last_response_time_ms' => null,
                'recent_errors_count' => 0
            ];
        }
        
        return [
            'id' => $id,
            'current_status' => $latestResult->getStatus(),
            'last_checked' => $latestResult->getTimestamp()->format(\DateTimeInterface::ATOM),
            'last_response_time_ms' => $latestResult->getResponseTimeMs(),
            'recent_errors_count' => $this->consecutiveErrors[$id] ?? 0
        ];
    }

    /**
     * Clean old results
     */
    public function cleanOldResults(): void
    {
        $cutoff = new \DateTimeImmutable('-24 hours');
        
        foreach ($this->results as $id => $results) {
            $this->results[$id] = array_filter($results, function (MonitorResult $result) use ($cutoff) {
                return $result->getTimestamp() > $cutoff;
            });
        }
    }

    /**
     * Start monitoring a single server
     */
    private function startMonitor(string $id, MonitorConfig $config, Cancellation $cancellation): void
    {
        $this->monitorTasks[$id] = async(function () use ($id, $config, $cancellation) {
            while (!$cancellation->isRequested()) {
                try {
                    $startTime = microtime(true);
                    
                    // Check if we can run this check on time
                    $nextCheckTime = $startTime + ($config->getIntervalMs() / 1000);
                    
                    $result = $this->checkServer($config, $cancellation);
                    $this->processResult($id, $result);
                    
                    // Wait until next check time
                    $now = microtime(true);
                    if ($now < $nextCheckTime) {
                        $delayMs = (int)(($nextCheckTime - $now) * 1000);
                        if ($delayMs > 0) {
                            yield delay($delayMs, $cancellation);
                        }
                    } else {
                        // We're behind schedule, log a warning and continue immediately
                        $this->logger->warning(
                            "[WARNING][APP] Monitor {$id} is running behind schedule. " .
                            "Skipping this check and continuing with next interval."
                        );
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("[ERROR][APP] Error in monitor {$id}: {$e->getMessage()}");
                    
                    // Wait for the next interval before retrying
                    try {
                        yield delay($config->getIntervalMs(), $cancellation);
                    } catch (\Throwable $delayError) {
                        // Cancellation requested, exit the loop
                        break;
                    }
                }
            }
        });
    }

    /**
     * Check a single server
     */
    private function checkServer(MonitorConfig $config, Cancellation $parentCancellation): MonitorResult
    {
        $id = $config->getId();
        $url = $config->getUrl();
        $timeoutMs = $config->getTimeoutMs();
        
        $startTime = microtime(true);
        
        try {
            // Create a timeout cancellation that will be used for this request
            $timeoutCancellation = new TimeoutCancellation($timeoutMs / 1000);
            
            // Create a request
            $request = new Request($url);
            
            // Set TLS verification based on config
            $request->setTlsHandshakeTimeout($timeoutMs / 1000);
            $request->setBodySizeLimit(1024 * 1024); // 1MB limit
            
            if ($config->shouldIgnoreTlsError()) {
                $request->setTlsContext((new \Amp\Socket\ClientTlsContext)->withoutPeerVerification());
            }
            
            // Send the request
            $response = $this->httpClient->request($request, $timeoutCancellation);
            
            // Calculate response time
            $endTime = microtime(true);
            $responseTimeMs = (int)(($endTime - $startTime) * 1000);
            
            // Check status code
            $statusCode = $response->getStatus();
            if ($statusCode !== $config->getExpectedStatus()) {
                return MonitorResult::createFailure(
                    $id,
                    $responseTimeMs,
                    $statusCode,
                    "Invalid Status Code"
                );
            }
            
            // Check response content
            $body = $response->getBody()->buffer();
            if (!str_contains($body, $config->getExpectedContent())) {
                return MonitorResult::createFailure(
                    $id,
                    $responseTimeMs,
                    $statusCode,
                    "Expected content not found"
                );
            }
            
            // All checks passed
            return MonitorResult::createSuccess($id, $responseTimeMs, $statusCode);
        } catch (\Throwable $e) {
            // Calculate response time even for errors
            $endTime = microtime(true);
            $responseTimeMs = (int)(($endTime - $startTime) * 1000);
            
            $errorMessage = $e->getMessage();
            $httpStatus = null;
            
            // Determine specific error type
            if ($e instanceof \Amp\TimeoutException) {
                $errorMessage = "Timeout";
            } elseif ($e instanceof \Amp\Http\Client\HttpException) {
                $errorMessage = "HTTP Error: " . $e->getMessage();
            } elseif ($e instanceof \Amp\Socket\SocketException) {
                $errorMessage = "Connection Error: " . $e->getMessage();
            }
            
            return MonitorResult::createFailure($id, $responseTimeMs, $httpStatus, $errorMessage);
        }
    }

    /**
     * Process a monitoring result
     */
    private function processResult(string $id, MonitorResult $result): void
    {
        // Store the result
        $this->results[$id][] = $result;
        
        // Update consecutive error count
        if ($result->isSuccess()) {
            $wasInErrorState = $this->consecutiveErrors[$id] >= $this->monitors[$id]->getAlertThreshold();
            $this->consecutiveErrors[$id] = 0;
            
            // Log the result
            $this->logger->info(sprintf(
                "[INFO][MONITOR] %s, %s, %dms, %d",
                $id,
                $result->getStatus(),
                $result->getResponseTimeMs(),
                $result->getHttpStatus() ?? 0
            ));
            
            // Check if we need to send a recovery notification
            if ($wasInErrorState && $this->alertSent[$id]) {
                $this->alertSent[$id] = false;
                $this->notifyRecovery($id, $result);
            }
        } else {
            $this->consecutiveErrors[$id]++;
            
            // Log the error
            $this->logger->error(sprintf(
                "[ERROR][MONITOR] %s, %s, %dms, %d, %s",
                $id,
                $result->getStatus(),
                $result->getResponseTimeMs(),
                $result->getHttpStatus() ?? 0,
                $result->getError() ?? 'Unknown Error'
            ));
            
            // Check if we need to send an alert
            $threshold = $this->monitors[$id]->getAlertThreshold();
            if ($this->consecutiveErrors[$id] >= $threshold && !$this->alertSent[$id]) {
                $this->alertSent[$id] = true;
                $this->notifyAlert($id, $result, $this->consecutiveErrors[$id]);
            }
        }
        
        // Notify listeners
        foreach ($this->resultListeners as $listener) {
            $listener($result);
        }
    }

    /**
     * Notify about an alert
     */
    private function notifyAlert(string $id, MonitorResult $result, int $consecutiveErrors): void
    {
        // This will be implemented in the Slack notification class
        // For now, just log it
        $this->logger->error(
            "[ALERT][MONITOR] {$id} has {$consecutiveErrors} consecutive errors. " .
            "Latest error: {$result->getError()}"
        );
    }

    /**
     * Notify about a recovery
     */
    private function notifyRecovery(string $id, MonitorResult $result): void
    {
        // This will be implemented in the Slack notification class
        // For now, just log it
        $this->logger->info(
            "[RECOVERY][MONITOR] {$id} has recovered. " .
            "Response time: {$result->getResponseTimeMs()}ms"
        );
    }
}
