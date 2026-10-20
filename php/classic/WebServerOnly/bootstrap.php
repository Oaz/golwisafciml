<?php

declare(strict_types=1);

/**
 * Bootstrap file for WebServerOnly shell.
 * Handles autoloading, error handling, and session initialization.
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Ensure we're in the correct directory
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new RuntimeException('Composer autoloader not found. Run composer install.');
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name('gol_session');
    session_start();
}

// Set error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
