# Security Vulnerability Quick Reference

## Critical Issues - Fix Immediately ⚠️

### 1. Hardcoded Database Credentials
**Files:** `admin/admin.config.php`, `web/xingcai_config.php`, `wap/xingcai_config.php`  
**Fix:** Use environment variables, remove from git history, rotate credentials  
**Risk:** Complete database compromise

### 2. SQL Injection
**Files:** `*/blast_sqlin.php`, `*/xingcai_sqlin.php`  
**Fix:** Migrate to PDO with prepared statements  
**Risk:** Data theft, modification, deletion

### 3. Directory Traversal
**Files:** `admin/blast_back/admin/Database.class.php`  
**Fix:** Use `basename()`, `realpath()`, whitelist validation  
**Risk:** Arbitrary file read/delete/execute

## High Priority Issues 🔥

### 4. Deprecated MySQL Functions
**Files:** Throughout codebase  
**Fix:** Replace `mysql_*` with PDO  
**Risk:** Won't run on PHP 7.0+, security issues

### 5. Weak Password Hashing (MD5)
**Files:** `*/User.class.php`, `*/Team.class.php`, `*/Member.class.php`  
**Fix:** Use `password_hash()` and `password_verify()`  
**Risk:** Easy password cracking

## Medium Priority Issues ⚡

### 6. XSS Vulnerabilities
**Files:** Template files  
**Fix:** Use `htmlspecialchars()` on all output, implement CSP  
**Risk:** Session hijacking, phishing

### 7. Missing CSRF Protection
**Files:** All forms  
**Fix:** Implement CSRF tokens  
**Risk:** Unauthorized actions

### 8. Insecure Sessions
**Files:** Configuration files  
**Fix:** Set secure cookie flags  
**Risk:** Session hijacking

### 9. Information Disclosure
**Files:** Configuration files  
**Fix:** Disable error display, enable logging  
**Risk:** System information leakage

### 10. File Upload Vulnerabilities (Potential)
**Files:** Upload directories  
**Fix:** Validate file type, use random names, store outside webroot  
**Risk:** Remote code execution

## Quick Fixes

### Fix 1: Move Credentials to Environment Variables

```php
// OLD (BAD):
$conf['db']['password']='dYAd4KeDY6ctczmN';

// NEW (GOOD):
$conf['db']['password'] = getenv('DB_PASSWORD');
```

### Fix 2: Use PDO Prepared Statements

```php
// OLD (BAD):
$id = wjStrFilter($_GET['id']);
$sql = "SELECT * FROM members WHERE uid=$id";
$result = mysql_query($sql);

// NEW (GOOD):
$stmt = $pdo->prepare("SELECT * FROM members WHERE uid = ?");
$stmt->execute([$_GET['id']]);
$result = $stmt->fetch();
```

### Fix 3: Use Secure Password Hashing

```php
// OLD (BAD):
$password = md5($_POST['password']);

// NEW (GOOD):
$password = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);

// Verify:
if (password_verify($_POST['password'], $user['password'])) {
    // Success
}
```

### Fix 4: Prevent Directory Traversal

```php
// OLD (BAD):
$file = $_POST['File'];
unlink($dataDir . $file);

// NEW (GOOD):
$file = basename($_POST['File']);
if (!preg_match('/^[a-zA-Z0-9_-]+\.sql$/', $file)) {
    throw new Exception("Invalid filename");
}
$fullPath = realpath($dataDir . DIRECTORY_SEPARATOR . $file);
if (!$fullPath || strpos($fullPath, $dataDir) !== 0) {
    throw new Exception("Access denied");
}
unlink($fullPath);
```

### Fix 5: Add CSRF Protection

```php
// Generate token:
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// In form:
<input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">

// Validate:
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('CSRF validation failed');
}
```

### Fix 6: Escape Output for XSS Prevention

```php
// In templates:
<?= htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8') ?>
```

### Fix 7: Secure Session Configuration

```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // HTTPS only
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
```

### Fix 8: Disable Error Display in Production

```php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php-errors.log');
```

## Immediate Actions Required

1. **[ ] Remove credentials from git:**
   ```bash
   git filter-branch --force --index-filter \
     'git rm --cached --ignore-unmatch admin/admin.config.php web/xingcai_config.php wap/xingcai_config.php' \
     --prune-empty --tag-name-filter cat -- --all
   ```

2. **[ ] Rotate all database passwords**

3. **[ ] Add config files to .gitignore:**
   ```
   *config.php
   .env
   ```

4. **[ ] Create sanitized config templates:**
   ```php
   // config.php.example
   $conf['db']['password'] = 'YOUR_PASSWORD_HERE';
   ```

5. **[ ] Run security scanner**

6. **[ ] Conduct penetration test**

## Testing Checklist

After fixes, verify:

- [ ] SQL injection: Try `' OR '1'='1` in all inputs
- [ ] XSS: Try `<script>alert(1)</script>` in all inputs
- [ ] CSRF: Submit form from external site
- [ ] Path traversal: Try `../../../etc/passwd` in file operations
- [ ] Password strength: Verify bcrypt hashes in database
- [ ] Session security: Check cookie flags in browser
- [ ] Error handling: Cause errors, verify no sensitive info shown

## Security Monitoring

Implement:
- [ ] Web Application Firewall (WAF)
- [ ] Intrusion Detection System (IDS)
- [ ] Log monitoring and alerting
- [ ] Regular security scans
- [ ] Dependency vulnerability scanning

## Resources

- **OWASP Top 10:** https://owasp.org/www-project-top-ten/
- **PHP Security Cheat Sheet:** https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html
- **SQL Injection Prevention:** https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html
- **XSS Prevention:** https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html

## Contact

For questions or to report new vulnerabilities, contact the security team.

---

**Last Updated:** 2026-02-13  
**Version:** 1.0  
**Classification:** INTERNAL USE ONLY
