<?php
/**
 * Secure Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to xingcai_config.php
 * 2. Create a .env file based on .env.example
 * 3. Fill in your actual credentials in .env
 * 4. Ensure .env is in .gitignore
 * 5. Set file permissions: chmod 600 .env
 * 
 * SECURITY NOTES:
 * - NEVER commit files containing real credentials
 * - Use strong, unique passwords
 * - Rotate passwords regularly
 * - Use dedicated database user with minimal permissions
 */

require_once('xingcai_sqlin.php');

// Load environment variables from .env file
function loadEnv($path = '.env') {
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
loadEnv(__DIR__ . '/.env');

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

// Build DSN
$conf['db']['dsn'] = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $dbhost,
    $dbname,
    $dbcharset
);

$conf['db']['user'] = $dbuser;
$conf['db']['password'] = $dbpass;
$conf['db']['charset'] = $dbcharset;
$conf['db']['prename'] = $dbprefix;

/* Cache Configuration */
$conf['cache']['expire'] = 0;
$conf['cache']['dir'] = '_xingcai_buffer/';

/* URL Configuration */
$conf['url_modal'] = 2;

/* Template Configuration */
$conf['action']['template'] = 'xingcai_Front/';
$conf['action']['modals'] = 'xingcai_back/';

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
ini_set('date.timezone', getEnv('APP_TIMEZONE', 'PRC'));

/* Time Range Configuration */
if (strtotime(date('Y-m-d', time())) > strtotime(date('Y-m-d', time()))) {
    $GLOBALS['fromTime'] = strtotime(date('Y-m-d', strtotime("-1 day")));
    $GLOBALS['toTime'] = strtotime(date('Y-m-d', time()));
} else {
    $GLOBALS['fromTime'] = strtotime(date('Y-m-d'));
    $GLOBALS['toTime'] = strtotime(date('Y-m-d', strtotime("+1 day")));
}

/* 
 * DEPRECATED: mysql_* functions
 * 
 * WARNING: The code below uses deprecated mysql_* functions that were 
 * removed in PHP 7.0. This is a CRITICAL security vulnerability.
 * 
 * RECOMMENDATION: Migrate to PDO or MySQLi with prepared statements
 * 
 * This connection is maintained for backward compatibility but should
 * be removed and replaced with secure alternatives.
 */
error_reporting(0);

// SECURITY WARNING: This uses deprecated and insecure mysql_connect
// TODO: Replace with PDO/MySQLi prepared statements
$config = mysql_connect($dbhost, $dbuser, $dbpass) or die("Mysql Connect Error");
mysql_select_db($dbname);
mysql_query("SET NAMES UTF8");

/*
 * SECURITY NOTE: 
 * 
 * After migration to PDO/MySQLi, replace the above code with:
 * 
 * try {
 *     $pdo = new PDO($conf['db']['dsn'], $conf['db']['user'], $conf['db']['password'], [
 *         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 *         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
 *         PDO::ATTR_EMULATE_PREPARES => false,
 *     ]);
 * } catch (PDOException $e) {
 *     error_log("Database connection failed: " . $e->getMessage());
 *     die("Database connection error. Please check logs.");
 * }
 */
?>
