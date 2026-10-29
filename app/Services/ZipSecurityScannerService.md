# ZIP Security Scanner Service

ZIP 安全扫描服务，用于深度扫描上传的 ZIP 文件中的安全风险。

## 功能特性

### 1. 文件类型检查
- ✅ 验证 ZIP 文件格式（MIME 类型和 magic number）
- ✅ 检测危险可执行文件 (.exe, .sh, .bat, .dll, .so, .com 等)
- ✅ 检测隐藏文件 (.git, .env, .svn 等)

### 2. PHP 危险函数检测
**高致命性检测：**
- `eval()`, `assert()` - 动态代码执行
- `system()`, `exec()`, `passthru()`, `shell_exec()`, `popen()` - 系统命令执行
- 反引号 `` ` `` - 命令注入
- `$$` - 变量污染
- `goto` 语句 - 代码混淆

**中等风险模式检测：**
- `base64_decode()` + `eval()` - 混淆后门
- `gzinflate()` + `str_rot13()` + `base64_decode()` - 加密后门
- `file_get_contents()` + `eval()` - 远程代码注入
- `$_POST/$_GET/$_REQUEST` + 危险函数 - Web Shell 特征

**低风险警告：**
- `include()/require()` + 用户输入 - 远程文件包含风险

### 3. JavaScript 可疑模式
- `document.write()` + Base64 编码 - XSS 后门
- 隐藏的 iframe (`display:none`) - 恶意重定向
- `eval(String.fromCharCode(...))` - JS 混淆

### 4. BOM 和编码检查
- UTF-8 BOM 头 (\xEF\xBB\xBF)
- GBK/GB2312 BOM 头 (\xFF\xFE, \xFE\xFF)
- 非标准 UTF-8 字符检测

### 5. WP 插件完整性检查
- 主文件是否包含 `Plugin Name:` 或 `Theme Name:` 头部
- readme.txt 或 README.md 是否存在
- LICENSE 文件是否存在

### 6. 结构安全检查
- 文件大小限制 (50MB)
- 文件数量限制 (500 个)
- 目录层级限制 (10 层)
- 超长单行检测 (5000+ 字符)

## 使用方式

### 基本使用

```php
use App\Services\ZipSecurityScannerService;
use Illuminate\Http\UploadedFile;

$scanner = new ZipSecurityScannerService();
$result = $scanner->scan($uploadedFile);

if ($result['safe']) {
    echo "✅ 文件安全";
} else {
    echo "❌ 发现问题: ";
    foreach ($result['issues'] as $issue) {
        echo "- {$issue}\n";
    }
    
    // 可选：查看警告
    foreach ($result['warnings'] as $warning) {
        echo "⚠️  {$warning}\n";
    }
}
```

### 返回格式

```php
[
    'safe' => bool,              // 整体是否安全
    'issues' => [string],        // 严重问题列表（会导致拒绝）
    'warnings' => [string]       // 警告信息（仅供参考）
]
```

## 配置参数

```php
class ZipSecurityScannerService
{
    const MAX_FILE_SIZE = 52428800;     // 50MB
    const TIMEOUT_SECONDS = 5;          // 超时时间
    
    // 危险扩展名列表已内建
    // 危险函数列表已内建
}
```

## 测试示例

### 干净的 ZIP 文件
```php
$file = new UploadedFile('move-bottom-menu.zip', 'plugin.zip');
$result = $scanner->scan($file);
// 返回：['safe' => true, 'issues' => [], 'warnings' => []]
```

### 包含恶意代码的 ZIP
```php
// 包含 eval($_POST['cmd'])
$result = $scanner->scan($maliciousFile);
// 返回：['safe' => false, 'issues' => ['发现危险函数：eval()...'], ...]
```

### 含 BOM 头的文件
```php
// 包含 UTF-8 BOM
$result = $scanner->scan($bomFile);
// 返回：['safe' => false, 'issues' => ['检测到 UTF-8 BOM 头...'], ...]
```

## 注意事项

1. **性能优化**: 设置 5 秒超时时间，避免长时间扫描
2. **资源清理**: 自动清理临时解压目录
3. **即时返回**: 发现致命问题时立即终止扫描并返回
4. **大文件拒绝**: 超过 50MB 的文件会被直接拒绝

## 与项目集成

可以在以下场景中使用：

1. **产品上传验证**: 在开发者上传插件/主题 ZIP 时进行安全扫描
2. **更新包检查**: 验证更新包的完整性
3. **批量导入**: 审核需要导入的第三方插件
4. **API 接口**: 提供安全扫描 API 给外部调用者

## 文件位置

- 源代码：`app/Services/ZipSecurityScannerService.php`
- 示例测试：`verify_scanner.php`
