<?php

declare(strict_types=1);

namespace Keira\Api;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Router;
use Amp\Socket\Server;
use Keira\Monitor\MonitorManager;
use Keira\WebSocket\WebSocketHandler;
use Psr\Log\LoggerInterface;

/**
 * API server for Keira
 */
class ApiServer
{
    private HttpServer $server;
    private Router $router;
    
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
     * Start the API server
     */
    public function start(): void
    {
        $this->logger->info("[INFO][APP] Starting API server on {$this->host}:{$this->port}");
        
        // Create socket server
        $socketServer = Server::listen("{$this->host}:{$this->port}");
        
        // Create router
        $this->router = new Router();
        
        // Register routes
        $this->registerRoutes();
        
        // Create HTTP server
        $this->server = new HttpServer(
            [$socketServer],
            $this->router,
            $this->logger,
            new DefaultErrorHandler()
        );
        
        // Start the server
        $this->server->start();
    }

    /**
     * Stop the API server
     */
    public function stop(): void
    {
        $this->logger->info("[INFO][APP] Stopping API server");
        $this->server->stop();
    }

    /**
     * Register API routes
     */
    private function registerRoutes(): void
    {
        // GET /monitors - List all monitors
        $this->router->addRoute('GET', '/monitors', new CallableRequestHandler(
            new MonitorsHandler($this->monitorManager)
        ));
        
        // GET /monitor/{id} - Get monitor status
        $this->router->addRoute('GET', '/monitor/{id}', new CallableRequestHandler(
            new MonitorHandler($this->monitorManager)
        ));
        
        // WebSocket /realtime/ - Real-time updates
        $this->router->addRoute('GET', '/realtime/', new WebSocketHandler($this->monitorManager, $this->logger));
    }
}
