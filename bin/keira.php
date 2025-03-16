#!/usr/bin/env php
<?php

declare(strict_types=1);

// Autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed. Run 'composer install' first.\n";
    exit(1);
}

// Parse command line arguments
$options = getopt('', ['config:']);
$configPath = $options['config'] ?? null;

// Get and display the process ID
$pid = getmypid();
echo "Keira Web Monitor starting with PID: {$pid}\n";

try {
    // Create and initialize the application
    $app = new Keira\Application($configPath);
    $app->initialize();
    
    // Run the application
    $app->run();
} catch (\Throwable $e) {
    echo "Fatal error: {$e->getMessage()}\n";
    exit(1);
}
