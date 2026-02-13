<?php
/**
 * Secure Admin Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to admin.config.php
 * 2. Create a .env file based on .env.example
 * 3. Fill in your actual credentials in .env
 * 4. Ensure .env is in .gitignore
 * 5. Set file permissions: chmod 600 .env
 * 
 * SECURITY NOTES:
 * - NEVER commit files containing real credentials
 * - Use strong, unique passwords (minimum 16 characters)
 * - Rotate passwords regularly
 * - Use dedicated database user with minimal permissions
 * - Hash admin passwords (use password_hash() function)
 */

require_once('blast_sqlin.php');

// Load environment variables from .env file
function loadEnv($path = '../.env') {
    if (!file_exists($path)) {
        die('ERROR: .env file not found. Please create it from .env.example');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load environment variables
loadEnv(__DIR__ . '/../.env');

// Get configuration from environment variables with fallback
function getEnv($key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// Debug level
$conf['debug']['level'] = 5;

/* Database Configuration - From Environment Variables */
$dbhost = getEnv('DB_HOST', 'localhost');
$dbname = getEnv('DB_NAME', '0xc');
$dbuser = getEnv('DB_USER', 'root');
$dbpass = getEnv('DB_PASSWORD', '');
$dbcharset = getEnv('DB_CHARSET', 'utf8');
$dbprefix = getEnv('DB_PREFIX', 'blast_');

// Validate required configuration
if (empty($dbpass)) {
    die('ERROR: Database password not configured. Please check your .env file.');
}

if (empty(getEnv('ADMIN_SAFE_PASSWORD'))) {
    die('ERROR: Admin password not configured. Please check your .env file.');
}

// Build DSN
$conf['db']['dsn'] = sprintf(
    'mysql:host=%s;dbname=%s',
    $dbhost,
    $dbname
);

$conf['db']['user'] = $dbuser;
$conf['db']['password'] = $dbpass;
$conf['db']['charset'] = $dbcharset;
$conf['db']['prename'] = $dbprefix;

/*  
 * Admin Safe Password Configuration
 * 
 * SECURITY WARNING: This password should be hashed, not stored in plain text!
 * 
 * RECOMMENDED APPROACH:
 * 1. Use password_hash() to generate hash: password_hash($password, PASSWORD_BCRYPT)
 * 2. Store hash in database, not in configuration file
 * 3. Verify with password_verify() during authentication
 * 4. Implement account lockout after failed attempts
 * 5. Add multi-factor authentication for admin access
 * 
 * Current implementation (INSECURE - for backward compatibility only):
 */
$conf['safepass'] = getEnv('ADMIN_SAFE_PASSWORD', 'CHANGE_ME_IMMEDIATELY');

// Warn if using default/weak password
if ($conf['safepass'] === 'CHANGE_ME_IMMEDIATELY' || 
    $conf['safepass'] === '123456' || 
    strlen($conf['safepass']) < 12) {
    error_log('WARNING: Admin password is weak or default. Please use a strong password!');
}

/* Cache Configuration */
$conf['cache']['expire'] = 0;
$conf['cache']['dir'] = '_blast_buffer/';

/* URL Configuration */
$conf['url_modal'] = 2;

/* Template Configuration */
$conf['action']['template'] = 'blast_Front/admin/';
$conf['action']['modals'] = 'blast_back/admin/';

/* Member Configuration */
$conf['member']['sessionTime'] = (int)getEnv('SESSION_LIFETIME', 900); // 15 minutes default

/* Node Access Configuration */
$conf['node']['access'] = getEnv('NODE_ACCESS_URL', 'http://localhost:65531');

/* Error Reporting Configuration */
$appEnv = getEnv('APP_ENV', 'production');
$appDebug = getEnv('APP_DEBUG', 'false') === 'true';

if ($appEnv === 'production') {
    error_reporting(E_ERROR & ~E_NOTICE);
    ini_set('display_errors', 'Off');
    ini_set('log_errors', 'On');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
}

/* Timezone Configuration */
ini_set('date.timezone', getEnv('APP_TIMEZONE', 'asia/shanghai'));

/*
 * SECURITY NOTE:
 * 
 * This configuration file should be further secured by:
 * 1. Implementing proper authentication and authorization
 * 2. Adding CSRF token validation
 * 3. Implementing rate limiting for login attempts
 * 4. Adding IP whitelisting for admin access
 * 5. Implementing session security (secure cookies, HttpOnly, SameSite)
 * 6. Adding security headers (CSP, X-Frame-Options, etc.)
 * 7. Implementing proper logging and monitoring
 */
?>
