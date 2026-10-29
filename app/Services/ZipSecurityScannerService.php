<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class ZipSecurityScannerService
{
    // 最大文件大小 (50MB)
    const MAX_FILE_SIZE = 52428800;

    // 超时时间 (秒)
    const TIMEOUT_SECONDS = 5;

    // 可疑文件扩展名（高致命性）
    const DANGEROUS_EXTENSIONS = [
        '.exe', '.sh', '.bat', '.dll', '.so', '.com', '.cmd', '.scr', '.pif',
        '.jar', '.msi', '.msp', '.bin', '.iso', '.img'
    ];

    // 隐藏文件模式（警告级别）
    const HIDDEN_FILE_PATTERNS = [
        '.git', '.svn', '.hg', '.bzr',
        '.env', '.env.local', '.env.production',
        '.htaccess', '.htpasswd',
        'wp-config.php',
        '*.log',
        '.DS_Store',
        'Thumbs.db'
    ];

    // 高致命性危险函数
    const CRITICAL_FUNCTIONS = [
        'eval',
        'assert',
        'system',
        'exec',
        'passthru',
        'shell_exec',
        'popen',
        'proc_open',
        'escapeshellarg',
        'escapeshellcmd'
    ];

    // 中等级危险函数/模式
    const MEDIUM_RISK_FUNCTIONS = [
        'base64_decode',
        'gzinflate',
        'str_rot13',
        'unserialize',
        'create_function',
        'stream_wrapper_register'
    ];

    // 低级别警告函数
    const LOW_RISK_FUNCTIONS = [
        'include',
        'require',
        'include_once',
        'require_once'
    ];

    /**
     * 扫描上传的 ZIP 文件
     *
     * @param UploadedFile $file
     * @return array ['safe' => bool, 'issues' => [...], 'warnings' => [...]]
     */
    public function scan(UploadedFile $file): array
    {
        set_time_limit(self::TIMEOUT_SECONDS);

        $result = [
            'safe' => true,
            'issues' => [],
            'warnings' => []
        ];

        // 1. 文件大小检查
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $result['issues'][] = '文件大小超过限制（最大 50MB）';
            return $result;
        }

        // 2. 文件类型检查
        if (!$this->validateFileType($file)) {
            $result['issues'][] = '不是有效的 ZIP 文件';
            return $result;
        }

        // 3. 解压并扫描
        $extractDir = sys_get_temp_dir() . '/zip_scan_' . uniqid();
        if (!mkdir($extractDir, 0755, true)) {
            $result['issues'][] = '无法创建临时目录进行扫描';
            return $result;
        }

        $zip = new ZipArchive();
        $zipResult = $zip->open($file->getRealPath());

        if ($zipResult !== true) {
            $result['issues'][] = 'ZIP 文件损坏或无法打开';
            @rmdir($extractDir);
            return $result;
        }

        // 4. 结构检查
        $structureChecks = $this->checkStructure($zip);
        $result = array_merge($result, $structureChecks);

        if (!$result['safe']) {
            $this->cleanup($zip, $extractDir);
            return $result;
        }

        // 5. 遍历所有文件进行深度扫描
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];

            // 跳过目录
            if (substr($fileName, -1) === '/') {
                continue;
            }

            // 文件类型和扩展名检查
            $typeCheck = $this->checkFileTypes($fileName, $zip, $i);
            $result = array_merge($result, $typeCheck);

            // PHP 文件内容检查
            if (pathinfo($fileName, PATHINFO_EXTENSION) === 'php') {
                $content = $zip->getFromIndex($i);
                
                if ($content === false) {
                    continue;
                }

                // BOM 和编码检查
                $encodingCheck = $this->checkEncoding($content);
                $result = array_merge($result, $encodingCheck);

                // 危险函数检测
                $dangerousFuncs = $this->detectDangerousFunctions($content, $fileName);
                $result = array_merge($result, $dangerousFuncs);

                // 可疑模式检测
                $suspiciousPatterns = $this->detectSuspiciousPatterns($content, $fileName);
                $result = array_merge($result, $suspiciousPatterns);

                // 单行长度检查
                $lineLengthCheck = $this->checkLineLength($content, $fileName);
                $result = array_merge($result, $lineLengthCheck);

                if (!$result['safe']) {
                    $this->cleanup($zip, $extractDir);
                    return $result;
                }
            }
        }

        // 6. WP 插件主文件检查
        $wpCheck = $this->checkWpPluginMainFile($zip);
        $result = array_merge($result, $wpCheck);

        $this->cleanup($zip, $extractDir);

        // 如果没有发现任何问题
        if (empty($result['issues'])) {
            $result['safe'] = true;
        } else {
            $result['safe'] = false;
        }

        return $result;
    }

    /**
     * 验证文件类型
     */
    private function validateFileType(UploadedFile $file): bool
    {
        // 检查 MIME 类型
        $allowedMimes = ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'];
        
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            // 尝试检查 magic number
            $handle = fopen($file->getRealPath(), 'rb');
            $signature = fread($handle, 2);
            fclose($handle);

            if ($signature !== 'PK') {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查文件扩展名和类型
     */
    private function checkFileTypes(string $fileName, ZipArchive $zip, int $index): array
    {
        $result = [
            'issues' => [],
            'warnings' => []
        ];

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // 检查危险扩展名
        if (in_array($extension, array_map('strtolower', self::DANGEROUS_EXTENSIONS))) {
            $result['issues'][] = "发现可执行文件：{$fileName} - 可能存在安全风险";
            return $result;
        }

        // 检查隐藏文件
        foreach (self::HIDDEN_FILE_PATTERNS as $pattern) {
            if ($this->matchPattern($fileName, $pattern)) {
                $result['warnings'][] = "ZIP 包含隐蔽文件 {$fileName}，请确认是否应该提交";
                break;
            }
        }

        return $result;
    }

    /**
     * 匹配通配符模式
     */
    private function matchPattern(string $fileName, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return strpos($fileName, $pattern) !== false;
        }

        // 将 glob 模式转换为正则表达式
        $regex = str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/'));
        return (bool)preg_match('/^' . $regex . '$/i', $fileName);
    }

    /**
     * 检查文件编码和 BOM
     */
    private function checkEncoding(string $content): array
    {
        $result = [
            'issues' => [],
            'warnings' => []
        ];

        // 检查 UTF-8 BOM
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $result['issues'][] = '检测到 UTF-8 BOM 头 - 可能导致输出乱码';
        }

        // 检查 GBK/GB2312 BOM
        if (strncmp($content, "\xFF\xFE", 2) === 0 || strncmp($content, "\xFE\xFF", 2) === 0) {
            $result['issues'][] = '检测到 GBK/Unicode BOM 头 - 可能兼容性问题';
        }

        // 检查非 UTF-8 字符（排除合法的多字节序列）
        if ($this->containsInvalidUtf8Chars($content)) {
            $result['warnings'][] = '检测到非标准 UTF-8 字符 - 可能有编码问题';
        }

        return $result;
    }

    /**
     * 检查是否存在无效的 UTF-8 字符
     */
    private function containsInvalidUtf8Chars(string $content): bool
    {
        // 使用 preg_match 检查非法字节序列
        // 这个正则匹配不是完整合法的 UTF-8，而是检测明显非法的字节
        return preg_match('/[\x80-\xFF]{2}/', $content) && !preg_match('//u', $content);
    }

    /**
     * 检测危险函数
     */
    private function detectDangerousFunctions(string $content, string $fileName): array
    {
        $result = [
            'issues' => [],
            'warnings' => []
        ];

        // 高致命性函数检测
        foreach (self::CRITICAL_FUNCTIONS as $function) {
            // 使用正则匹配函数调用（考虑空格和参数）
            $pattern = '/\b' . preg_quote($function, '/') . '\s*\(/i';
            
            if (preg_match($pattern, $content)) {
                $issue = $this->getFunctionIssueMessage($function, $fileName);
                $result['issues'][] = $issue;
            }
        }

        // $$ 变量变量检测
        if (preg_match('/\$\$/i', $content)) {
            $result['issues'][] = "发现 variable variables (\$\$) - {$fileName} 可能导致变量污染";
        }

        // goto 语句检测
        if (preg_match('/\bgoto\s+[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*/i', $content)) {
            $result['issues'][] = "发现 goto 语句 - {$fileName} 可能用于代码混淆";
        }

        // 反引号命令注入
        if (preg_match('/`[^`]+`/', $content)) {
            $result['issues'][] = "发现反引号命令执行 - {$fileName} 可能存在命令注入风险";
        }

        // 中等级组合模式检测
        $mediumIssues = $this->detectMediumRiskPatterns($content, $fileName);
        $result['issues'] = array_merge($result['issues'], $mediumIssues);

        // 低级警告检测
        $lowWarnings = $this->detectLowRiskPatterns($content, $fileName);
        $result['warnings'] = array_merge($result['warnings'], $lowWarnings);

        return $result;
    }

    /**
     * 获取危险函数的错误消息
     */
    private function getFunctionIssueMessage(string $function, string $fileName): string
    {
        $messages = [
            'eval' => "发现危险函数：eval() - {$fileName} 可能导致任意代码执行",
            'assert' => "发现危险函数：assert() - {$fileName} 可能被利用执行恶意代码",
            'system' => "发现危险函数：system() - {$fileName} 可能执行系统命令",
            'exec' => "发现危险函数：exec() - {$fileName} 可能执行系统命令",
            'passthru' => "发现危险函数：passthru() - {$fileName} 可能执行系统命令",
            'shell_exec' => "发现危险函数：shell_exec() - {$fileName} 可能执行系统命令",
            'popen' => "发现危险函数：popen() - {$fileName} 可能打开进程管道",
            'proc_open' => "发现危险函数：proc_open() - {$fileName} 可能打开进程管道",
            'escapeshellarg' => "发现函数：escapeshellarg() - {$fileName} 通常与安全绕过相关",
            'escapeshellcmd' => "发现函数：escapeshellcmd() - {$fileName} 通常与安全绕过相关"
        ];

        return $messages[$function] ?? "发现危险函数：{$function}() - {$fileName} 可能存在安全风险";
    }

    /**
     * 检测中等风险模式
     */
    private function detectMediumRiskPatterns(string $content, string $fileName): array
    {
        $issues = [];

        // base64_decode() + eval() 组合
        if (preg_match('/base64_decode\s*\(/i', $content) && 
            preg_match('/eval\s*\(/i', $content)) {
            $issues[] = "发现混淆后门特征：base64_decode() + eval() 在同一个文件中";
        }

        // gzinflate() + str_rot13() + base64_decode() 组合
        if (preg_match('/gzinflate\s*\(/i', $content) &&
            preg_match('/str_rot13\s*\(/i', $content) &&
            preg_match('/base64_decode\s*\(/i', $content)) {
            $issues[] = "发现加密后门特征：gzinflate() + str_rot13() + base64_decode() 组合";
        }

        // file_get_contents() + eval()
        if (preg_match('/file_get_contents\s*\(/i', $content) &&
            preg_match('/eval\s*\(/i', $content)) {
            $issues[] = "发现远程代码注入风险：file_get_contents() + eval() 组合";
        }

        // $_POST/$_GET/$_REQUEST + eval()/$$
        $superGlobals = ['$_POST', '$_GET', '$_REQUEST', '$_COOKIE'];
        $hasSuperGlobal = false;
        
        foreach ($superGlobals as $global) {
            if (strpos($content, $global) !== false) {
                $hasSuperGlobal = true;
                break;
            }
        }

        if ($hasSuperGlobal && (preg_match('/eval\s*\(/i', $content) || preg_match('/\$\$/i', $content))) {
            $issues[] = "发现 Web Shell 特征：超级全局变量 + 危险函数组合";
        }

        // preg_replace with /e modifier
        if (preg_match('/preg_replace\s*\(\s*["\']\/.*\/[eimsonupx]+\s*\)/i', $content)) {
            $issues[] = "发现危险用法：preg_replace() 的 /e 修饰符已被废弃但仍可能被利用";
        }

        return $issues;
    }

    /**
     * 检测低风险警告模式
     */
    private function detectLowRiskPatterns(string $content, string $fileName): array
    {
        $warnings = [];

        // include/require + $_GET/$_POST 远程文件包含
        $hasInclude = false;
        foreach (self::LOW_RISK_FUNCTIONS as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $content)) {
                $hasInclude = true;
                break;
            }
        }

        $hasSuperGlobal = false;
        foreach (['$_GET', '$_POST', '$_REQUEST'] as $global) {
            if (strpos($content, $global) !== false) {
                $hasSuperGlobal = true;
                break;
            }
        }

        if ($hasInclude && $hasSuperGlobal) {
            $warnings[] = "潜在的文件包含风险：{$fileName} 使用了 include/require 配合用户输入";
        }

        return $warnings;
    }

    /**
     * 检测可疑代码模式（包括 JavaScript）
     */
    private function detectSuspiciousPatterns(string $content, string $fileName): array
    {
        $result = [
            'issues' => [],
            'warnings' => []
        ];

        // document.write() + Base64 字符集
        if (preg_match('/document\.write\s*\(/i', $content) &&
            preg_match('/[A-Za-z0-9+\/]{50,}={0,2}/', $content)) {
            $result['issues'][] = "发现潜在 XSS 特征：document.write() + 编码字符串";
        }

        // 隐藏 iframe
        if (preg_match('/iframe\s+[^>]*src\s*=\s*["\']?\s*https?:\/\/[^"\'>\s]+/i', $content) &&
            preg_match('/display\s*:\s*none/i', $content)) {
            $result['issues'][] = "发现隐藏 iframe - 可能被用于恶意重定向";
        }

        // JS eval + String.fromCharCode
        if (preg_match('/eval\s*\(\s*String\.fromCharCode\s*\(/i', $content)) {
            $result['issues'][] = "发现 JS 混淆后门：eval(String.fromCharCode(...))";
        }

        return $result;
    }

    /**
     * 检查单行长度
     */
    private function checkLineLength(string $content, string $fileName): array
    {
        $result = [
            'issues' => [],
            'warnings' => []
        ];

        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (mb_strlen($line) > 5000) {
                $result['warnings'][] = "文件 {$fileName} 第 {$lineNum} 行超长（" . mb_strlen($line) . "字符）- 可能是混淆代码";
                break; // 只报告第一次
            }
        }

        return $result;
    }

    /**
     * 检查文件结构
     */
    private function checkStructure(ZipArchive $zip): array
    {
        $result = [
            'issues' => [],
            'warnings' => []
        ];

        // 文件数量检查
        if ($zip->numFiles > 500) {
            $result['issues'][] = "ZIP 内文件数量过多（{$zip->numFiles}个）- 可能存在异常";
        }

        // 目录层级检查
        $maxDepth = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];
            
            if (substr($fileName, -1) === '/') {
                continue;
            }
            
            $depth = substr_count($fileName, '/') + 1;
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        if ($maxDepth > 10) {
            $result['issues'][] = "目录层级过深（{$maxDepth}层）- 可能存在异常结构";
        }

        // readme.txt 或 README.md 检查
        $hasReadme = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];
            $lowerName = strtolower($fileName);
            if (basename($lowerName) === 'readme.txt' || basename($lowerName) === 'readme.md') {
                $hasReadme = true;
                break;
            }
        }

        if (!$hasReadme) {
            $result['warnings'][] = '缺少文档文件（readme.txt 或 README.md）';
        }

        // LICENSE 文件检查
        $hasLicense = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];
            $lowerName = strtolower($fileName);
            if (basename($lowerName) === 'license.txt' || basename($lowerName) === 'license.md' || basename($lowerName) === 'license') {
                $hasLicense = true;
                break;
            }
        }

        if (!$hasLicense) {
            $result['warnings'][] = '缺少许可证文件（LICENSE 或 COPYING）';
        }

        return $result;
    }

    /**
     * 检查 WP 插件主文件头部标记
     */
    private function checkWpPluginMainFile(ZipArchive $zip): array
    {
        $result = [
            'issues' => [],
            'warnings' => []
        ];

        // 查找可能的插件主文件
        $mainFileFound = false;
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            // 跳过目录和特定文件
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];
            if (substr($fileName, -1) === '/' || pathinfo($fileName, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            // 检查是否在根目录或一级子目录
            $parts = explode('/', $fileName);
            if (count($parts) > 2) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            // 检查 WP 插件头部标记
            if (preg_match('/\*\s*Plugin Name:\s*(.+?)(?:\r\n|\r|\n)/', $content, $matches) ||
                preg_match('/Plugin Name:(.+?)(?:\r\n|\r|\n)/', $content, $matches)) {
                $mainFileFound = true;
                break;
            }

            if (preg_match('/\*\s*Theme Name:\s*(.+?)(?:\r\n|\r|\n)/', $content, $matches) ||
                preg_match('/Theme Name:(.+?)(?:\r\n|\r|\n)/', $content, $matches)) {
                $mainFileFound = true;
                break;
            }
        }

        if (!$mainFileFound) {
            $result['issues'][] = 'WP 插件缺少必要的头部标记（Plugin Name: 或 Theme Name:）';
        }

        return $result;
    }

    /**
     * 清理临时资源
     */
    private function cleanup(ZipArchive $zip, string $extractDir): void
    {
        try {
            $zip->close();
            
            // 简单递归删除目录
            if (is_dir($extractDir)) {
                $this->deleteDirectory($extractDir);
            }
        } catch (\Exception $e) {
            // 忽略清理过程中的异常
        }
    }

    /**
     * 递归删除目录
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
