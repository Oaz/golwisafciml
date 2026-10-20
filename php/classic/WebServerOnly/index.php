<?php

declare(strict_types=1);

/**
 * Entry point for WebServerOnly shell.
 * Handles all HTTP requests and outputs the HTML response.
 */

// Bootstrap
require_once __DIR__ . '/bootstrap.php';

use GameOfLife\Classic\WebServerOnly\Shell\RequestHandler;
use GameOfLife\Classic\WebServerOnly\Shell\Interpreter;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\DisplayPort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\PatternStorePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\EventSourcePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\StatePort;
use GameOfLife\Classic\WebServerOnly\Models\ShellConfig;

// Configuration
$config = new ShellConfig(
    tickInterval: 0.1,
    patternsDir: __DIR__ . '/patterns',
    debug: false
);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name('gol_session');
    session_start();
}

// Initialize PatternStore
$patternStore = new PatternStorePort($config->patternsDir);

// Create ports
$displayPort = new DisplayPort();
$statePort = new StatePort();
$eventSourcePort = new EventSourcePort($_GET);

// Create interpreter
$interpreter = new Interpreter($displayPort, $patternStore, $statePort);

// Create request handler
$handler = new RequestHandler(
    $interpreter,
    $displayPort,
    $patternStore,
    $statePort,
    $eventSourcePort,
    $config
);

// Handle request and output HTML
header('Content-Type: text/html; charset=utf-8');
echo $handler->handle();
