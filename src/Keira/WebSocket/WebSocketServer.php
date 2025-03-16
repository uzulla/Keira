<?php

declare(strict_types=1);

namespace Keira\WebSocket;

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Router;
use Amp\Socket\Server;
use Keira\Monitor\MonitorManager;
use Psr\Log\LoggerInterface;

/**
 * WebSocket server for Keira
 */
class WebSocketServer
{
    private HttpServer $server;
    
    /**
     * Constructor
     */
    public function __construct(
        private string $host,
        private int $port,
        private MonitorManager $monitorManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Start the WebSocket server
     */
    public function start(): void
    {
        $this->logger->info("[INFO][APP] Starting WebSocket server on {$this->host}:{$this->port}");
        
        // Create socket server
        $socketServer = Server::listen("{$this->host}:{$this->port}");
        
        // Create router
        $router = new Router();
        
        // Register WebSocket route
        $router->addRoute('GET', '/realtime/', new WebSocketHandler($this->monitorManager, $this->logger));
        
        // Create HTTP server
        $this->server = new HttpServer(
            [$socketServer],
            $router,
            $this->logger
        );
        
        // Start the server
        $this->server->start();
    }

    /**
     * Stop the WebSocket server
     */
    public function stop(): void
    {
        $this->logger->info("[INFO][APP] Stopping WebSocket server");
        $this->server->stop();
    }
}
