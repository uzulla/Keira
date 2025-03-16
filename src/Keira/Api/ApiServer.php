<?php

declare(strict_types=1);

namespace Keira\Api;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Router;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\AllowOriginAcceptor;
// Socket listening is now handled by SocketHttpServer
use Keira\Monitor\MonitorManager;
use Keira\WebSocket\WebSocketHandler;
use Psr\Log\LoggerInterface;

/**
 * API server for Keira
 */
class ApiServer
{
    private SocketHttpServer $server;
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
        
        // Create HTTP server
        $this->server = SocketHttpServer::createForDirectAccess($this->logger);
        
        // Create router and error handler
        $errorHandler = new DefaultErrorHandler();
        $this->router = new Router($this->server, $this->logger, $errorHandler);
        
        // Register routes
        $this->registerRoutes();
        
        // Expose server on hostname:port
        $this->server->expose("{$this->host}:{$this->port}");
        
        // Start the server
        $this->server->start($this->router, $errorHandler);
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
        $this->router->addRoute('GET', '/monitors', new ClosureRequestHandler(
            function ($request) {
                $handler = new MonitorsHandler($this->monitorManager);
                return $handler($request);
            }
        ));
        
        // GET /monitor/{id} - Get monitor status
        $this->router->addRoute('GET', '/monitor/{id}', new ClosureRequestHandler(
            function ($request) {
                $handler = new MonitorHandler($this->monitorManager);
                return $handler($request);
            }
        ));
        
        // GET /ws-test - WebSocket test page
        $this->router->addRoute('GET', '/ws-test', new ClosureRequestHandler(
            function ($request) {
                $templatePath = __DIR__ . '/../../../resources/templates/websocket-test.html';
                
                if (!file_exists($templatePath)) {
                    $this->logger->error("[ERROR][APP] WebSocket test template not found: {$templatePath}");
                    return new \Amp\Http\Server\Response(
                        status: 500,
                        headers: ['Content-Type' => 'text/plain'],
                        body: 'Template file not found'
                    );
                }
                
                $html = file_get_contents($templatePath);
                
                return new \Amp\Http\Server\Response(
                    status: 200,
                    headers: ['Content-Type' => 'text/html'],
                    body: $html
                );
            }
        ));
        
        // WebSocket /realtime/ - Real-time updates
        $webSocketHandler = new WebSocketHandler($this->monitorManager, $this->logger);
        
        // Allow more specific origins for the WebSocket connections
        $origins = [
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            'http://0.0.0.0:8080',
            'ws://localhost:8080',
            'ws://127.0.0.1:8080',
            'ws://0.0.0.0:8080',
            'http://' . $_SERVER['HTTP_HOST'] ?? 'localhost:8080',
            'ws://' . $_SERVER['HTTP_HOST'] ?? 'localhost:8080',
        ];
        $this->logger->debug("[DEBUG][APP] Setting up WebSocket with allowed origins: " . implode(", ", $origins));
        $acceptor = new \Amp\Websocket\Server\AllowOriginAcceptor($origins);
        
        $websocket = new Websocket($this->server, $this->logger, $acceptor, $webSocketHandler);
        $this->router->addRoute('GET', '/realtime/', $websocket);
    }
}
