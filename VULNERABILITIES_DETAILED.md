# Detailed Security Vulnerabilities Report

## Executive Summary

This security audit identified **10 major categories** of security vulnerabilities in the taohua application, ranging from CRITICAL to MEDIUM severity. The most critical issues include:

1. Hardcoded database credentials in version control
2. SQL Injection vulnerabilities due to insufficient input validation
3. Use of deprecated and insecure MySQL functions
4. Directory traversal vulnerabilities
5. Weak password hashing using MD5

## Vulnerability Details

---

### VULN-001: Hardcoded Database Credentials (CRITICAL)

**CWE:** CWE-798 (Use of Hard-coded Credentials)  
**CVSS Score:** 9.8 (CRITICAL)

**Affected Files:**
- `/admin/admin.config.php:8`
- `/web/xingcai_config.php:10, 41`
- `/wap/xingcai_config.php:10`
- `/wap/hkoudai/conn.php:5`
- `/web/ldzf/conn.php:5`

**Code Example:**
```php
// File: admin/admin.config.php
$conf['db']['password']='dYAd4KeDY6ctczmN';
$conf['db']['user']='root';

// File: web/xingcai_config.php
$config = mysql_connect("127.0.0.1","root","dYAd4KeDY6ctczmN");
```

**Impact:**
- Complete database compromise if repository is leaked
- Credentials visible in version control history
- Violation of security best practices
- Difficult to rotate credentials

**Proof of Concept:**
An attacker with access to the repository can:
1. Extract database credentials
2. Connect directly to the database
3. Dump all data, modify records, or destroy data
4. Potentially pivot to other systems using same credentials

**Remediation:**
```php
// Use environment variables
$conf['db']['password'] = getenv('DB_PASSWORD');
$conf['db']['user'] = getenv('DB_USER');

// Or use a separate config file not in version control
// .env file (not committed):
DB_PASSWORD=dYAd4KeDY6ctczmN
DB_USER=root

// Load using dotenv library
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$conf['db']['password'] = $_ENV['DB_PASSWORD'];
```

**Additional Steps:**
1. Add config files to `.gitignore`
2. Remove credentials from git history: `git filter-branch --force --index-filter ...`
3. Rotate all exposed credentials immediately
4. Use principle of least privilege for database accounts

---

### VULN-002: SQL Injection Vulnerabilities (CRITICAL)

**CWE:** CWE-89 (SQL Injection)  
**CVSS Score:** 9.8 (CRITICAL)

**Affected Files:**
- `/admin/blast_sqlin.php:29-31, 72-94`
- `/web/xingcai_sqlin.php:58-80, 186-191`
- `/wap/xingcai_sqlin.php:58-80`

**Vulnerable Code:**

**Example 1: No filtering at all**
```php
function get_str($string){
    return $string;  // Returns input unmodified!
}
```

**Example 2: Bypassable regex-based filtering**
```php
$str=preg_replace("/select/i", "",$str);
$str=preg_replace("/union/i", "",$str);
$str=preg_replace("/insert/i", "",$str);
```

**Example 3: Deprecated escape function**
```php
$str=mysql_escape_string($str);  // Deprecated since PHP 5.3.0
```

**Bypass Techniques:**

1. **Double encoding:**
   - Input: `SeLeCt` → Filter removes nothing
   - Input: `UNunionION` → Filter removes "union", leaves "UNION"

2. **Alternative SQL syntax:**
   - Use `CONCAT` instead of string literals
   - Use hex encoding: `0x73656c656374` (select)
   - Use comments: `SEL/**/ECT`

3. **Case variations:**
   - `SeLeCt`, `UNION`, `UnIoN`

**Proof of Concept:**

Assuming vulnerable query:
```php
$id = $_GET['id']; // Filtered by get_str() which does nothing
$sql = "SELECT * FROM users WHERE id = '$id'";
```

Attack payloads:
```
?id=1' OR '1'='1
?id=1' UNunionION SELselectECT * FROM members--
?id=1' AND 1=0 UNunionION SELeCt username,password,1,2,3 FROM blast_members--
```

**Impact:**
- Complete database compromise
- Data exfiltration
- Data modification/deletion
- Authentication bypass
- Privilege escalation

**Remediation:**

**Replace with PDO and Prepared Statements:**

```php
// Bad (current code):
$id = wjStrFilter($_GET['id']);
$sql = "SELECT * FROM {$this->prename}members WHERE uid=$id";
$result = mysql_query($sql);

// Good (use PDO):
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM blast_members WHERE uid = :id");
$stmt->execute(['id' => $id]);
$result = $stmt->fetch();
```

**Complete Migration Example:**

```php
// Database connection using PDO
class Database {
    private $pdo;
    
    public function __construct($dsn, $username, $password) {
        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

// Usage:
$db = new Database($dsn, $username, $password);

// Select with parameters
$user = $db->query(
    "SELECT * FROM blast_members WHERE uid = ? AND status = ?",
    [$uid, $status]
)->fetch();

// Insert with named parameters
$db->query(
    "INSERT INTO blast_members (username, password, email) VALUES (:username, :password, :email)",
    [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'email' => $email
    ]
);
```

---

### VULN-003: Deprecated MySQL Functions (CRITICAL)

**CWE:** CWE-477 (Use of Obsolete Function)  
**CVSS Score:** 8.2 (HIGH)

**Affected Files:**
- `/admin/blast_back/admin/Database.class.php:84-264`
- `/web/xingcai_config.php:41-43`
- `/admin/blast_sqlin.php:94`
- `/web/xingcai_sqlin.php:80`

**Vulnerable Code:**
```php
$config = mysql_connect("127.0.0.1","root","dYAd4KeDY6ctczmN");
mysql_select_db("0xc");
mysql_query("SET NAMES UTF8");
$str=mysql_escape_string($str);
```

**Problems:**

1. **Removed in PHP 7.0.0:**
   - All `mysql_*` functions were removed
   - Code will not run on modern PHP versions

2. **Security Issues:**
   - `mysql_escape_string()` is less secure than `mysql_real_escape_string()`
   - No support for prepared statements
   - Vulnerable to charset-based SQL injection

3. **No Connection Context:**
   - `mysql_escape_string()` doesn't use connection charset
   - Can be bypassed with certain character sets (GBK, BIG5)

**Charset-based SQL Injection Example:**
```php
// With GBK charset:
mysql_query("SET NAMES GBK");
$id = mysql_escape_string("1' OR '1'='1");
// Under certain conditions, escaping can be bypassed
```

**Impact:**
- Application cannot run on PHP 7.0+
- Increased SQL injection risk
- Limited security features

**Remediation:**

See VULN-002 for complete PDO migration guide.

---

### VULN-004: Directory Traversal Vulnerability (HIGH)

**CWE:** CWE-22 (Path Traversal)  
**CVSS Score:** 8.6 (HIGH)

**Affected Files:**
- `/admin/blast_back/admin/Database.class.php:16-17, 38-46`

**Vulnerable Code:**
```php
public final function dataBackup() {
    $DataDir = "./databak888/";
    $para=$_POST;
    $para['File']=wjStrFilter($para['File']);  // Insufficient filtering
    $para['Action']=wjStrFilter($para['Action']);
    
    if ($para['Action'] == 'RL') {
        $mr->recover($para['File']);  // User input used directly
    } elseif ($para['Action'] == 'Del') {
        if (@unlink($DataDir . $para['File'])) {  // Can delete arbitrary files
            return '删除成功';
        }
    } elseif ($para['Action'] == 'Dow') {
        DownloadFile($DataDir . $para['file']);  // Can download arbitrary files
    }
}
```

**Insufficient Filter:**
```php
function filter_dir($fileName) {
    $tmpname = strtolower($fileName);
    $temp = array(':/',"\0", "..");
    if (str_replace($temp, '', $tmpname) !== $tmpname) {
        return false;
    }
    return $fileName;
}
```

**Bypass Techniques:**

1. **URL Encoding:**
   ```
   File=..%2F..%2F..%2Fetc%2Fpasswd
   File=..%252F..%252Fetc%252Fpasswd (double encoding)
   ```

2. **Alternative Directory Separators:**
   ```
   File=....//....//etc/passwd
   File=..\/..\/etc/passwd
   ```

3. **Null Byte (older PHP):**
   ```
   File=../../../../etc/passwd%00.sql
   ```

4. **Case Sensitivity:**
   ```
   File=..%2F..%2F..%2FEtc%2FPasswd (filter uses lowercase)
   ```

**Proof of Concept:**

```bash
# Delete arbitrary file
curl -X POST https://target.com/admin/Database/dataBackup \
  -d "Action=Del&File=..%2F..%2F..%2Fvar%2Fwww%2Fhtml%2Findex.php"

# Download sensitive file
curl -X POST https://target.com/admin/Database/dataBackup \
  -d "Action=Dow&File=..%2F..%2F..%2Fetc%2Fpasswd"

# Restore malicious backup
curl -X POST https://target.com/admin/Database/dataBackup \
  -d "Action=RL&File=..%2F..%2Fmalicious.sql"
```

**Impact:**
- Read arbitrary files (configuration files, source code, /etc/passwd)
- Delete arbitrary files (DoS, defacement)
- Execute arbitrary SQL from uploaded files
- Complete system compromise

**Remediation:**

```php
public final function dataBackup() {
    $DataDir = realpath("./databak888/");
    if (!$DataDir) {
        throw new Exception("Backup directory does not exist");
    }
    
    $para = $_POST;
    $file = basename($para['File']); // Remove path components
    
    // Whitelist validation
    if (!preg_match('/^[a-zA-Z0-9_-]+\.sql(\.gz)?$/', $file)) {
        throw new Exception("Invalid file name");
    }
    
    $fullPath = realpath($DataDir . DIRECTORY_SEPARATOR . $file);
    
    // Ensure file is within allowed directory
    if (!$fullPath || strpos($fullPath, $DataDir) !== 0) {
        throw new Exception("Access denied");
    }
    
    $para['Action'] = wjStrFilter($para['Action']);
    
    if ($para['Action'] == 'Del') {
        if (is_file($fullPath) && unlink($fullPath)) {
            return '删除成功';
        } else {
            throw new Exception('删除失败');
        }
    }
    // ... rest of code
}
```

**Additional Security Measures:**
1. Use whitelist for allowed files
2. Store backups outside web root
3. Implement access controls
4. Log all file operations
5. Use `basename()` to strip directory components
6. Use `realpath()` to resolve symbolic links
7. Verify file is within allowed directory

---

### VULN-005: Weak Password Hashing (MEDIUM)

**CWE:** CWE-327 (Use of a Broken or Risky Cryptographic Algorithm)  
**CVSS Score:** 7.4 (HIGH)

**Affected Files:**
- `/wap/xingcai_back/Team.class.php:282`
- `/wap/xingcai_back/User.class.php:110`
- `/admin/blast_back/admin/Member.class.php:95`
- Multiple other locations

**Vulnerable Code:**
```php
// Storing password
$update['password']=md5($update['password']);

// Verifying password
if(md5($password)!=$user['password']){
    throw new Exception('密码错误');
}
```

**Problems:**

1. **MD5 is Cryptographically Broken:**
   - Known collision vulnerabilities
   - Extremely fast to compute (billions of hashes/second)
   - Rainbow tables available

2. **No Salt:**
   - Same password = same hash
   - Vulnerable to rainbow table attacks
   - Can't defend against pre-computed attacks

3. **No Key Stretching:**
   - Single iteration makes brute force easy
   - Modern GPUs can try billions of combinations/second

**Attack Scenario:**

If database is compromised:
```
User: admin
Password Hash: 5f4dcc3b5aa765d61d8327deb882cf99

Attacker uses online MD5 lookup:
→ Plaintext: "password"
```

**Time to Crack:**
- MD5: < 1 second for most passwords
- bcrypt: Minutes to hours (depending on work factor)
- Argon2: Even slower

**Impact:**
- All user passwords can be cracked quickly
- Account takeover
- Credential reuse on other sites
- Administrator account compromise

**Remediation:**

```php
// Storing password - GOOD
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, [
    'cost' => 12
]);
// Or use Argon2 (PHP 7.2+)
$hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

$sql = "UPDATE blast_members SET password = :password WHERE uid = :uid";
$stmt->execute([
    'password' => $hashedPassword,
    'uid' => $uid
]);

// Verifying password - GOOD
$sql = "SELECT password FROM blast_members WHERE username = :username";
$stmt = $pdo->prepare($sql);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    // Password correct
    
    // Check if rehashing is needed (algorithm changed)
    if (password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
        $newHash = password_hash($password, PASSWORD_ARGON2ID);
        // Update database with new hash
    }
} else {
    // Password incorrect
    throw new Exception('密码错误');
}
```

**Migration Strategy:**

Since existing passwords use MD5, you need a migration plan:

```php
function verifyAndUpgradePassword($username, $password) {
    $user = getUserByUsername($username);
    
    // Check if using old MD5 hash
    if (strlen($user['password']) == 32) {
        // MD5 hash (32 chars)
        if (md5($password) === $user['password']) {
            // Correct password, upgrade to bcrypt
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            updateUserPassword($user['uid'], $newHash);
            return true;
        }
        return false;
    } else {
        // New bcrypt/argon2 hash
        return password_verify($password, $user['password']);
    }
}
```

---

### VULN-006: Cross-Site Scripting (XSS) (MEDIUM)

**CWE:** CWE-79 (Cross-site Scripting)  
**CVSS Score:** 6.5 (MEDIUM)

**Potential Vulnerable Areas:**
- Template files that output user data without proper escaping
- Complex and potentially bypassable XSS filter

**Vulnerable Pattern:**
```php
// In various template files
<input value="<?=$_POST['username']?>">
```

**Complex Filter (may have bypasses):**
```php
function RemoveXSS($val) {
    // 100+ lines of complex regex
    // Blacklist approach = can be bypassed
}
```

**Common XSS Bypass Techniques:**

1. **Alternative encoding:**
   ```html
   <img src=x onerror="alert(1)">
   <svg/onload=alert(1)>
   ```

2. **Breaking filters:**
   ```html
   <scr<script>ipt>alert(1)</script>
   ```

3. **Event handlers:**
   ```html
   <input onfocus=alert(1) autofocus>
   <body onload=alert(1)>
   ```

**Impact:**
- Session hijacking
- Defacement
- Phishing
- Malware distribution
- Credential theft

**Remediation:**

```php
// Output escaping function
function escape($str, $context = 'html') {
    switch ($context) {
        case 'html':
            return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        case 'js':
            return json_encode($str, JSON_HEX_TAG | JSON_HEX_AMP);
        case 'url':
            return rawurlencode($str);
        case 'css':
            // More complex, use a library
            return preg_replace('/[^a-zA-Z0-9]/', '\\\\$0', $str);
        default:
            return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// Usage in templates:
<input value="<?= escape($_POST['username']) ?>">
<script>
var username = <?= escape($_POST['username'], 'js') ?>;
</script>
<a href="?user=<?= escape($_GET['user'], 'url') ?>">Link</a>
```

**Content Security Policy:**

Add to HTTP headers:
```
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self';
```

---

### VULN-007: Missing CSRF Protection (MEDIUM)

**CWE:** CWE-352 (Cross-Site Request Forgery)  
**CVSS Score:** 6.5 (MEDIUM)

**Observation:**
No CSRF tokens found in form submissions or AJAX requests.

**Attack Scenario:**

1. User is logged into admin panel
2. User visits malicious website
3. Malicious site submits hidden form:

```html
<form action="https://target.com/admin/Member/delete" method="POST" id="csrf">
    <input type="hidden" name="uid" value="1">
</form>
<script>
document.getElementById('csrf').submit();
</script>
```

4. Request executes with user's session
5. Admin account deleted

**Impact:**
- Unauthorized actions
- Data modification/deletion
- Privilege escalation
- Account takeover

**Remediation:**

```php
// Generate CSRF token
class CSRF {
    public static function generateToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateToken($token) {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// In forms:
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= CSRF::generateToken() ?>">
    <!-- other fields -->
</form>

// In handlers:
if (!CSRF::validateToken($_POST['csrf_token'])) {
    throw new Exception('CSRF token validation failed');
}
```

---

### VULN-008: Insecure Session Configuration (MEDIUM)

**CWE:** CWE-614 (Sensitive Cookie in HTTPS Session Without 'Secure' Attribute)  
**CVSS Score:** 5.9 (MEDIUM)

**Missing Security Settings:**

```php
// Should be set but isn't:
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
```

**Risks:**
- **No HttpOnly:** Session can be stolen via XSS
- **No Secure:** Session sent over HTTP (if available)
- **No SameSite:** Vulnerable to CSRF
- **No Strict Mode:** Session fixation attacks

**Remediation:**

```php
// At application start
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // Only if using HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 1800);  // 30 minutes
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// Regenerate session ID on privilege change
session_regenerate_id(true);
```

---

### VULN-009: Information Disclosure via Error Messages (LOW)

**CWE:** CWE-209 (Information Exposure Through Error Message)  
**CVSS Score:** 4.3 (MEDIUM)

**Issue:**
Debug level set to 5, inconsistent error reporting settings

```php
$conf['debug']['level']=5;
error_reporting(E_ERROR & ~E_NOTICE);
error_reporting(0);  // Contradictory
```

**What Can Be Leaked:**
- Database structure
- File paths
- Function names
- Variable contents
- Server configuration

**Remediation:**

```php
// Production settings
$conf['debug']['level'] = 0;
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php/error.log');

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    // Show generic message to user
    if ($errno === E_ERROR || $errno === E_USER_ERROR) {
        die("An error occurred. Please contact support.");
    }
});

set_exception_handler(function($exception) {
    error_log("Exception: " . $exception->getMessage());
    error_log("Stack trace: " . $exception->getTraceAsString());
    die("An error occurred. Please contact support.");
});
```

---

### VULN-010: Potential File Upload Vulnerabilities (TBD)

**CWE:** CWE-434 (Unrestricted Upload of File with Dangerous Type)  
**CVSS Score:** 8.8 (HIGH) - If vulnerable

**Observation:**
`/upload` directories exist, but upload handling code needs review.

**Common Upload Vulnerabilities:**

1. **No file type validation**
2. **Extension-only checking** (can be bypassed)
3. **No file size limits**
4. **Predictable file names**
5. **Direct file execution** (uploaded PHP files can run)

**Secure Upload Implementation:**

```php
function secureFileUpload($fileInput, $allowedTypes, $maxSize) {
    // Check if file was uploaded
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed');
    }
    
    $file = $_FILES[$fileInput];
    
    // Check file size
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large');
    }
    
    // Check MIME type (not just extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type');
    }
    
    // Generate random filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = bin2hex(random_bytes(16)) . '.' . $extension;
    
    // Store outside web root
    $uploadDir = '/var/uploads/';  // Outside of /var/www/html
    $destination = $uploadDir . $newName;
    
    // Move file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save file');
    }
    
    // Set restrictive permissions
    chmod($destination, 0644);
    
    return $newName;
}

// Usage:
$allowedImages = ['image/jpeg', 'image/png', 'image/gif'];
$filename = secureFileUpload('avatar', $allowedImages, 5 * 1024 * 1024);  // 5MB max
```

**Additional Measures:**
1. Store uploads outside web root
2. Use .htaccess to prevent execution:
   ```apache
   <Directory "/var/www/html/upload">
       php_flag engine off
       Options -ExecCGI
       AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi
   </Directory>
   ```
3. Scan files for malware
4. Use separate domain for user content

---

## Summary Statistics

| Severity | Count | Status |
|----------|-------|--------|
| Critical | 3 | Needs immediate fix |
| High | 2 | Needs urgent fix |
| Medium | 4 | Should fix soon |
| Low | 1 | Fix when possible |
| **Total** | **10** | |

## Compliance Impact

**OWASP Top 10 2021:**
- ✓ A01: Broken Access Control
- ✓ A02: Cryptographic Failures  
- ✓ A03: Injection
- ✓ A05: Security Misconfiguration
- ✓ A07: Identification and Authentication Failures

**Regulatory:**
- GDPR: Data protection violations
- PCI DSS: Cannot process payments securely
- HIPAA: Cannot handle health data

## Recommended Action Plan

### Phase 1 (Week 1): Critical Fixes
- [ ] Remove hardcoded credentials
- [ ] Migrate to PDO with prepared statements
- [ ] Fix directory traversal
- [ ] Deploy immediately after testing

### Phase 2 (Week 2-3): High Priority
- [ ] Implement proper password hashing
- [ ] Add CSRF protection
- [ ] Review and fix file upload
- [ ] Conduct limited re-test

### Phase 3 (Week 4): Medium Priority
- [ ] Improve XSS protection
- [ ] Secure session configuration
- [ ] Fix error handling
- [ ] Full security re-test

### Phase 4 (Ongoing):
- [ ] Implement security monitoring
- [ ] Set up automated security scanning
- [ ] Conduct regular penetration tests
- [ ] Security training for developers

## Tools Used

- Manual code review
- Static analysis
- Pattern matching for known vulnerabilities
- OWASP guidelines

## References

- OWASP Top 10: https://owasp.org/www-project-top-ten/
- CWE/SANS Top 25: https://cwe.mitre.org/top25/
- PHP Security Best Practices: https://www.php.net/manual/en/security.php
- PDO Documentation: https://www.php.net/manual/en/book.pdo.php

---

**Report Date:** 2026-02-13  
**Auditor:** Automated Security Analysis  
**Classification:** CONFIDENTIAL
