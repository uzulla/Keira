<?php

declare(strict_types=1);

namespace Keira\Slack;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Keira\Monitor\MonitorResult;
use Psr\Log\LoggerInterface;
use function Amp\async;

/**
 * Slack notifier for Keira
 */
class SlackNotifier
{
    private HttpClient $httpClient;
    
    /**
     * Constructor
     */
    public function __construct(
        private string $webhookUrl,
        private string $channel,
        private LoggerInterface $logger
    ) {
        $this->httpClient = HttpClientBuilder::buildDefault();
    }

    /**
     * Send alert notification
     */
    public function sendAlert(string $id, string $url, MonitorResult $result, int $threshold): void
    {
        $timestamp = $result->getTimestamp()->format('Y-m-d H:i:s');
        $error = $result->getError() ?? 'Unknown error';
        
        $message = <<<EOT
🚨 [ALERT] {$url} (id: {$id}) が{$threshold}回連続エラーになりました。
発生時刻: {$timestamp}  
URL: {$url}
直近の原因: {$error} (設定値: {$result->getResponseTimeMs()}ms)
EOT;

        $this->sendMessage($message);
    }

    /**
     * Send recovery notification
     */
    public function sendRecovery(string $id, string $url, MonitorResult $result): void
    {
        $timestamp = $result->getTimestamp()->format('Y-m-d H:i:s');
        
        $message = <<<EOT
✅ [復旧] {$url} は正常状態に戻りました。
発生時刻: {$timestamp}
EOT;

        $this->sendMessage($message);
    }

    /**
     * Send message to Slack
     */
    private function sendMessage(string $message): void
    {
        async(function () use ($message) {
            try {
                $payload = [
                    'channel' => $this->channel,
                    'text' => $message
                ];
                
                $request = new Request($this->webhookUrl, 'POST');
                $request->setHeader('Content-Type', 'application/json');
                $request->setBody(json_encode($payload));
                
                $response = yield $this->httpClient->request($request);
                
                if ($response->getStatus() !== 200) {
                    $this->logger->error("[ERROR][APP] Failed to send Slack notification: HTTP {$response->getStatus()}");
                }
            } catch (\Throwable $e) {
                $this->logger->error("[ERROR][APP] Error sending Slack notification: {$e->getMessage()}");
            }
        });
    }
}
