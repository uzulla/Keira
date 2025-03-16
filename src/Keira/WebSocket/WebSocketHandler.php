<?php

declare(strict_types=1);

namespace Keira\WebSocket;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\WebSocket\Message;
use Amp\Http\Server\WebSocket\WebSocketHandler as AmpWebSocketHandler;
use Amp\Http\Server\WebSocket\WebSocketClient;
use Keira\Monitor\MonitorManager;
use Keira\Monitor\MonitorResult;
use Psr\Log\LoggerInterface;

/**
 * WebSocket handler for real-time monitoring updates
 */
class WebSocketHandler extends AmpWebSocketHandler
{
    /** @var array<WebSocketClient> */
    private array $clients = [];
    
    /**
     * Constructor
     */
    public function __construct(
        private MonitorManager $monitorManager,
        private LoggerInterface $logger
    ) {
        parent::__construct();
        
        // Register as a listener for monitor results
        $this->monitorManager->addResultListener(function (MonitorResult $result) {
            $this->broadcastResult($result);
        });
    }

    /**
     * Handle WebSocket connection
     */
    public function onStart(WebSocketClient $client, Request $request, Response $response): void
    {
        $this->logger->info("[INFO][APP] WebSocket client connected");
        $this->clients[] = $client;
    }

    /**
     * Handle WebSocket message
     */
    public function onMessage(WebSocketClient $client, Message $message): void
    {
        // We don't expect any messages from clients, but we'll log them just in case
        $payload = $message->buffer();
        $this->logger->info("[INFO][APP] Received WebSocket message: {$payload}");
    }

    /**
     * Handle WebSocket disconnection
     */
    public function onClose(WebSocketClient $client, int $code, string $reason): void
    {
        $this->logger->info("[INFO][APP] WebSocket client disconnected: {$reason} ({$code})");
        
        // Remove client from the list
        $index = array_search($client, $this->clients, true);
        if ($index !== false) {
            unset($this->clients[$index]);
            $this->clients = array_values($this->clients); // Re-index array
        }
    }

    /**
     * Broadcast monitor result to all connected clients
     */
    private function broadcastResult(MonitorResult $result): void
    {
        $payload = json_encode($result->toArray());
        
        foreach ($this->clients as $client) {
            if ($client->isConnected()) {
                $client->send($payload);
            }
        }
    }
}
