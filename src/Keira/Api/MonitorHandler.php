<?php

declare(strict_types=1);

namespace Keira\Api;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Keira\Monitor\MonitorManager;

/**
 * Handler for /monitor/{id} endpoint
 */
class MonitorHandler
{
    /**
     * Constructor
     */
    public function __construct(
        private MonitorManager $monitorManager
    ) {
    }

    /**
     * Handle request
     */
    public function __invoke(Request $request): Response
    {
        $id = $request->getAttribute('id');
        
        if ($id === null) {
            return new Response(
                status: 400,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['error' => 'Monitor ID is required'])
            );
        }
        
        $monitor = $this->monitorManager->getMonitor($id);
        
        if ($monitor === null) {
            return new Response(
                status: 404,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['error' => "Monitor with ID '{$id}' not found"])
            );
        }
        
        $status = $this->monitorManager->getMonitorStatus($id);
        
        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($status, JSON_PRETTY_PRINT)
        );
    }
}
