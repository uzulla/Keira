<?php

declare(strict_types=1);

namespace Keira\WebSocket;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Http\Server\Router;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\AllowOriginAcceptor;
// Socket listening is now handled by SocketHttpServer
use Keira\Monitor\MonitorManager;
use Psr\Log\LoggerInterface;

/**
 * WebSocket server for Keira
 */
class WebSocketServer
{
    private SocketHttpServer $server;
    
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
        
        // Create HTTP server
        $this->server = SocketHttpServer::createForDirectAccess($this->logger);
        
        // Create router and error handler
        $errorHandler = new DefaultErrorHandler();
        $router = new Router($this->server, $this->logger, $errorHandler);
        
        // Register WebSocket route
        $webSocketHandler = new WebSocketHandler($this->monitorManager, $this->logger);
        $acceptor = new AllowOriginAcceptor(['*']); // Allow any origin for this example
        $websocket = new Websocket($this->server, $this->logger, $acceptor, $webSocketHandler);
        $router->addRoute('GET', '/realtime/', $websocket);
        
        // Expose server on hostname:port
        $this->server->expose("{$this->host}:{$this->port}");
        
        // Start the server
        $this->server->start($router, $errorHandler);
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
