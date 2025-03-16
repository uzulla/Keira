<?php

declare(strict_types=1);

namespace Keira\Api;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Keira\Monitor\MonitorManager;

/**
 * Handler for /monitors endpoint
 */
class MonitorsHandler
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
        $monitors = $this->monitorManager->getMonitors();
        $result = [];
        
        foreach ($monitors as $id => $monitor) {
            $result[] = $this->monitorManager->getMonitorStatus($id);
        }
        
        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($result, JSON_PRETTY_PRINT)
        );
    }
}
