<?php
/**
 * LicenseOS PHP SDK v1.0
 * 
 * 适用于：Laravel Package、PHP Source Code、PHP Script
 * 
 * 使用方法：
 * 1. 将此文件复制到你的项目目录
 * 2. require_once 'licenseos-sdk.php';
 * 3. $licenseos = new LicenseOS('https://your-api.com', 'your-api-key');
 * 4. $licenseos->activate('LICENSE-KEY', $_SERVER['HTTP_HOST']);
 * 5. if ($licenseos->isValid()) { // 允许使用 }
 */

class LicenseOS
{
    private string $apiUrl;
    private string $apiKey;
    private string $licenseKey;
    private string $domain;
    private int $cacheTtl; // seconds
    private ?array $lastCheck = null;

    public function __construct(
        string $apiUrl,
        string $apiKey,
        string $storagePath = '',  // 存储许可证数据的目录，默认为系统临时目录
        int $cacheTtl = 43200      // 12 小时
    ) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->cacheTtl = $cacheTtl;
        $this->domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        // 存储路径
        $this->storagePath = $storagePath ?: sys_get_temp_dir();
        $this->licenseFile = $this->storagePath . '/.licenseos_data.json';
        
        // 加载已保存的许可证数据
        $this->loadData();
    }

    // === 核心方法 ===
    
    /**
     * 激活许可证
     */
    public function activate(string $licenseKey): array
    {
        $this->licenseKey = $licenseKey;
        $result = $this->apiPost('/api/v2/license/activate', [
            'license_key' => $licenseKey,
            'domain'      => $this->domain,
        ]);
        
        if (($result['status'] ?? '') === 'valid' || ($result['status'] ?? '') === 'active') {
            $this->saveData();
        }
        
        return $result;
    }

    /**
     * 检查许可证是否有效
     */
    public function isValid(): bool
    {
        if (empty($this->licenseKey)) return false;
        
        // 检查缓存
        $cached = $this->getCachedCheck();
        if ($cached !== null) {
            return $cached;
        }
        
        // 远程检查
        $result = $this->apiPost('/api/v2/license/check', [
            'license_key' => $this->licenseKey,
        ]);
        
        $valid = ($result['status'] ?? '') === 'valid';
        $this->lastCheck = $result;
        $this->setCachedCheck($valid);
        
        // 完整性上报
        $this->reportIntegrity();
        
        return $valid;
    }

    /**
     * 停用许可证
     */
    public function deactivate(): array
    {
        if (empty($this->licenseKey)) return ['status' => 'error', 'message' => 'No license key'];
        
        $result = $this->apiPost('/api/v2/license/deactivate', [
            'license_key' => $this->licenseKey,
            'domain'      => $this->domain,
        ]);
        
        $this->licenseKey = '';
        $this->lastCheck = null;
        $this->clearCache();
        $this->saveData();
        
        return $result;
    }

    /**
     * 获取许可证状态详情
     */
    public function getStatus(): array
    {
        if (empty($this->licenseKey)) return ['status' => 'inactive'];
        return $this->lastCheck ?? ['status' => 'unknown'];
    }

    /**
     * 检查更新
     */
    public function checkForUpdate(string $productSlug, string $currentVersion): ?array
    {
        $result = $this->apiPost('/api/v2/update/check', [
            'product_slug'   => $productSlug,
            'current_version' => $currentVersion,
            'license_key'    => $this->licenseKey,
        ]);
        
        if (isset($result['download_url']) && version_compare($currentVersion, $result['current_version'] ?? '0', '<')) {
            return $result;
        }
        
        return null;
    }

    // === 完整性校验 ===

    /**
     * 报告代码完整性
     * 开发者可在关键代码位置调用此方法
     */
    public function reportIntegrity(): void
    {
        // 计算本 SDK 文件的哈希
        $sdkFile = __FILE__;
        if (!file_exists($sdkFile)) return;
        
        $codeHash = hash_file('sha256', $sdkFile);
        $apiKeyHash = hash('sha256', $this->apiKey);
        
        // 非阻塞上报（使用 cURL 异步）
        $this->apiPostAsync('/api/v2/integrity/report', [
            'domain'       => $this->domain,
            'code_hash'    => $codeHash,
            'api_key_hash' => $apiKeyHash,
            'license_key'  => $this->licenseKey,
            'api_key'      => $this->apiKey,
        ]);
    }

    // === 内部方法 ===

    private function apiPost(string $endpoint, array $data): array
    {
        $ch = curl_init($this->apiUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-KEY: ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response === false) return ['status' => 'error', 'message' => 'Network error'];
        
        return json_decode($response, true) ?: ['status' => 'error'];
    }

    private function apiPostAsync(string $endpoint, array $data): void
    {
        // 非阻塞：fork 一个子进程发送请求
        $url = $this->apiUrl . $endpoint;
        $payload = json_encode($data);
        $apiKey = $this->apiKey;
        
        // 使用 fsockopen 实现非阻塞
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $port = ($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80;
        $path = $parsed['path'] ?? '/';
        
        $fp = @fsockopen(($port === 443 ? 'ssl://' : '') . $host, $port, $errno, $errstr, 2);
        if (!$fp) return;
        
        $request = "POST $path HTTP/1.1\r\n"
            . "Host: $host\r\n"
            . "Content-Type: application/json\r\n"
            . "X-API-KEY: $apiKey\r\n"
            . "Content-Length: " . strlen($payload) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $payload;
        
        fwrite($fp, $request);
        fclose($fp);
    }

    // 数据存储
    private string $storagePath;
    private string $licenseFile;

    private function loadData(): void
    {
        if (file_exists($this->licenseFile)) {
            $data = json_decode(file_get_contents($this->licenseFile), true);
            $this->licenseKey = $data['license_key'] ?? '';
        }
    }

    private function saveData(): void
    {
        $dir = dirname($this->licenseFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents($this->licenseFile, json_encode([
            'license_key' => $this->licenseKey,
            'domain'      => $this->domain,
        ]));
    }

    // 缓存
    private function getCachedCheck(): ?bool
    {
        $cacheFile = $this->storagePath . '/.licenseos_cache';
        if (!file_exists($cacheFile)) return null;
        if (time() - filemtime($cacheFile) > $this->cacheTtl) return null;
        return file_get_contents($cacheFile) === '1';
    }

    private function setCachedCheck(bool $valid): void
    {
        $dir = dirname($this->storagePath . '/.licenseos_cache');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents($this->storagePath . '/.licenseos_cache', $valid ? '1' : '0');
    }

    private function clearCache(): void
    {
        @unlink($this->storagePath . '/.licenseos_cache');
    }
}
