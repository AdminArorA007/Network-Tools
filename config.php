<?php
/**
 * Netools — Configuration
 * Privacy-first: No cookies, no tracking, no database.
 */

define('NETOOLS_DATA_DIR', getenv('NETOOLS_DATA_DIR') ?: '/var/www/it-india.org/data/netools');

// Used to sign stateless CSRF tokens without setting any cookies.
// Change this to any random 32+ character string on your live server.
define('NETOOLS_SECRET_KEY', getenv('SomePassWord') ?: 'change-this-to-a-random-secret-key-2026');

// GitHub repository link shown in the top bar
define('NETOOLS_GITHUB_URL', 'https://github.com/AdminArorA007/Network-Tools');

// Strict No-Tracking default (false = zero query/IP logs saved to disk)
define('NETOOLS_ENABLE_LOGS', false);

// Rate Limits (enforced via one-way SHA-256 hashes, never raw IPs)
define('NETOOLS_MIN_WAIT_SEC', 5);
define('NETOOLS_HOURLY_PER_IP', 40);
define('NETOOLS_HOURLY_GLOBAL', 400);
define('NETOOLS_MAX_TARGET_LEN', 253);