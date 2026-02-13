# Security Implementation Guide

## 🔴 CRITICAL: Read This First

This repository contains **CRITICAL SECURITY VULNERABILITIES** that have been documented in `SECURITY_AUDIT_REPORT.md`. This guide provides instructions for securing the application.

## ⚠️ Before Deployment

**DO NOT deploy this application to production without:**
1. Reading the complete security audit report
2. Implementing the security fixes outlined below
3. Rotating all passwords and credentials
4. Running security tests

---

## Immediate Actions Required

### 1. Secure Configuration Setup

#### Step 1: Remove Hardcoded Credentials

**Files with hardcoded credentials that MUST be updated:**
- `web/xingcai_config.php` - Contains database password
- `admin/admin.config.php` - Contains database and admin password

**Action:**
```bash
# Backup original files (DO NOT commit these)
cp web/xingcai_config.php web/xingcai_config.php.backup
cp admin/admin.config.php admin/admin.config.php.backup

# Copy secure templates
cp web/xingcai_config.example.php web/xingcai_config.php
cp admin/admin.config.example.php admin/admin.config.php
```

#### Step 2: Create Environment File

```bash
# Copy the template
cp .env.example .env

# Set secure permissions (owner read/write only)
chmod 600 .env
```

#### Step 3: Generate Strong Credentials

**Database Password:**
```bash
# Generate a strong 32-character password
openssl rand -base64 32
```

**Admin Password:**
```bash
# Generate a strong 32-character password
openssl rand -base64 32
```

**Application Key:**
```bash
# Generate a random 32-character key
openssl rand -hex 32
```

#### Step 4: Edit .env File

Edit `.env` with a text editor and fill in the generated values:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_actual_database_name
DB_USER=app_user  # NOT root!
DB_PASSWORD=your_generated_strong_password_here
DB_CHARSET=utf8
DB_PREFIX=blast_

ADMIN_SAFE_PASSWORD=your_generated_admin_password_here

APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Shanghai

SESSION_LIFETIME=900

APP_KEY=your_generated_app_key_here
```

#### Step 5: Create Database User with Limited Privileges

```sql
-- Connect to MySQL as root
mysql -u root -p

-- Create dedicated database user
CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'your_generated_strong_password';

-- Grant only required privileges (NOT ALL)
GRANT SELECT, INSERT, UPDATE, DELETE ON your_database.* TO 'app_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

---

## Security Fixes to Implement

### Priority 1: Database Security

#### A. Migrate from mysql_* to PDO

**Current (INSECURE):**
```php
$config = mysql_connect("127.0.0.1","root","password");
mysql_select_db("database");
mysql_query("SELECT * FROM users WHERE id = " . $_GET['id']);
```

**Fixed (SECURE):**
```php
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=database;charset=utf8',
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Use prepared statements
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_GET['id']]);
    $user = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error. Please contact support.");
}
```

#### B. Replace Blacklist Filtering with Prepared Statements

**Never filter SQL keywords.** Use prepared statements instead:

```php
// BAD - Blacklist filtering (easily bypassed)
$str = preg_replace("/select/i", "", $str);

// GOOD - Prepared statements
$stmt = $pdo->prepare("SELECT * FROM table WHERE column = ?");
$stmt->execute([$userInput]);
```

---

### Priority 2: Input Validation

#### Implement Proper Input Sanitization

```php
// For HTML output
function sanitizeHtml($input) {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// For integer values
function sanitizeInt($input) {
    return filter_var($input, FILTER_VALIDATE_INT);
}

// For email
function sanitizeEmail($input) {
    return filter_var($input, FILTER_SANITIZE_EMAIL);
}

// For URL
function sanitizeUrl($input) {
    return filter_var($input, FILTER_SANITIZE_URL);
}
```

---

### Priority 3: Authentication Security

#### Implement Secure Password Hashing

```php
// Registration - Hash password
$hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

// Store $hashedPassword in database

// Login - Verify password
$stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($plainPassword, $user['password'])) {
    // Password correct - create session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
} else {
    // Password incorrect
    // Log failed attempt
    error_log("Failed login attempt for user: $username from IP: " . $_SERVER['REMOTE_ADDR']);
}
```

#### Implement Account Lockout

```php
function checkLoginAttempts($username) {
    // Check failed attempts in last 15 minutes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE username = ? 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$username]);
    $result = $stmt->fetch();
    
    if ($result['attempts'] >= 5) {
        die("Account temporarily locked due to multiple failed login attempts.");
    }
}

function recordFailedLogin($username) {
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (username, ip_address, attempted_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$username, $_SERVER['REMOTE_ADDR']]);
}
```

---

### Priority 4: File Security

#### Secure File Upload

```php
function secureFileUpload($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload failed");
    }
    
    // Validate file size
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        throw new Exception("File too large");
    }
    
    // Validate file type by content (not extension)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception("Invalid file type");
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    
    // Store outside web root if possible
    $uploadDir = '/var/www/uploads/'; // Outside public_html
    $destination = $uploadDir . $filename;
    
    // Move file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to save file");
    }
    
    return $filename;
}
```

#### Prevent Path Traversal

```php
function sanitizeFilePath($filename) {
    // Remove directory traversal attempts
    $filename = basename($filename);
    
    // Remove dangerous characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    return $filename;
}
```

---

### Priority 5: Session Security

```php
// Configure secure sessions
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Requires HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

session_start();

// Regenerate session ID on privilege change
function loginUser($userId) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}

// Validate session
function validateSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Verify IP hasn't changed (optional, may break with mobile networks)
    if (isset($_SESSION['ip_address']) && 
        $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        return false;
    }
    
    return true;
}
```

---

### Priority 6: CSRF Protection

```php
// Generate CSRF token
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('CSRF token validation failed');
    }
}

// In forms
echo '<input type="hidden" name="csrf_token" value="' . sanitizeHtml(generateCsrfToken()) . '">';

// On form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    // Process form
}
```

---

### Priority 7: Security Headers

Add to `.htaccess` or web server configuration:

```apache
# Prevent clickjacking
Header always set X-Frame-Options "DENY"

# XSS Protection
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"

# Content Security Policy
Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';"

# HSTS (only if using HTTPS)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Referrer Policy
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Permissions Policy
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
```

---

## Testing Checklist

After implementing security fixes:

- [ ] All database credentials moved to .env
- [ ] .env file has 600 permissions
- [ ] Database user has minimal required privileges
- [ ] Prepared statements used for all queries
- [ ] Input validation implemented
- [ ] Output encoding implemented
- [ ] Password hashing implemented
- [ ] CSRF protection added
- [ ] Session security configured
- [ ] Security headers added
- [ ] File upload validation implemented
- [ ] Error logging configured (not displayed to users)
- [ ] Run OWASP ZAP security scan
- [ ] Run SQL injection tests
- [ ] Run XSS tests
- [ ] Test authentication and authorization
- [ ] Review all code accepting user input

---

## Monitoring and Maintenance

### Enable Security Logging

```php
// Log security events
function logSecurityEvent($event, $details) {
    $logFile = '/var/log/app/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'];
    $message = "[$timestamp] [$ip] $event: $details\n";
    error_log($message, 3, $logFile);
}

// Usage examples
logSecurityEvent('AUTH_FAILURE', "Failed login for user: $username");
logSecurityEvent('SQL_INJECTION_ATTEMPT', "Suspicious input detected");
logSecurityEvent('FILE_UPLOAD', "File uploaded: $filename");
```

### Regular Security Tasks

1. **Weekly:**
   - Review security logs
   - Check for failed login attempts
   - Monitor unusual activity

2. **Monthly:**
   - Update dependencies
   - Review access logs
   - Rotate passwords

3. **Quarterly:**
   - Run penetration tests
   - Review and update security policies
   - Security training for developers

---

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [SQL Injection Prevention](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html)
- [XSS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)

---

## Support

For security concerns, please:
1. Do NOT create public GitHub issues for security vulnerabilities
2. Review the SECURITY_AUDIT_REPORT.md
3. Contact the development team privately
4. Follow responsible disclosure practices

---

**Last Updated:** 2026-02-13  
**Version:** 1.0
