# Security Audit Report (代码审计报告)
# Taohua Repository Security Assessment

**Audit Date:** 2026-02-13  
**Severity Levels:** 🔴 Critical | 🟠 High | 🟡 Medium | 🟢 Low

---

## Executive Summary

This security audit identified **multiple critical vulnerabilities** in the Taohua codebase that pose significant security risks. The application is a PHP-based gambling/lottery platform with several security weaknesses that could lead to:

- Database compromise through hardcoded credentials
- SQL injection attacks
- Authentication bypass
- Unauthorized file access
- Cross-site scripting (XSS) attacks

**CRITICAL ACTION REQUIRED:** Immediate remediation of hardcoded credentials and SQL injection vulnerabilities is strongly recommended.

---

## 🔴 CRITICAL Vulnerabilities

### 1. Hardcoded Database Credentials (CWE-798)

**Severity:** 🔴 CRITICAL  
**CVSS Score:** 9.8 (Critical)

**Affected Files:**
- `/web/xingcai_config.php` (Lines 6, 9-10, 41)
- `/admin/admin.config.php` (Lines 6-8)

**Details:**
Database credentials are hardcoded directly in configuration files:

```php
// web/xingcai_config.php
$conf['db']['dsn']='mysql:host=localhost;dbname=0xc;charset=utf8';
$conf['db']['user']='root';
$conf['db']['password']='dYAd4KeDY6ctczmN';  // ⚠️ EXPOSED PASSWORD

$config = mysql_connect("127.0.0.1","root","dYAd4KeDY6ctczmN");  // ⚠️ DUPLICATE HARDCODED
```

```php
// admin/admin.config.php
$conf['db']['user']='root';
$conf['db']['password']='dYAd4KeDY6ctczmN';  // ⚠️ EXPOSED PASSWORD
$conf['safepass']='123456';  // ⚠️ WEAK ADMIN PASSWORD
```

**Impact:**
- Anyone with repository access can obtain database credentials
- Root database access enables complete data exfiltration
- Weak admin password ('123456') is easily guessable
- Credentials may be committed to version control history

**Recommendation:**
1. **IMMEDIATE:** Rotate all database passwords
2. Use environment variables for sensitive configuration
3. Implement `.env` files (excluded from version control)
4. Use secure password manager for credential storage
5. Apply principle of least privilege (avoid 'root' user)

---

### 2. Deprecated and Unsafe MySQL Functions (CWE-477)

**Severity:** 🔴 CRITICAL  
**CVSS Score:** 8.1 (High)

**Affected Files:**
- `/web/xingcai_config.php` (Line 41)
- `/web/xingcai_sqlin.php` (Lines 80, 94)
- `/admin/blast_sqlin.php` (Line 94)
- `/admin/blast_back/admin/Database.class.php` (Lines 84-100)

**Details:**

The codebase uses deprecated `mysql_*` functions that:
- Were removed in PHP 7.0+ (application cannot run on modern PHP)
- Do not support prepared statements
- Are vulnerable to SQL injection
- Lack proper error handling

```php
// Deprecated function usage
mysql_connect("127.0.0.1","root","dYAd4KeDY6ctczmN");  // ⚠️ Deprecated
mysql_select_db("0xc");                                 // ⚠️ Deprecated
mysql_escape_string($str);                              // ⚠️ Deprecated & Unsafe
mysql_query("SET NAMES UTF8");                          // ⚠️ Deprecated
mysql_list_dbs();                                       // ⚠️ Deprecated
mysql_tablename($rs,$i);                               // ⚠️ Deprecated
```

**Impact:**
- Application incompatible with PHP 7.0+
- Increased SQL injection risk
- No support or security updates
- mysql_escape_string() does not consider character encoding

**Recommendation:**
1. Migrate to **MySQLi** or **PDO** with prepared statements
2. Use parameterized queries for all database operations
3. Implement proper error handling and logging
4. Update PHP version to 8.x with security patches

---

### 3. SQL Injection Vulnerabilities (CWE-89)

**Severity:** 🔴 CRITICAL  
**CVSS Score:** 9.1 (Critical)

**Affected Files:**
- Multiple files using `wjStrFilter()` for SQL input sanitization
- `/web/xingcai_sqlin.php` (Lines 21-97)
- `/admin/blast_sqlin.php` (Lines 34-97)

**Details:**

The application attempts to prevent SQL injection using blacklist filtering instead of parameterized queries:

```php
// Blacklist approach - INEFFECTIVE
$str=preg_replace("/insert/i", "",$str);
$str=preg_replace("/update/i", "",$str);
$str=preg_replace("/delete/i", "",$str);
$str=preg_replace("/select/i", "",$str);
$str=preg_replace("/union/i", "",$str);
```

**Known Bypass Techniques:**
1. **Case variation bypass:** `SeLeCt`, `INSERT`, `UnIoN`
2. **Double encoding:** `%2553%2545%254C%2545%2543%2554` (URL encoded twice)
3. **Replacement bypass:** `selselectect` → `select` (after filtering)
4. **Concatenation:** `SEL/**/ECT`, `UN/**/ION`
5. **Alternative keywords:** Using `UNION ALL`, `EXEC`, etc.
6. **Character encoding:** Unicode/UTF-7 encoded SQL keywords

**Example Exploit:**
```sql
-- Original: selselectect
-- After preg_replace: select (bypass successful)

-- Original: ununionion
-- After preg_replace: union (bypass successful)
```

**Impact:**
- Complete database compromise
- Data exfiltration of all user information
- Data manipulation/deletion
- Potential remote code execution via `INTO OUTFILE`

**Recommendation:**
1. **NEVER use blacklist filtering for SQL injection prevention**
2. Implement **prepared statements with parameterized queries**
3. Use PDO or MySQLi with bound parameters
4. Apply input validation with whitelisting approach
5. Implement proper error handling (don't expose SQL errors)

---

## 🟠 HIGH Severity Vulnerabilities

### 4. Weak Input Sanitization (CWE-20)

**Severity:** 🟠 HIGH  
**CVSS Score:** 7.5 (High)

**Affected Files:**
- `/admin/blast_sqlin.php` (Lines 23-31, function `get_str()`)

**Details:**

The `get_str()` function provides NO sanitization:

```php
function get_str($string){
    return $string;  // ⚠️ Returns input unchanged!
}
```

This is used to process all POST/GET string parameters:

```php
foreach ($_POST as $post_key=>$post_var) {
    if (is_numeric($post_var)) {
        $post[strtolower($post_key)] = get_int($post_var);
    } else {
        $post[strtolower($post_key)] = get_str($post_var);  // ⚠️ NO FILTERING
    }
}
```

**Impact:**
- XSS attacks via unsanitized input
- HTML injection
- Script injection
- Potential for various injection attacks

**Recommendation:**
1. Implement proper input validation and sanitization
2. Use `htmlspecialchars()` with `ENT_QUOTES` flag
3. Apply context-specific escaping (HTML, JS, SQL, etc.)
4. Implement Content Security Policy (CSP) headers

---

### 5. File Upload and Path Traversal Risks (CWE-22, CWE-434)

**Severity:** 🟠 HIGH  
**CVSS Score:** 8.8 (High)

**Affected Files:**
- `/admin/blast_back/admin/Database.class.php` (Lines 14, 41, 54-57)

**Details:**

Database backup functionality has path traversal vulnerabilities:

```php
$DataDir = "./databak888/";
$para['File']=wjStrFilter($para['File']);  // ⚠️ Insufficient filtering

// Path traversal potential
if ($para['Action'] == 'Del') {
    if (@unlink($DataDir . $para['File'])) {  // ⚠️ Direct concatenation
        return '删除成功';
    }
}

// File download (currently disabled but vulnerable)
function DownloadFile($fileName) {
    readfile($fileName);  // ⚠️ No validation
}
DownloadFile($DataDir . $para['file']);  // ⚠️ Path traversal risk
```

**Bypass Example:**
```
File=../../../../etc/passwd
File=../../../web/xingcai_config.php
```

**Impact:**
- Arbitrary file deletion
- Sensitive file disclosure
- Configuration file exposure
- Potential remote code execution if combined with file upload

**Recommendation:**
1. Validate filenames against whitelist
2. Use `basename()` to strip directory components
3. Implement proper path canonicalization
4. Store files outside web root
5. Use unique, random filenames
6. Check file types by content, not extension

---

### 6. Hardcoded Admin Password (CWE-798)

**Severity:** 🟠 HIGH  
**CVSS Score:** 7.5 (High)

**Affected Files:**
- `/admin/admin.config.php` (Line 12)

**Details:**

```php
$conf['safepass']='123456';  // ⚠️ Extremely weak default password
```

**Impact:**
- Trivial admin authentication bypass
- Complete system compromise
- Unauthorized access to admin panel
- User data exposure

**Recommendation:**
1. Never hardcode passwords
2. Implement secure password hashing (bcrypt, Argon2)
3. Enforce strong password policies
4. Implement multi-factor authentication
5. Add account lockout after failed attempts

---

## 🟡 MEDIUM Severity Issues

### 7. Use of `get_magic_quotes_gpc()` (CWE-676)

**Severity:** 🟡 MEDIUM

**Affected Files:**
- `/web/xingcai_sqlin.php` (Line 71)
- `/admin/blast_sqlin.php` (Line 85)

**Details:**
`get_magic_quotes_gpc()` was deprecated in PHP 5.4 and removed in PHP 7.0.

**Recommendation:**
Remove all references to magic quotes.

---

### 8. Disabled Error Reporting (CWE-209)

**Severity:** 🟡 MEDIUM

**Affected Files:**
- `/web/xingcai_config.php` (Line 28, 40)
- `/admin/admin.config.php` (Line 24)

**Details:**

```php
ini_set('display_errors', 'Off');
error_reporting(0);
```

While hiding errors from users is correct, ensure proper logging is enabled.

**Recommendation:**
- Keep `display_errors = Off` in production
- Enable `log_errors = On`
- Configure `error_log` path
- Implement structured logging

---

### 9. Use of Deprecated `mysql_*` Constants

**Severity:** 🟡 MEDIUM

The code doesn't use proper connection constants or error handling for database operations.

**Recommendation:**
Migrate to MySQLi or PDO with proper exception handling.

---

## 🟢 LOW Severity Issues

### 10. Commented-Out Security Code

**Severity:** 🟢 LOW

**Details:**
Many security functions are commented out in filtering code, suggesting incomplete implementation or testing.

**Recommendation:**
Remove commented code or properly implement and test security functions.

---

### 11. Inconsistent Character Encoding

**Severity:** 🟢 LOW

**Details:**
Mixed use of UTF-8 and UTF8 (without hyphen), potential encoding issues.

**Recommendation:**
Standardize on UTF-8 throughout the application.

---

## Security Best Practices Violations

1. ❌ **No Authentication Layer:** Direct file access without authentication checks
2. ❌ **No CSRF Protection:** No tokens for state-changing operations
3. ❌ **No Rate Limiting:** No protection against brute force attacks
4. ❌ **No Security Headers:** Missing CSP, X-Frame-Options, etc.
5. ❌ **No Input Validation Framework:** Ad-hoc filtering instead of systematic approach
6. ❌ **No Logging/Monitoring:** No audit trail for security events
7. ❌ **No Encryption:** Database passwords stored in plain text
8. ❌ **No Secure Session Management:** Potential session hijacking risks

---

## Compliance Issues

This application may violate:
- **OWASP Top 10:** A01 (Broken Access Control), A02 (Cryptographic Failures), A03 (Injection)
- **PCI DSS:** If handling payment card data (requirements 6.5.1, 6.5.3, 8.2.1)
- **GDPR:** Data protection requirements for user data
- **ISO 27001:** Information security management standards

---

## Immediate Action Items

### Priority 1 (Do NOW):
1. ✅ **Rotate all database passwords immediately**
2. ✅ **Change admin password from '123456'**
3. ✅ **Remove hardcoded credentials from code**
4. ⚠️ **Disable application until critical fixes are deployed**

### Priority 2 (Within 24 hours):
1. Implement environment variable configuration
2. Create secure configuration template
3. Add input validation and prepared statements
4. Deploy on PHP 7.4+ with MySQLi/PDO

### Priority 3 (Within 1 week):
1. Complete security code review
2. Implement comprehensive input validation
3. Add authentication and authorization layers
4. Enable security logging and monitoring
5. Implement CSRF protection
6. Add security headers

---

## Recommended Security Controls

### Authentication & Authorization
- Implement session-based authentication
- Add role-based access control (RBAC)
- Enforce multi-factor authentication for admin
- Implement secure password reset workflow

### Input Validation
- Use prepared statements exclusively
- Implement whitelist-based validation
- Apply context-specific output encoding
- Use validation libraries (e.g., Respect\Validation)

### Data Protection
- Encrypt sensitive data at rest
- Use HTTPS exclusively (HSTS)
- Implement secure session management
- Hash passwords with bcrypt/Argon2

### Monitoring & Logging
- Log all authentication attempts
- Monitor for SQL injection attempts
- Implement intrusion detection
- Set up security alerting

### Infrastructure
- Use Web Application Firewall (WAF)
- Implement rate limiting
- Regular security updates
- Principle of least privilege for database access

---

## Testing Recommendations

1. **Penetration Testing:** Conduct professional pentesting
2. **Static Analysis:** Use tools like PHPStan, Psalm, SonarQube
3. **Dynamic Analysis:** OWASP ZAP, Burp Suite scanning
4. **Dependency Scanning:** Check for vulnerable libraries
5. **Code Review:** Manual security-focused code review

---

## Conclusion

The Taohua application has **severe security vulnerabilities** that require immediate attention. The combination of hardcoded credentials, SQL injection vulnerabilities, and use of deprecated functions creates a high-risk environment.

**Risk Assessment:** 🔴 **CRITICAL RISK**

Immediate remediation of identified vulnerabilities is essential to prevent:
- Data breaches
- Financial loss
- Reputational damage
- Legal liability
- Regulatory penalties

---

## References

- [OWASP Top 10 2021](https://owasp.org/Top10/)
- [CWE/SANS Top 25](https://cwe.mitre.org/top25/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [SQL Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html)

---

**Audit Conducted By:** GitHub Copilot Security Agent  
**Report Version:** 1.0  
**Last Updated:** 2026-02-13
