# 安全审计报告 (Security Audit Report)

## 审计日期 (Audit Date)
2026-02-13

## 项目概述 (Project Overview)
这是一个基于PHP的Web应用程序，使用MySQL数据库。该项目包含管理后台(admin)、Web端(web)和移动端(wap)三个主要部分。

## 发现的严重安全漏洞 (Critical Security Vulnerabilities Found)

### 1. 硬编码数据库凭据 (Hardcoded Database Credentials) - 严重 (CRITICAL)

**位置 (Location):**
- `/admin/admin.config.php` (Line 8)
- `/web/xingcai_config.php` (Lines 10, 41)
- `/wap/xingcai_config.php` (Line 10)
- `/wap/hkoudai/conn.php` (Line 5)
- `/web/ldzf/conn.php` (Line 5)

**问题描述 (Description):**
数据库密码明文硬编码在配置文件中：
```php
$conf['db']['password']='dYAd4KeDY6ctczmN';
```

**风险 (Risk):**
- 数据库凭据暴露在代码库中
- 如果代码库泄露，攻击者可以直接访问数据库
- 违反安全最佳实践

**建议修复 (Recommended Fix):**
- 使用环境变量存储敏感信息
- 将配置文件添加到 `.gitignore`
- 使用配置管理工具（如dotenv）

---

### 2. 使用废弃的MySQL函数 (Deprecated MySQL Functions) - 严重 (CRITICAL)

**位置 (Location):**
- `/web/xingcai_config.php` (Lines 41-43)
- `/admin/blast_back/admin/Database.class.php` (Lines 84-264)
- `/admin/blast_sqlin.php` (Line 94)
- `/web/xingcai_sqlin.php` (Line 80)

**问题描述 (Description):**
代码使用了PHP 5.5.0已废弃、PHP 7.0.0已移除的mysql_*系列函数：
```php
$config = mysql_connect("127.0.0.1","root","dYAd4KeDY6ctczmN");
mysql_select_db("0xc");
mysql_escape_string($str);
```

**风险 (Risk):**
- 在现代PHP版本中无法运行
- `mysql_escape_string()` 不如 `mysql_real_escape_string()` 安全
- 缺少现代的安全特性
- 容易受到SQL注入攻击

**建议修复 (Recommended Fix):**
- 迁移到PDO或MySQLi
- 使用预处理语句(prepared statements)
- 移除所有mysql_*函数调用

---

### 3. SQL注入保护不充分 (Insufficient SQL Injection Protection) - 高危 (HIGH)

**位置 (Location):**
- `/admin/blast_sqlin.php` (Lines 29-31, 72-94)
- `/web/xingcai_sqlin.php` (Lines 58-80, 186-191)
- `/wap/xingcai_sqlin.php` (Lines 58-80, 186-191)

**问题描述 (Description):**

1. **不安全的字符串过滤函数:**
```php
function get_str($string){
    return $string;  // 没有任何过滤！
}
```

2. **使用正则表达式替换而不是参数化查询:**
```php
$str=preg_replace("/select/i", "",$str);
$str=preg_replace("/union/i", "",$str);
```
这种方式可以被绕过，例如：`SeLeCt`, `UNunionION`

3. **不安全的过滤实现:**
```php
$str=mysql_escape_string($str);  // 已废弃且不安全
```

**风险 (Risk):**
- 可能存在SQL注入漏洞
- 攻击者可以通过绕过过滤器执行恶意SQL
- 可能导致数据泄露、篡改或删除

**建议修复 (Recommended Fix):**
- 使用PDO的预处理语句
- 移除基于正则表达式的SQL关键字过滤
- 实施参数化查询

---

### 4. 目录遍历漏洞 (Directory Traversal Vulnerability) - 高危 (HIGH)

**位置 (Location):**
- `/admin/blast_back/admin/Database.class.php` (Lines 16-17, 38-46)

**问题描述 (Description):**
文件操作使用用户输入但过滤不足：
```php
$para['File']=wjStrFilter($para['File']);
// ...
if ($para['Action'] == 'RL') {
    $mr->recover($para['File']);  // 直接使用用户输入
} elseif ($para['Action'] == 'Del') {
    if (@unlink($DataDir . $para['File'])) {  // 可能删除任意文件
```

filter_dir函数的检查不足：
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
可以使用 `..%2F` 或其他编码绕过。

**风险 (Risk):**
- 攻击者可以读取或删除服务器上的任意文件
- 可能导致系统完全受损
- 信息泄露

**建议修复 (Recommended Fix):**
- 使用白名单验证文件名
- 使用 `basename()` 移除路径组件
- 验证文件在允许的目录内
- 禁止使用用户输入直接操作文件系统

---

### 5. 弱密码哈希 (Weak Password Hashing) - 中危 (MEDIUM)

**位置 (Location):**
- `/wap/xingcai_back/Team.class.php` (Line 282)
- `/wap/xingcai_back/User.class.php` (Line 110)
- `/admin/blast_back/admin/Member.class.php` (Line 95)
- 多个其他位置

**问题描述 (Description):**
使用MD5哈希密码：
```php
$update['password']=md5($update['password']);
if(md5($password)!=$user['password']){
```

**风险 (Risk):**
- MD5已被证明不安全
- 彩虹表攻击可以快速破解MD5哈希
- 没有使用盐值(salt)
- 不符合现代密码存储标准

**建议修复 (Recommended Fix):**
- 使用 `password_hash()` 和 `password_verify()`
- 使用bcrypt或Argon2算法
- 自动添加盐值

---

### 6. XSS (跨站脚本) 漏洞风险 (XSS Vulnerability Risk) - 中危 (MEDIUM)

**位置 (Location):**
- 多个模板文件中直接输出未转义的用户输入
- `/web/xingcai_sqlin.php` RemoveXSS函数实现复杂但可能被绕过

**问题描述 (Description):**
虽然有XSS过滤函数，但实现复杂且可能有漏洞：
```php
function RemoveXSS($val) {
    // 复杂的正则表达式可能被绕过
}
```

某些模板文件直接输出变量：
```php
<?=preg_replace('/^(\w{4}).*(\w{4})$/','\1***\2',htmlspecialchars($myBank['account']))?>
```

**风险 (Risk):**
- 可能执行恶意JavaScript
- 会话劫持
- 钓鱼攻击

**建议修复 (Recommended Fix):**
- 在所有输出点使用 `htmlspecialchars()`
- 实施内容安全策略(CSP)
- 使用成熟的模板引擎

---

### 7. 错误处理信息泄露 (Information Disclosure via Error Handling) - 中危 (MEDIUM)

**位置 (Location):**
- `/admin/admin.config.php` (Line 22)
- `/web/xingcai_config.php` (Line 28, 40)

**问题描述 (Description):**
虽然设置了 `display_errors=Off`，但debug级别设置为5：
```php
$conf['debug']['level']=5;
error_reporting(E_ERROR & ~E_NOTICE);
ini_set('display_errors', 'Off');
error_reporting(0);  // 在另一个地方又设置为0
```

配置不一致，可能泄露敏感信息。

**风险 (Risk):**
- 泄露系统路径
- 泄露数据库结构
- 帮助攻击者了解系统

**建议修复 (Recommended Fix):**
- 生产环境完全禁用错误显示
- 将错误记录到日志文件
- 统一错误处理配置

---

### 8. 缺少CSRF保护 (Missing CSRF Protection) - 中危 (MEDIUM)

**位置 (Location):**
- 所有表单提交
- `/admin/index.php` 及其他入口文件

**问题描述 (Description):**
代码中没有发现CSRF令牌的生成和验证机制。

**风险 (Risk):**
- 攻击者可以伪造请求
- 可能执行未授权操作
- 账户劫持

**建议修复 (Recommended Fix):**
- 实施CSRF令牌机制
- 在所有表单中包含令牌
- 在服务器端验证令牌

---

### 9. 会话管理不安全 (Insecure Session Management) - 中危 (MEDIUM)

**位置 (Location):**
- 各个配置文件

**问题描述 (Description):**
没有发现以下安全配置：
- `session.cookie_httponly`
- `session.cookie_secure`
- `session.use_strict_mode`

**风险 (Risk):**
- 会话劫持
- XSS可以窃取会话cookie
- 中间人攻击

**建议修复 (Recommended Fix):**
- 设置 `session.cookie_httponly = 1`
- 设置 `session.cookie_secure = 1` (HTTPS)
- 设置 `session.use_strict_mode = 1`
- 实施会话固定保护

---

### 10. 文件上传漏洞风险 (File Upload Vulnerability Risk) - 待确认 (TBD)

**位置 (Location):**
- `/upload` 目录存在于多个位置

**问题描述 (Description):**
存在上传目录但需要进一步审查上传处理代码。

**风险 (Risk):**
- 可能上传恶意文件
- 远程代码执行
- webshell上传

**建议修复 (Recommended Fix):**
- 验证文件类型（不仅是扩展名）
- 限制文件大小
- 使用随机文件名
- 将上传文件存储在Web根目录外
- 设置正确的文件权限

---

## 优先级修复建议 (Priority Fix Recommendations)

### 立即修复 (Immediate - Critical):
1. ✅ 移除硬编码的数据库凭据
2. ✅ 修复SQL注入漏洞 - 迁移到PDO/预处理语句
3. ✅ 修复目录遍历漏洞

### 短期修复 (Short-term - High):
4. 替换废弃的MySQL函数
5. 改进密码哈希算法
6. 实施CSRF保护

### 中期修复 (Medium-term):
7. 改进XSS防护
8. 加固会话管理
9. 改进错误处理

### 长期改进 (Long-term):
10. 全面的安全代码审查
11. 实施安全开发生命周期
12. 添加自动化安全测试

---

## 合规性问题 (Compliance Issues)

1. **OWASP Top 10 违规:**
   - A01:2021 – 失效的访问控制
   - A02:2021 – 加密机制失效
   - A03:2021 – 注入
   - A05:2021 – 安全配置错误
   - A07:2021 – 识别和身份验证失败

2. **PCI DSS (如果处理支付):**
   - 不符合密码存储要求
   - 缺少适当的访问控制

---

## 审计方法 (Audit Methodology)

本次审计采用了以下方法：
1. 静态代码分析
2. 手动代码审查
3. 安全配置检查
4. 已知漏洞模式匹配
5. 最佳实践对比

---

## 免责声明 (Disclaimer)

本报告基于当前可见的代码进行分析。实际运行环境中可能存在其他安全问题。建议：
1. 进行渗透测试
2. 进行动态应用安全测试(DAST)
3. 定期进行安全审计
4. 实施持续安全监控

---

## 联系信息 (Contact)

如需更多信息或澄清，请联系安全团队。

---

**报告生成时间:** 2026-02-13 15:26 UTC
**审计工具:** 手动代码审查 + 自动化扫描工具
**审计范围:** 完整代码库
