<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$config['live_editing'] = getenv('ALTUS_LIVE_EDITING') !== '0';
$config['mcp_enabled'] = getenv('ALTUS_MCP_ENABLED') === '1';
$config['gateway_url'] = rtrim(getenv('ALTUS_MCP_URL') ?: 'http://127.0.0.1:3100', '/');
$config['gateway_secret'] = getenv('ALTUS_MCP_SECRET') ?: '';
$config['native_audience'] = 'altus-native-publisher';
// Key rotation: the gateway signs with the current key id/secret; the previous pair verifies until removed.
$config['gateway_key_id'] = getenv('ALTUS_MCP_KEY_ID') ?: 'current';
$config['gateway_secret_previous'] = getenv('ALTUS_MCP_SECRET_PREVIOUS') ?: '';
$config['gateway_key_id_previous'] = getenv('ALTUS_MCP_KEY_ID_PREVIOUS') ?: 'previous';
// Self-approval: the requesting administrator may approve their own MCP request (every review is audited).
// Set ALTUS_MCP_ALLOW_SELF_APPROVAL=0 to require a second administrator (separation of duties).
$config['allow_self_approval'] = getenv('ALTUS_MCP_ALLOW_SELF_APPROVAL') !== '0';
// Seconds a request stays approvable/usable. Default 24 hours so a reviewer can work through a batch.
$config['approval_ttl'] = (int) (getenv('ALTUS_MCP_APPROVAL_TTL') ?: 86400);

// Native PHP MCP server (/mcp) and OAuth 2.1 authorization server (/oauth/*). Same mcp_enabled switch.
// Issuer: set ALTUS_MCP_ISSUER to the public base URL in production (default: config base_url).
$config['mcp_issuer'] = rtrim((string) getenv('ALTUS_MCP_ISSUER'), '/');
$config['mcp_access_ttl'] = 300;            // access tokens: 5 minutes
$config['mcp_refresh_ttl'] = 30 * 86400;    // refresh tokens (rotated on every use)
$config['mcp_code_ttl'] = 60;               // authorization codes
$config['mcp_rate_limit'] = (int) (getenv('ALTUS_MCP_RATE_LIMIT') ?: 120);   // requests per minute per client+user
$config['mcp_ip_rate_limit'] = (int) (getenv('ALTUS_MCP_IP_RATE_LIMIT') ?: 300); // requests per minute per IP (all endpoints)
// Extra browser origins allowed to call /mcp (the issuer origin is always allowed). Comma separated.
$config['mcp_allowed_origins'] = array_values(array_filter(array_map('trim', explode(',', (string) getenv('ALTUS_MCP_ALLOWED_ORIGINS')))));

// Document extraction. Executables come from server configuration only, never from a request.
// Python: absolute path recommended for web servers whose PATH differs from the shell.
$config['python'] = getenv('ALTUS_PUBLISHER_PYTHON') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
// Tesseract: empty means "find tesseract on PATH" (the extractor then also checks this value).
$config['tesseract'] = getenv('ALTUS_TESSERACT') ?: (PHP_OS_FAMILY === 'Windows' && is_file('C:/Program Files/Tesseract-OCR/tesseract.exe') ? 'C:/Program Files/Tesseract-OCR/tesseract.exe' : '');
// Folder holding eng.traineddata and ara.traineddata.
$config['tessdata'] = getenv('ALTUS_TESSDATA') ?: APPPATH . 'storage/private/ocr/tessdata';
// Worker behaviour.
$config['max_attempts'] = (int) (getenv('ALTUS_PUBLISHER_MAX_ATTEMPTS') ?: 3);       // automatic tries for transient failures
$config['attempt_limit'] = (int) (getenv('ALTUS_PUBLISHER_ATTEMPT_LIMIT') ?: 6);     // hard cap including manual retries
$config['backoff_seconds'] = (int) (getenv('ALTUS_PUBLISHER_BACKOFF') ?: 60);        // doubles per attempt
$config['source_retention_days'] = (int) (getenv('ALTUS_PUBLISHER_RETENTION_DAYS') ?: 30);
$config['heartbeat_stale_seconds'] = 300;
$config['worker_sleep_seconds'] = 5;

// Optional server-local settings; never deploy this workstation override.
if (is_file(__DIR__.'/ha_publisher.local.php')) $config=array_merge($config,(array)include __DIR__.'/ha_publisher.local.php');
