<?php

declare(strict_types=1);

/**
 * Configuration for WebServerOnly shell.
 * This file defines runtime configuration constants.
 */

// Tick interval in seconds (updated from 0.1 to 0.5 to fix input race conditions)
const TICK_INTERVAL = 0.5;

// Patterns directory (relative to WebServerOnly directory)
const PATTERNS_DIR = __DIR__ . '/patterns';

// Session configuration
const SESSION_NAME = 'gol_session';
const SESSION_LIFETIME = 0; // Until browser close

// Debug mode
const DEBUG = false;
