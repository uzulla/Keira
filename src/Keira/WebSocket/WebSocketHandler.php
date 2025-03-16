<?php

declare(strict_types=1);

namespace Keira\WebSocket;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\Server\Websocket;
use Keira\Monitor\MonitorManager;
use Keira\Monitor\MonitorResult;
use Psr\Log\LoggerInterface;

/**
 * WebSocket handler for real-time monitoring updates
 */
class WebSocketHandler implements WebsocketClientHandler
{
    private WebsocketGateway $gateway;
    
    /**
     * Constructor
     */
    public function __construct(
        private MonitorManager $monitorManager,
        private LoggerInterface $logger
    ) {
        $this->gateway = new WebsocketClientGateway();
        
        // Register as a listener for monitor results
        $this->monitorManager->addResultListener(function (MonitorResult $result) {
            $this->broadcastResult($result);
        });
    }

    /**
     * Handle WebSocket client
     */
    public function handleClient(
        WebsocketClient $client, 
        Request $request, 
        Response $response
    ): void {
        $this->logger->info("[INFO][APP] WebSocket client connected");
        
        // Add client to the gateway
        $this->gateway->addClient($client);
        
        // Keep connection open until client disconnects
        // We don't expect any messages from clients, but we'll log them just in case
        try {
            foreach ($client as $message) {
                $payload = $message;
                $this->logger->info("[INFO][APP] Received WebSocket message: {$payload}");
            }
        } catch (\Throwable $e) {
            $this->logger->error("[ERROR][APP] WebSocket error: {$e->getMessage()}");
        } finally {
            $this->logger->info("[INFO][APP] WebSocket client disconnected");
        }
    }

    /**
     * Broadcast monitor result to all connected clients
     */
    private function broadcastResult(MonitorResult $result): void
    {
        $payload = json_encode($result->toArray());
        
        if ($payload) {
            $this->gateway->broadcastText($payload);
        }
    }
}
