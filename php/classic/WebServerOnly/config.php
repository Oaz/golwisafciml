<?php

declare(strict_types=1);

/**
 * Configuration for WebServerOnly shell.
 * This file defines runtime configuration constants.
 */

// Tick interval in seconds (v1 spec: 0.1s)
const TICK_INTERVAL = 0.1;

// Patterns directory (relative to WebServerOnly directory)
const PATTERNS_DIR = __DIR__ . '/patterns';

// Session configuration
const SESSION_NAME = 'gol_session';
const SESSION_LIFETIME = 0; // Until browser close

// Debug mode
const DEBUG = false;
