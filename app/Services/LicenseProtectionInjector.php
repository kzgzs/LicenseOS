<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class LicenseProtectionInjector
{
    protected string $detectedPluginFile = '';

    /**
     * @var int|null 探测到的主文件索引 (ZIP 内)
     */
    protected ?int $mainPluginFileIndex = null;

    /**
     * @var string 探测到的父菜单 slug (e.g., 'options-general.php')
     */
    protected string $detectedParentSlug = '';

    /**
     * 向 WP 插件/主题 ZIP 中注入授权保护代码
     *
     * @param string $zipPath ZIP 文件的绝对路径（将被原地替换）
     * @param string $apiUrl LicenseOS API 地址
     * @param string $apiKey API 密钥
     * @param string $productSlug 产品 slug，用于存储完整性哈希
     * @param string $productType 产品类型：'wp_plugin' 或 'wp_theme'
     * @return bool 是否注入成功
     */
    public function inject(string $zipPath, string $apiUrl, string $apiKey, string $productSlug, string $productType = 'wp_plugin'): bool
    {
        // Theme injection path
        if ($productType === 'wp_theme') {
            return $this->injectTheme($zipPath, $apiUrl, $apiKey, $productSlug);
        }

        $zip = new ZipArchive();

        try {
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                return false;
            }

            $mainIndex = $this->findMainPluginFile($zip);
            $this->mainPluginFileIndex = $mainIndex; // 记录主文件索引

            if ($mainIndex === null) {
                Log::warning('[inject] No main plugin file found', [
                    'zip' => basename($zipPath),
                    'numFiles' => $zip->numFiles,
                ]);
                return false;
            }

            // 计算插件目录前缀（如 "move-bottom-menu/" 或 ""）
            $entryName = $zip->getNameIndex($mainIndex);
            $pluginDir = '';
            if ($entryName) {
                $dir = dirname($entryName);
                $pluginDir = ($dir !== '.' && $dir !== '') ? $dir . '/' : '';
            }

            // 注入 SDK 文件（在 Integrity Guard 之前，因为 Guard 会检查 SDK 引用）
            $this->injectSdkFile($zip, $pluginDir);

            $content = $zip->getFromIndex($mainIndex);

            if ($content === false) {
                return false;
            }

            // --- Auto-inject require_once for SDK file ---
            $content = $this->injectSdkRequireOnce($content);

            // Detect the plugin's admin menu slug + parent slug from ZIP PHP files
            $menuInfo = $this->detectAdminPageSlug($zip, $pluginDir);
            $adminPageSlug = $menuInfo['slug'] ?? null;
            if (!$adminPageSlug) {
                $adminPageSlug = $productSlug;
            }
            
            // Debug: Log detected admin page slug
            Log::info('[LicenseProtectionInjector] Admin page slug detection', [
                'detected_slug' => $adminPageSlug,
                'product_slug' => $productSlug,
            ]);

            // Detect plugin option prefix (e.g., mbm_, wpo_, etc.)
            try {
                $optionPrefix = $this->detectPluginOptionPrefix($zip);
            } catch (\Exception $e) {
                Log::warning('[inject] detectPluginOptionPrefix failed', ['error' => $e->getMessage()]);
                $optionPrefix = '';
            }
            
            if (!$optionPrefix || trim($optionPrefix) === '') {
                $optionPrefix = str_replace('-', '_', $productSlug);
            }
            
            // Debug: Log detected option prefix
            Log::info('[LicenseProtectionInjector] Option prefix detection', [
                'detected_prefix' => $optionPrefix,
                'product_slug_converted' => str_replace('-', '_', $productSlug),
            ]);

            $protectionCode = $this->generateProtectionCode($apiUrl, $apiKey, $productSlug, $adminPageSlug, $optionPrefix);

            // Append Integrity Guard to protection code
            $protectionCode = $this->appendIntegrityGuard($protectionCode, $apiUrl, $apiKey, $productSlug, $adminPageSlug, $optionPrefix);

            // Generate complete initialization code
            $initCode = $this->generateInitCode($apiUrl, $apiKey, $productSlug, $optionPrefix, $adminPageSlug ?: $productSlug, $this->detectedPluginFile, $this->detectedParentSlug ?: 'admin.php');
            
            // Append init code to protection code
            $fullCode = $initCode . "\n" . $protectionCode;

            // Find injection point
            $injected = false;

            // Priority 1: after defined('ABSPATH') check
            if (!$injected && preg_match(
                '/(if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)\s*.*?;\s*\n)/s',
                $content,
                $match,
                PREG_OFFSET_CAPTURE
            )) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $content = substr($content, 0, $pos) . "\n" . $fullCode . "\n" . substr($content, $pos);
                $injected = true;
            }

            // Priority 2: after Plugin Name: comment block closing */
            if (!$injected && preg_match(
                '/(\*\/\s*\n)/',
                $content,
                $match,
                PREG_OFFSET_CAPTURE
            )) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $content = substr($content, 0, $pos) . "\n" . $fullCode . "\n" . substr($content, $pos);
                $injected = true;
            }

            // Priority 3: after <?php
            if (!$injected && preg_match(
                '/(<\?php\s*\n)/',
                $content,
                $match,
                PREG_OFFSET_CAPTURE
            )) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $content = substr($content, 0, $pos) . "\n" . $fullCode . "\n" . substr($content, $pos);
                $injected = true;
            }

            if (!$injected) {
                return false;
            }

            $zip->addFromString($entryName, $content);

            // 在所有注入完成后，使用与运行时相同的正则提取代码块并计算哈希
            // 确保运行时 Integrity Guard 计算的哈希 === 服务器存储的预期哈希
            if ($productSlug && preg_match(
                '/\/\/ === LicenseOS License Protection ===.*\/\/ === End LicenseOS Integrity Guard ===/s',
                $content,
                $runtimeMatch
            )) {
                $finalHash = hash('sha256', $runtimeMatch[0]);
                $apiKeyHash = hash('sha256', $apiKey);
                SiteSetting::set("integrity_hash_{$productSlug}", $finalHash);
                SiteSetting::set("integrity_api_key_hash_{$productSlug}", $apiKeyHash);
            }

            Log::info('[LicenseProtectionInjector] Main file protection code injected & SDK file injected & require_once injected', [
                'zip'         => basename($zipPath),
                'plugin_file' => $entryName,
                'sdk_file'    => $pluginDir . 'includes/licenseos/licenseos-sdk.php',
                'api_url'     => $apiUrl,
            ]);

            // 已完整注入 LicenseOS（包含所有模块）
            if (str_contains($content, '// === LicenseOS License Protection ===') &&
                str_contains($content, '// === LicenseOS Integrity Guard ===') &&
                str_contains($content, '// === LicenseOS SDK Auto-Loader ===')) {
                Log::info('[LicenseProtectionInjector] Already fully injected with all modules');
                
                // --- 即使主文件已注入，仍需确保二级文件也注入了交叉验证 ---
                $mainFileName = basename($entryName);
                $secondaryTargets = $this->findSecondaryFiles($zip, $mainFileName);
                
                foreach ($secondaryTargets as $secIndex) {
                    $secEntryName = $zip->getNameIndex($secIndex);
                    $secContent = $zip->getFromIndex($secIndex);
                    
                    if ($secContent === false) continue;
                    
                    // 已注入则跳过
                    if (str_contains($secContent, 'LicenseOS Cross-Integrity Check')) {
                        continue;
                    }
                    
                    $guardCode = $this->generateSecondaryGuard($mainFileName);
                    
                    // 注入到闭合标签之前（如果有的话），否则追加到文件末尾
                    $closeTag = strrpos($secContent, '?' . '>');
                    if ($closeTag !== false) {
                        $secContent = substr($secContent, 0, $closeTag) . "\n" . $guardCode . "\n" . substr($secContent, $closeTag);
                    } else {
                        $secContent .= "\n" . $guardCode;
                    }
                    
                    $zip->addFromString($secEntryName, $secContent);
                    
                    Log::info('[LicenseProtectionInjector] Secondary cross-validation code injected (already fully injected path)', [
                        'zip'            => basename($zipPath),
                        'secondary_file' => $secEntryName,
                    ]);
                }
                
                return true;
            }

            // --- 二级文件交叉验证注入 ---
            $mainFileName = basename($entryName);
            $secondaryTargets = $this->findSecondaryFiles($zip, $mainFileName);
            $secondaryInjected = 0;
            
            // Debug: Log secondary targets found
            Log::info('[LicenseProtectionInjector] Secondary injection debug', [
                'main_file' => $mainFileName,
                'secondary_targets_count' => count($secondaryTargets),
                'secondary_target_names' => array_map(fn($idx) => $zip->getNameIndex($idx), $secondaryTargets),
            ]);

            foreach ($secondaryTargets as $secIndex) {
                $secEntryName = $zip->getNameIndex($secIndex);
                $secContent = $zip->getFromIndex($secIndex);

                if ($secContent === false) {
                    continue;
                }

                // 已注入则跳过
                if (str_contains($secContent, 'LicenseOS Cross-Integrity Check')) {
                    $secondaryInjected++;
                    continue;
                }

                $guardCode = $this->generateSecondaryGuard($mainFileName);

                // 注入到闭合标签之前（如果有的话），否则追加到文件末尾
                $closeTag = strrpos($secContent, '?' . '>');
                if ($closeTag !== false) {
                    $secContent = substr($secContent, 0, $closeTag) . "\n" . $guardCode . "\n" . substr($secContent, $closeTag);
                } else {
                    $secContent .= "\n" . $guardCode;
                }

                $zip->addFromString($secEntryName, $secContent);
                $secondaryInjected++;

                Log::info('[LicenseProtectionInjector] Secondary cross-validation code injected', [
                    'zip'            => basename($zipPath),
                    'secondary_file' => $secEntryName,
                ]);
            }

            // 标记全局注入开关
            SiteSetting::set('injection_enabled', '1');

            Log::info('[LicenseProtectionInjector] Successfully injected all modules including secondary checks');

            return true;
        } finally {
            $zip->close();
        }
    }

    /**
     * Inject LicenseOS protection into a WordPress theme ZIP.
     *
     * Follows the same pattern as inject() for plugins:
     * open ZIP → find main file → inject SDK → generate code → write back → close.
     *
     * @param string $zipPath ZIP file path (modified in-place)
     * @param string $apiUrl API URL
     * @param string $apiKey API key
     * @param string $productSlug Product slug
     * @return bool Whether injection succeeded
     */
    protected function injectTheme(string $zipPath, string $apiUrl, string $apiKey, string $productSlug): bool
    {
        $zip = new ZipArchive();

        try {
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                return false;
            }

            $functionsPhpIndex = $this->findThemeMainFile($zip);

            if ($functionsPhpIndex === null) {
                Log::warning('[LicenseProtectionInjector] Theme main file (functions.php) not found', [
                    'zip' => basename($zipPath),
                ]);
                return false;
            }

            $entryName = $zip->getNameIndex($functionsPhpIndex);
            $themeDir = '';
            if ($entryName) {
                $dir = dirname($entryName);
                $themeDir = ($dir !== '.' && $dir !== '') ? $dir . '/' : '';
            }

            // Inject SDK file
            $this->injectSdkFile($zip, $themeDir);

            $content = $zip->getFromIndex($functionsPhpIndex);

            if ($content === false) {
                return false;
            }

            // Auto-inject require_once for SDK file
            $content = $this->injectSdkRequireOnce($content);

            // Detect admin page slug and option prefix
            $menuInfo = $this->detectAdminPageSlug($zip, $themeDir);
            $adminPageSlug = $menuInfo['slug'] ?? null;
            if (!$adminPageSlug) {
                $adminPageSlug = $productSlug;
            }

            try {
                $optionPrefix = $this->detectPluginOptionPrefix($zip);
            } catch (\Exception $e) {
                Log::warning('[injectTheme] detectPluginOptionPrefix failed', ['error' => $e->getMessage()]);
                $optionPrefix = '';
            }

            if (!$optionPrefix || trim($optionPrefix) === '') {
                $optionPrefix = str_replace('-', '_', $productSlug);
            }

            Log::info('[LicenseProtectionInjector] Theme injection debug', [
                'theme_dir'    => $themeDir,
                'admin_slug'   => $adminPageSlug,
                'option_prefix' => $optionPrefix,
                'product_slug' => $productSlug,
            ]);

            // Generate theme-specific initialization code
            $initCode = $this->generateThemeInitCode($apiUrl, $apiKey, $productSlug, $optionPrefix, $adminPageSlug);

            // Generate protection + integrity guard code
            $protectionCode = $this->generateProtectionCode($apiUrl, $apiKey, $productSlug, $adminPageSlug, $optionPrefix);
            $protectionCode = $this->appendIntegrityGuard($protectionCode, $apiUrl, $apiKey, $productSlug, $adminPageSlug, $optionPrefix);

            $fullCode = $initCode . "\n" . $protectionCode;

            // Find injection point in functions.php
            $injected = false;

            // Priority 1: after defined('ABSPATH') check
            if (!$injected && preg_match(
                '/(if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)\s*.*?;\s*\n)/s',
                $content,
                $match,
                PREG_OFFSET_CAPTURE
            )) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $content = substr($content, 0, $pos) . "\n" . $fullCode . "\n" . substr($content, $pos);
                $injected = true;
            }

            // Priority 2: after Theme Name: comment block closing */
            if (!$injected && preg_match(
                '/(\*\/\s*\n)/',
                $content,
                $match,
                PREG_OFFSET_CAPTURE
            )) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $content = substr($content, 0, $pos) . "\n" . $fullCode . "\n" . substr($content, $pos);
                $injected = true;
            }

            // Priority 3: after <?php
            if (!$injected && preg_match(
                '/(<\?php\s*\n)/',
                $content,
                $match,
                PREG_OFFSET_CAPTURE
            )) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $content = substr($content, 0, $pos) . "\n" . $fullCode . "\n" . substr($content, $pos);
                $injected = true;
            }

            if (!$injected) {
                return false;
            }

            $zip->addFromString($entryName, $content);

            // Compute integrity hash
            if ($productSlug && preg_match(
                '/\/\/ === LicenseOS License Protection ===.*\/\/ === End LicenseOS Integrity Guard ===/s',
                $content,
                $runtimeMatch
            )) {
                $finalHash = hash('sha256', $runtimeMatch[0]);
                $apiKeyHash = hash('sha256', $apiKey);
                SiteSetting::set("integrity_hash_{$productSlug}", $finalHash);
                SiteSetting::set("integrity_api_key_hash_{$productSlug}", $apiKeyHash);
            }

            Log::info('[LicenseProtectionInjector] Theme protection code injected & SDK file injected', [
                'zip'           => basename($zipPath),
                'theme_file'    => $entryName,
                'sdk_file'      => $themeDir . 'includes/licenseos/licenseos-sdk.php',
                'api_url'       => $apiUrl,
            ]);

            // Secondary files cross-validation
            $mainFileName = basename($entryName);
            $secondaryTargets = $this->findSecondaryFiles($zip, $mainFileName);

            foreach ($secondaryTargets as $secIndex) {
                $secEntryName = $zip->getNameIndex($secIndex);
                $secContent = $zip->getFromIndex($secIndex);

                if ($secContent === false) continue;

                if (str_contains($secContent, 'LicenseOS Cross-Integrity Check')) {
                    continue;
                }

                $guardCode = $this->generateSecondaryGuard($mainFileName);

                $closeTag = strrpos($secContent, '?' . '>');
                if ($closeTag !== false) {
                    $secContent = substr($secContent, 0, $closeTag) . "\n" . $guardCode . "\n" . substr($secContent, $closeTag);
                } else {
                    $secContent .= "\n" . $guardCode;
                }

                $zip->addFromString($secEntryName, $secContent);

                Log::info('[LicenseProtectionInjector] Theme secondary cross-validation code injected', [
                    'zip'            => basename($zipPath),
                    'secondary_file' => $secEntryName,
                ]);
            }

            SiteSetting::set('injection_enabled', '1');

            Log::info('[LicenseProtectionInjector] Theme injection completed successfully');

            return true;
        } finally {
            $zip->close();
        }
    }

    /**
     * Find the main theme file (functions.php) in a theme ZIP.
     * Returns the index of functions.php or null.
     *
     * @param ZipArchive $zip ZIP instance
     * @return int|null Index of functions.php, or null if not found
     */
    protected function findThemeMainFile(ZipArchive $zip): ?int
    {
        $themeDir = '';
        $functionsPhpIndex = null;
        $styleCssFound = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!$name) continue;

            // Determine theme directory (first directory in ZIP)
            $parts = explode('/', $name);
            if (count($parts) > 1 && empty($themeDir)) {
                $themeDir = $parts[0] . '/';
            }

            // Check for style.css (theme identifier)
            if (!$styleCssFound && preg_match('#^' . preg_quote($themeDir, '#') . 'style\.css$#', $name)) {
                $styleContent = $zip->getFromIndex($i);
                if ($styleContent && preg_match('/Theme\s*Name\s*:/i', $styleContent)) {
                    $styleCssFound = true;
                }
            }

            // Check for functions.php
            if ($functionsPhpIndex === null && preg_match('#^' . preg_quote($themeDir, '#') . 'functions\.php$#', $name)) {
                $functionsPhpIndex = $i;
            }
        }

        // Theme must have both style.css (with Theme Name) and functions.php
        if ($styleCssFound && $functionsPhpIndex !== null) {
            return $functionsPhpIndex;
        }

        // Fallback: if we found functions.php in any directory
        if ($functionsPhpIndex !== null) {
            return $functionsPhpIndex;
        }

        return null;
    }

    /**
     * Generate initialization code for WordPress themes.
     *
     * Uses LicenseOS::enableThemeAutoUpdate() and LicenseOS::enableThemeLicenseGate()
     * static methods from the SDK.
     *
     * @param string $apiUrl API URL
     * @param string $apiKey API key
     * @param string $productSlug Product slug
     * @param string $optionPrefix Option prefix
     * @param string $menuSlug Admin page slug
     * @return string PHP code block
     */
    private function generateThemeInitCode(string $apiUrl, string $apiKey, string $productSlug, string $optionPrefix, string $menuSlug): string
    {
        $optionName = $optionPrefix . 'license_key';
        $statusName = $optionPrefix . 'license_status';
        $funcPrefix = str_replace('-', '_', rtrim($optionPrefix, '_'));
        $themeName = ucwords(str_replace(['-', '_'], ' ', $productSlug));

        return <<<PHP
// === LicenseOS Initialization Code ===
if (!defined('LICENSEOS_API_URL')) {
    define('LICENSEOS_API_URL', '{$apiUrl}');
}
if (!defined('LICENSEOS_API_KEY')) {
    define('LICENSEOS_API_KEY', '{$apiKey}');
}
if (!defined('LICENSEOS_PRODUCT_SLUG')) {
    define('LICENSEOS_PRODUCT_SLUG', '{$productSlug}');
}

add_action('after_setup_theme', function () {
    if (!class_exists('LicenseOS')) return;

    LicenseOS::init(
        LICENSEOS_API_URL,
        LICENSEOS_API_KEY,
        get_template_directory()
    );

    \$license_key = get_option('{$optionName}', '');
    if (\$license_key) {
        LicenseOS::enableThemeAutoUpdate('{$productSlug}', \$license_key);
    }

    LicenseOS::enableThemeLicenseGate([
        'theme_dir'    => '{$productSlug}',
        'slug'         => '{$menuSlug}',
        'name'         => '{$themeName}',
        'option_key'   => '{$optionName}',
        'status_key'   => '{$statusName}',
    ]);
});

function {$funcPrefix}_activate_license(\$license_key) {
    if (!class_exists('LicenseOS')) return false;
    \$result = LicenseOS::activate(
        \$license_key,
        \$_SERVER['HTTP_HOST'],
        '{$productSlug}'
    );

    if ((\$result['status'] ?? '') === 'success') {
        update_option('{$optionName}', \$license_key);
        update_option('{$statusName}', 'valid');
        LicenseOS::enableThemeAutoUpdate('{$productSlug}', \$license_key);
        return true;
    }

    return false;
}

function {$funcPrefix}_check_license() {
    if (!class_exists('LicenseOS')) return false;
    \$key = get_option('{$optionName}');
    if (!\$key) return false;

    \$result = LicenseOS::check(\$key);
    \$is_valid = (\$result['status'] ?? '') === 'valid';
    update_option('{$statusName}', \$is_valid ? 'valid' : 'invalid');
    return \$is_valid;
}

function {$funcPrefix}_deactivate_license() {
    if (!class_exists('LicenseOS')) return false;
    \$license_key = get_option('{$optionName}');
    if (!\$license_key) return true;

    \$response = wp_remote_post(
        LICENSEOS_API_URL . '/license/deactivate',
        [
            'body' => [
                'license_key' => \$license_key,
                'domain'      => \$_SERVER['HTTP_HOST'],
            ],
            'headers' => [
                'X-API-KEY' => LICENSEOS_API_KEY,
            ],
            'timeout' => 15,
        ]
    );

    if (!is_wp_error(\$response)) {
        \$result = json_decode(wp_remote_retrieve_body(\$response), true);
        if ((\$result['status'] ?? '') === 'success') {
            delete_option('{$optionName}');
            delete_option('{$statusName}');
            return true;
        }
    }

    return false;
}
// === End LicenseOS Initialization Code ===
PHP;
    }

    /**
     * 从 WP 插件 ZIP 中移除已注入的授权保护代码
     *
     * @param string $zipPath ZIP 文件的绝对路径（将被原地替换）
     * @return bool 是否移除成功（文件未注入过也返回 true）
     */
    public function uninject(string $zipPath): bool
    {
        $zip = new ZipArchive();

        try {
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                return false;
            }

            $mainIndex = $this->findMainPluginFile($zip);

            if ($mainIndex === null) {
                return false;
            }

            $content = $zip->getFromIndex($mainIndex);

            if ($content === false) {
                return false;
            }

            // No injection present — nothing to do
            if (!str_contains($content, 'LicenseOS License Protection')) {
                return true;
            }

            // Remove the entire injected block including surrounding blank lines
            // Matches both: legacy (End LicenseOS License Protection) and new (End LicenseOS Integrity Guard)
            $content = preg_replace(
                '/\n*\s*\/\/ === LicenseOS License Protection ===.*?(?:\/\/ === End LicenseOS Integrity Guard ===|\/\/ === End LicenseOS License Protection ===)\n*/s',
                "\n",
                $content
            );

            if ($content === null) {
                return false;
            }

            // Remove the auto-injected SDK require_once block
            $content = preg_replace(
                '/\n*\s*\/\/ === LicenseOS SDK Auto-Loader ===.*?\/\/ === End LicenseOS SDK Auto-Loader ===\n*/s',
                "\n",
                $content
            );

            if ($content === null) {
                return false;
            }

            // Remove initialization code block (NEW!)
            $content = $this->removeInitCodeBlock($content);

            if ($content === null) {
                return false;
            }

            $entryName = $zip->getNameIndex($mainIndex);
            $zip->addFromString($entryName, $content);

            // 删除 SDK 文件（新路径 includes/licenseos/ 及旧版根目录路径）
            $entryDir = dirname($entryName);
            $entryDir = ($entryDir === '.' || $entryDir === '') ? '' : $entryDir . '/';

            $sdkFile = $entryDir . 'includes/licenseos/licenseos-sdk.php';
            if ($zip->locateName($sdkFile) !== false) {
                $zip->deleteName($sdkFile);
            }

            $legacySdkFile = $entryDir . 'licenseos-sdk.php';
            if ($zip->locateName($legacySdkFile) !== false) {
                $zip->deleteName($legacySdkFile);
            }

            // 删除 includes/licenseos 目录（如果为空，仅含 . 和 ..）
            $sdkDir = $entryDir . 'includes/licenseos/';
            $dirIsEmpty = true;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) continue;
                if ($name !== $sdkDir && $name !== $sdkFile && str_starts_with($name, $sdkDir)) {
                    $dirIsEmpty = false;
                    break;
                }
            }
            if ($dirIsEmpty && ($zip->locateName($sdkDir) !== false || $zip->locateName(rtrim($sdkDir, '/')) !== false)) {
                $zip->deleteName(rtrim($sdkDir, '/'));
                $zip->deleteName($sdkDir);
            }

            // --- 清理二级文件的交叉验证代码 ---
            $mainFileName = basename($entryName);
            $secondaryTargets = $this->findSecondaryFiles($zip, $mainFileName);

            foreach ($secondaryTargets as $secIndex) {
                $secEntryName = $zip->getNameIndex($secIndex);
                $secContent = $zip->getFromIndex($secIndex);

                if ($secContent === false) {
                    continue;
                }

                if (!str_contains($secContent, 'LicenseOS Cross-Integrity Check')) {
                    continue;
                }

                $secContent = preg_replace(
                    '/\n*\s*\/\/ === LicenseOS Cross-Integrity Check ===.*?\/\/ === End LicenseOS Cross-Integrity Check ===\n*/s',
                    "\n",
                    $secContent
                );

                if ($secContent !== null) {
                    $zip->addFromString($secEntryName, $secContent);
                }
            }

            // 关闭全局注入开关
            SiteSetting::set('injection_enabled', '0');

            return true;
        } finally {
            $zip->close();
        }
    }

    /**
     * 移除初始化代码块（用于 uninject）
     *
     * @param string $content 文件内容
     * @return string 处理后的内容
     */
    private function removeInitCodeBlock(string $content): string
    {
        return preg_replace(
            '/\n*\s*\/\/ === LicenseOS Initialization Code ===.*?\/\/ === End LicenseOS Initialization Code ===\n*/s',
            "\n",
            $content
        );
    }

    /**
     * Generate complete initialization code for plugin main file
     *
     * @param string $apiUrl API 地址
     * @param string $apiKey API 密钥
     * @param string $productSlug 产品 slug
     * @return string PHP 代码块
     */
    private function generateInitCode(string $apiUrl, string $apiKey, string $productSlug, string $optionPrefix, string $menuSlug, string $pluginFile = '', string $parentSlug = 'admin.php'): string
    {
        $optionName = $optionPrefix . 'license_key';
        $statusName = $optionPrefix . 'license_status';
        $funcPrefix = str_replace('-', '_', rtrim($optionPrefix, '_')); // Normalize: replace hyphens + strip trailing underscore
        $pluginName = ucwords(str_replace(['-', '_'], ' ', $productSlug));
        
        return <<<PHP
// === LicenseOS Initialization Code ===
add_action('plugins_loaded', function () {
    if (!class_exists('LicenseOS')) return;

    LicenseOS::init(
        '{$apiUrl}',
        '{$apiKey}',
        __DIR__
    );

    \$license_key = get_option('{$optionName}', '');
    LicenseOS::enableAutoUpdate(
        '{$productSlug}',
        \$license_key
    );

    LicenseOS::enableLicenseGate([
        'slug'         => '{$menuSlug}',
        'name'         => '{$pluginName}',
        'option_key'   => '{$optionName}',
        'status_key'   => '{$statusName}',
        'parent_slug'  => '{$parentSlug}',
        'page_title'   => '{$pluginName} Settings',
        'product_slug' => '{$productSlug}',
    ]);
});

function {$funcPrefix}_activate_license(\$license_key) {
    if (!class_exists('LicenseOS')) return false;
    \$result = LicenseOS::activate(
        \$license_key,
        \$_SERVER['HTTP_HOST'],
        '{$productSlug}'
    );

    if ((\$result['status'] ?? '') === 'success') {
        update_option('{$optionName}', \$license_key);
        update_option('{$statusName}', 'valid');
        LicenseOS::enableAutoUpdate('{$productSlug}', \$license_key);
        return true;
    }

    return false;
}

function {$funcPrefix}_check_license() {
    if (!class_exists('LicenseOS')) return false;
    \$key = get_option('{$optionName}');
    if (!\$key) return false;

    \$result = LicenseOS::check(\$key);
    \$is_valid = (\$result['status'] ?? '') === 'valid';
    update_option('{$statusName}', \$is_valid ? 'valid' : 'invalid');
    return \$is_valid;
}

function {$funcPrefix}_deactivate_license() {
    if (!class_exists('LicenseOS')) return false;
    \$license_key = get_option('{$optionName}');
    if (!\$license_key) return true;

    \$response = wp_remote_post(
        '{$apiUrl}/license/deactivate',
        [
            'body' => [
                'license_key' => \$license_key,
                'domain'      => \$_SERVER['HTTP_HOST'],
            ],
            'headers' => [
                'X-API-KEY' => '{$apiKey}',
            ],
            'timeout' => 15,
        ]
    );

    if (!is_wp_error(\$response)) {
        \$result = json_decode(wp_remote_retrieve_body(\$response), true);
        if ((\$result['status'] ?? '') === 'success') {
            delete_option('{$optionName}');
            delete_option('{$statusName}');
            return true;
        }
    }

    return false;
}
// === End LicenseOS Initialization Code ===
PHP;
    }

    /**
     * 追加防篡改完整性校验代码（Integrity Guard）
     *
     * 设计决策：Integrity Guard 运行时提取代码块的哈希发送到服务器，
     * 由服务器对比存储的预期哈希。哈希在 inject() 方法中于所有注入完成后
     * 使用与运行时相同的正则提取并计算，确保时序一致。
     *
     * @param string $protectionCode 已有的 Protection 代码块
     * @param string $apiUrl API 地址
     * @param string $apiKey API 密钥
     * @param string $productSlug 产品 slug
     * @return string 包含 Integrity Guard 的完整代码
     */
    protected function appendIntegrityGuard(string $protectionCode, string $apiUrl, string $apiKey, string $productSlug, string $adminPageSlug, string $optionPrefix): string
    {
        $guard = <<<'GUARD'

// === LicenseOS Integrity Guard ===
if (!function_exists('licenseos_integrity_check')) {
    function licenseos_integrity_check() {
        $file = @file_get_contents(__FILE__);
        if (!$file) return true;

        // Extract the full LicenseOS code block (Protection + Integrity Guard)
        if (!preg_match('/\/\/ === LicenseOS License Protection ===.*\/\/ === End LicenseOS Integrity Guard ===/s', $file, $match)) {
            return false;
        }

        $currentHash = hash('sha256', $match[0]);

        $currentApiKey = '__API_KEY__';
        $apiKeyHash = hash('sha256', $currentApiKey);

        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $licenseKey = get_option('__OPTION_NAME__', '');

        // Report integrity status to platform (non-blocking)
        @wp_remote_post('__API_URL__/integrity/report', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'domain'        => $domain,
                'code_hash'     => $currentHash,
                'api_key_hash'  => $apiKeyHash,
                'license_key'   => $licenseKey,
                'api_key'       => $currentApiKey,
                'product_slug'  => '__PRODUCT_SLUG__',
            ]),
            'timeout'  => 5,
            'blocking' => false,
        ]);

        // Cross-integrity: verify secondary files still carry the cross-check marker
        // 递归扫描（限深度 2-3 层），兼容交叉标记被注入到嵌套目录文件的情况
        $pluginDir = plugin_dir_path(__FILE__);
        $crossCheckOk = false;
        $dirs_to_check = ['includes', 'src', 'lib', 'inc'];
        foreach ($dirs_to_check as $dir) {
            $dirPath = $pluginDir . $dir;
            if (!is_dir($dirPath)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            $iterator->setMaxDepth(2);

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = @file_get_contents($file->getPathname());
                    if ($content && strpos($content, 'LicenseOS Cross-Integrity Check') !== false) {
                        $crossCheckOk = true;
                        break 2;
                    }
                }
            }
        }
        if (!$crossCheckOk) {
            // Secondary guard files are missing or tampered
            return false;
        }

        // Local quick check: if API Key is emptied or obviously tampered, fail immediately
        if (strlen($currentApiKey) < 5) {
            return false;
        }
        return true;
    }
}

// Integrity check hook — runs every 6 hours
add_action('admin_init', function() {
    // 快速 API Key 校验（绕过缓存，每次 admin_init 都检查）
    $__apiKey = '__API_KEY__';
    if (strlen($__apiKey) < 5 || $__apiKey === '' || $__apiKey === '__API_KEY__') {
        update_option('licenseos_tampered', true);
        delete_transient('licenseos_integrity_check'); // 清除缓存，强制下次完整检查
    }

    $lastCheck = get_transient('licenseos_integrity_check');
    if ($lastCheck === false) {
        $isOk = licenseos_integrity_check();
        set_transient('licenseos_integrity_check', $isOk ? 'ok' : 'tampered', 6 * HOUR_IN_SECONDS);
        if ($isOk) {
            // 校验通过：清除篡改标记（修复误报后自动恢复）
            delete_option('licenseos_tampered');
        } else {
            update_option('licenseos_tampered', true);
            // Feature gate: remove admin menu items for this plugin
            add_action('admin_menu', function() {
                global $menu;
                if (!is_array($menu)) return;
                foreach ($menu as $item) {
                    if (isset($item[2]) && strpos($item[2], '__PRODUCT_SLUG__') !== false) {
                        remove_menu_page($item[2]);
                    }
                }
            }, 999);
        }
    } else if ($lastCheck === 'tampered') {
        update_option('licenseos_tampered', true);
        // Feature gate: remove admin menu items for this plugin
        add_action('admin_menu', function() {
            global $menu;
            if (!is_array($menu)) return;
            foreach ($menu as $item) {
                if (isset($item[2]) && strpos($item[2], '__PRODUCT_SLUG__') !== false) {
                    remove_menu_page($item[2]);
                }
            }
        }, 999);
    }
});

// Feature gate: dequeue frontend scripts/styles when tampered (runs outside admin_init so it works on frontend)
add_action('wp_enqueue_scripts', function() {
    if (!get_option('licenseos_tampered', false)) return;
    global $wp_scripts, $wp_styles;
    if (isset($wp_scripts->queue)) {
        foreach ($wp_scripts->queue as $handle) {
            if (strpos($handle, '__PRODUCT_SLUG__') !== false) {
                wp_dequeue_script($handle);
            }
        }
    }
    if (isset($wp_styles->queue)) {
        foreach ($wp_styles->queue as $handle) {
            if (strpos($handle, '__PRODUCT_SLUG__') !== false) {
                wp_dequeue_style($handle);
            }
        }
    }
}, 999);

// Feature gate: remove shortcodes when tampered (init fires before admin_init, so register at module level)
add_action('init', function() {
    if (!get_option('licenseos_tampered', false)) return;
    global $shortcode_tags;
    if (!is_array($shortcode_tags)) return;
    foreach ($shortcode_tags as $tag => $callback) {
        if (strpos($tag, '__PRODUCT_SLUG__') !== false) {
            remove_shortcode($tag);
        }
    }
}, 999);

// Tamper popup — only on the plugin's own admin pages
add_action('admin_footer', function() {
    if (!get_option('licenseos_tampered', false)) return;

    // Only show full-screen popup on the plugin's own settings pages
    $current_page = isset($_GET['page']) ? $_GET['page'] : '';
    if (strpos($current_page, '__ADMIN_PAGE_SLUG__') !== 0) return;

    // Allow dismiss once per session
    if (isset($_GET['licenseos_dismiss']) && wp_verify_nonce($_GET['licenseos_dismiss'], 'dismiss_tamper')) {
        set_transient('licenseos_tamper_dismissed', true, 30 * MINUTE_IN_SECONDS);
        return;
    }
    if (get_transient('licenseos_tamper_dismissed')) return;

    $dismiss_url = wp_nonce_url(add_query_arg('licenseos_dismiss', '1'), 'dismiss_tamper');

    echo '<div id="licenseos-tamper-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;">';
    echo '<div style="background:#fff;border-radius:12px;padding:40px;max-width:520px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.5);">';
    echo '<div style="font-size:56px;margin-bottom:20px;">🔒</div>';
    echo '<h2 style="color:#dc3232;margin:0 0 16px;font-size:22px;">安全警告</h2>';
    echo '<p style="font-size:16px;line-height:1.8;color:#333;margin:0;">';
    echo '嘿！那个谁！你不可以篡改代码的任何地方，否则不可使用！<br>';
    echo '请到官方下载最新插件版；';
    echo '</p>';
    echo '<a href="' . esc_url($dismiss_url) . '" style="display:inline-block;margin-top:20px;color:#666;font-size:13px;text-decoration:none;">不再显示（本次会话）</a>';
    echo '</div></div>';
});
// === End LicenseOS Integrity Guard ===
GUARD;

        $guard = str_replace('__API_KEY__', $apiKey, $guard);
        $guard = str_replace('__API_URL__', $apiUrl, $guard);
        $guard = str_replace('__PRODUCT_SLUG__', $productSlug, $guard);
        $guard = str_replace('__ADMIN_PAGE_SLUG__', $adminPageSlug, $guard);
        $guard = str_replace('__OPTION_NAME__', $optionPrefix . '_license_key', $guard);

        return $protectionCode . $guard;
    }

    /**
     * 生成授权保护代码片段
     */
    protected function generateProtectionCode(string $apiUrl, string $apiKey, string $productSlug, string $adminPageSlug, string $optionPrefix): string
    {
        $code = <<<'PROT'
// === LicenseOS License Protection ===
if (!function_exists('licenseos_check_license')) {
    function licenseos_check_license() {
        $license_key = get_option('__OPTION_NAME__', '');
        $cached = get_transient('licenseos_check_result');
        if ($cached !== false) {
            return $cached === 'valid';
        }
        $response = wp_remote_post('__API_URL__/license/check', [
            'headers' => [
                'X-API-KEY' => '__API_KEY__',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'license_key' => $license_key,
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
            ]),
            'timeout' => 10,
        ]);
        if (is_wp_error($response)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $status = $data['status'] ?? '';
        set_transient('licenseos_check_result', $status, 12 * HOUR_IN_SECONDS);
        return $status === 'valid';
    }
}

// === End LicenseOS License Protection ===
PROT;

        $code = str_replace('__API_URL__', $apiUrl, $code);
        $code = str_replace('__API_KEY__', $apiKey, $code);
        $code = str_replace('__OPTION_NAME__', $optionPrefix . '_license_key', $code);

        return $code;
    }

    /**
     * 查找二级注入目标文件（includes/或 src/ 目录下的 PHP 文件）
     *
     * 交叉验证机制：二级文件会检查主文件仍包含 LicenseOS 保护标记。
     * 如果标记被删除则触发篡改警告。
     *
     * @param ZipArchive $zip ZIP 实例
     * @param string $mainFileName 主文件的 basename（用于排除）
     * @return int[] 匹配文件的索引号数组（最多 2 个）
     */
    protected function findSecondaryFiles(ZipArchive $zip, string $mainFileName): array
    {
        $topLevelFiles = [];
        $nestedFiles = [];
        $fileCount = $zip->numFiles;
        $dirTokens = ['includes/', 'src/', 'lib/', 'inc/'];

        for ($i = 0; $i < $fileCount; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || !str_ends_with(strtolower($name), '.php')) {
                continue;
            }

            $normalizedName = str_replace('\\', '/', $name);
            $baseName = basename($normalizedName);

            // 跳过主文件
            if ($baseName === $mainFileName) {
                continue;
            }

            // 只注入 includes/ 或 src/ 目录下的 PHP 文件
            $relativeStart = -1;
            foreach ($dirTokens as $token) {
                $pos = strpos($normalizedName, $token);
                if ($pos !== false) {
                    $relativeStart = $pos + strlen($token);
                    break;
                }
            }
            if ($relativeStart < 0) {
                continue;
            }

            // 跳过 SDK 文件（不能注入自身）
            if (stripos($baseName, 'licenseos-sdk') !== false
                || stripos($baseName, 'licenseos_sdk') !== false) {
                continue;
            }

            // 跳过空文件
            $stat = $zip->statIndex($i);
            if (($stat['size'] ?? 0) <= 0) {
                continue;
            }

            // 计算相对路径深度：顶层文件（直接位于 includes/ 等第一层）优先
            $relativePath = substr($normalizedName, $relativeStart);
            $depth = substr_count($relativePath, '/');

            if ($depth === 0) {
                $topLevelFiles[] = $i;
            } else {
                $nestedFiles[] = $i;
            }
        }

        // 优先使用顶层文件，不足时用嵌套文件补充（最多注入 2 个）
        return array_slice(array_merge($topLevelFiles, $nestedFiles), 0, 2);
    }

    /**
     * 生成二级文件的交叉校验代码（Cross-Integrity Guard）
     *
     * 逻辑：运行时检查插件主文件仍包含 LicenseOS License Protection 标记。
     * 如果标记不存在 → 说明主文件注入代码被删除 → 触发篡改弹窗 + 禁用菜单。
     *
     * @param string $mainFileName 主文件名（basename）
     * @return string PHP 代码块
     */
    protected function generateSecondaryGuard(string $mainFileName): string
    {
        $code = <<<'CROSS'
// === LicenseOS Cross-Integrity Check ===
if (!function_exists('licenseos_cross_check')) {
    function licenseos_cross_check() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        // 动态向上查找主文件，定位插件根目录（兼容文件位于嵌套目录的情况）
        $currentDir = dirname(__FILE__);
        $mainFileName = '__MAIN_FILE__';
        $pluginRoot = null;
        $maxDepth = 5;
        for ($i = 0; $i < $maxDepth; $i++) {
            if (file_exists($currentDir . '/' . $mainFileName)) {
                $pluginRoot = $currentDir;
                break;
            }
            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) break;
            $currentDir = $parentDir;
        }
        if ($pluginRoot === null) {
            $pluginRoot = dirname(dirname(__FILE__)); // 兜底
        }
        $pluginDir = $pluginRoot;
        if (!$pluginDir || !is_dir($pluginDir)) return;

        // 检查主文件是否包含 LicenseOS 保护标记
        $mainFile = $pluginDir . '/' . $mainFileName;
        $hasProtection = false;

        if (is_file($mainFile)) {
            $content = @file_get_contents($mainFile);
            if ($content && strpos($content, 'LicenseOS License Protection') !== false) {
                $hasProtection = true;
            }
        }

        // 兜底：扫描根目录所有 PHP 文件
        if (!$hasProtection) {
            $rootPhpFiles = glob($pluginDir . '/*.php');
            foreach ($rootPhpFiles as $f) {
                $c = @file_get_contents($f);
                if ($c && strpos($c, 'LicenseOS License Protection') !== false) {
                    $hasProtection = true;
                    break;
                }
            }
        }

        if (!$hasProtection) {
            // 主文件的保护代码被删除？ → 标记篡改
            update_option('licenseos_tampered', true);
            update_option('licenseos_cross_tampered', true);

            // 显示篡改弹窗
            add_action('admin_footer', function() {
                if (get_option('licenseos_cross_tampered')) {
                    echo '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:999999;display:flex;align-items:center;justify-content:center;">';
                    echo '<div style="background:#fff;border-radius:12px;padding:40px;max-width:520px;text-align:center;">';
                    echo '<div style="font-size:56px;margin-bottom:20px;">🔒</div>';
                    echo '<h2 style="color:#dc3232;margin:0 0 16px;">安全警告</h2>';
                    echo '<p style="font-size:16px;line-height:1.8;color:#333;">';
                    echo '嘿！那个谁！你不可以篡改代码的任何地方，否则不可使用！<br>';
                    echo '请到官方下载最新插件版；';
                    echo '</p></div></div>';
                    echo '<script>document.body.style.overflow="hidden";</script>';
                }
            }, 1);

            // 移除管理员菜单
            add_action('admin_menu', function() {
                global $menu;
                foreach ($menu as $key => $item) {
                    if (isset($item[1]) && $item[1] === 'manage_options') {
                        remove_menu_page($item[2]);
                    }
                }
            }, 999);
        }
    }
    add_action('admin_init', 'licenseos_cross_check');
}
// === End LicenseOS Cross-Integrity Check ===
CROSS;

        return str_replace('__MAIN_FILE__', $mainFileName, $code);
    }

    /**
     * 将 SDK 文件注入到 ZIP 中
     *
     * @param ZipArchive $zip 已打开的 ZIP 对象
     * @param string $pluginDir ZIP 内的插件目录前缀（如 "move-bottom-menu/" 或 ""）
     * @return bool 是否成功注入
     */
    private function injectSdkFile(ZipArchive $zip, string $pluginDir = ''): bool
    {
        $sdkSource = base_path('app/SDK/WordPress/licenseos-sdk.php');

        if (!file_exists($sdkSource)) {
            Log::warning('[LicenseProtectionInjector] SDK source file does not exist', [
                'path' => $sdkSource,
            ]);
            return false;
        }

        $sdkTarget = $pluginDir . 'includes/licenseos/licenseos-sdk.php';

        // If SDK file already exists in ZIP, skip (preserve developer's existing version)
        if ($zip->locateName($sdkTarget) !== false) {
            Log::info('[LicenseProtectionInjector] SDK file already exists in ZIP, skipping injection', [
                'target' => $sdkTarget,
            ]);
            return true;
        }

        // 复制 SDK 文件之前，确保 includes/licenseos/ 目录存在
        $sdkDir = $pluginDir . 'includes/licenseos/';
        if ($zip->locateName($sdkDir) === false && $zip->locateName(rtrim($sdkDir, '/')) === false) {
            $zip->addEmptyDir(rtrim($sdkDir, '/'));
        }

        $sdkContent = file_get_contents($sdkSource);
        $zip->addFromString($sdkTarget, $sdkContent);

        Log::info('[LicenseProtectionInjector] SDK file injected', [
            'target' => $sdkTarget,
        ]);

        return true;
    }

    /**
     * 在主文件中自动添加 require_once 引用 SDK 文件
     *
     * 如果主文件已包含 licenseos-sdk.php 引用则跳过。
     * 使用 file_exists() 包裹，确保即使 SDK 文件存在也不白屏。
     *
     * @param string $content 主文件内容
     * @return string 修改后的主文件内容
     */
    private function injectSdkRequireOnce(string $content): string
    {
        // 已完整注入 LicenseOS 则跳过（检查完整的 Auto-Loader 块）
        if (str_contains($content, '// === LicenseOS SDK Auto-Loader ===')) {
            return $content;
        }

        $requireBlock = <<<'REQUIRE'

// === LicenseOS SDK Auto-Loader ===
if (file_exists(__DIR__ . '/includes/licenseos/licenseos-sdk.php')) {
    require_once __DIR__ . '/includes/licenseos/licenseos-sdk.php';
}
// === End LicenseOS SDK Auto-Loader ===

REQUIRE;

        $injected = false;

        // Priority 1: after defined('ABSPATH') check
        if (!$injected && preg_match(
            '/(if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)\s*.*?;\s*\n)/s',
            $content,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            $pos = $match[0][1] + strlen($match[0][0]);
            $content = substr($content, 0, $pos) . $requireBlock . substr($content, $pos);
            $injected = true;
        }

        // Priority 2: after Plugin Name: comment block closing */
        if (!$injected && preg_match(
            '/(\*\/\s*\n)/',
            $content,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            $pos = $match[0][1] + strlen($match[0][0]);
            $content = substr($content, 0, $pos) . $requireBlock . substr($content, $pos);
            $injected = true;
        }

        // Priority 3: after <?php
        if (!$injected && preg_match(
            '/(<\?php\s*\n)/',
            $content,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            $pos = $match[0][1] + strlen($match[0][0]);
            $content = substr($content, 0, $pos) . $requireBlock . substr($content, $pos);
            $injected = true;
        }

        return $content;
    }

    /**
     * 从 ZIP 中探测插件的 admin 菜单 slug 及其 parent_slug
     *
     * 扫描所有 PHP 文件，提取 add_menu_page() 的第 4 个参数（menu_slug）。
     * 顶级菜单（add_menu_page / add_theme_page）的 parent_slug 固定为 'admin.php'；
     * 子菜单（add_submenu_page）的 parent_slug 为第 1 个参数。
     *
     * @param ZipArchive $zip ZIP 实例
     * @param string $pluginDir 插件目录前缀（未使用，保留扩展）
     * @return array|null ['slug' => 菜单slug, 'parent_slug' => 父级菜单slug]，未找到返回 null
     */
    protected function detectAdminPageSlug(ZipArchive $zip, string $pluginDir = ''): ?array
    {
        $this->detectedParentSlug = '';
        $fileCount = $zip->numFiles;
        $candidates = [];
        $topLevelSlugs = [];   // 通过 add_menu_page/add_theme_page 注册的顶级菜单 slug
        $submenuParents = [];  // 子菜单 slug => 其 parent_slug

        // WordPress 权限（capability）黑名单：这些是权限而非菜单 slug，禁止作为候选
        $capabilityBlacklist = [
            'manage_options', 'administrator', 'edit_posts', 'edit_pages',
            'publish_posts', 'manage_categories', 'edit_theme_options',
            'edit_others_posts', 'delete_posts', 'upload_files', 'read',
        ];
        $isCapability = function (string $slug) use ($capabilityBlacklist): bool {
            return in_array($slug, $capabilityBlacklist, true);
        };
        
        for ($i = 0; $i < $fileCount; $i++) {
            $name = $zip->getNameIndex($i);
            if (!$name || !str_ends_with(strtolower($name), '.php')) continue;
    
            $content = $zip->getFromIndex($i);
            if (!$content) continue;
    
            // Strategy 1: Match add_menu_page with bracket depth counting
            if (preg_match_all('/add_menu_page\s*\(/', $content, $starts, PREG_OFFSET_CAPTURE)) {
                foreach ($starts[0] as $startMatch) {
                    $pos = $startMatch[1] + strlen($startMatch[0]);
                    $depth = 1;
                    $argsStr = '';
                    $len = strlen($content);
                    while ($pos < $len && $depth > 0) {
                        $ch = $content[$pos];
                        if ($ch === '(') $depth++;
                        elseif ($ch === ')') $depth--;
                        if ($depth > 0) $argsStr .= $ch;
                        $pos++;
                    }
                    // 从 argsStr 中按顶层逗号分割参数
                    $params = [];
                    $current = '';
                    $innerDepth = 0;
                    $inStr = false;
                    $strChar = '';
                    for ($j = 0; $j < strlen($argsStr); $j++) {
                        $c = $argsStr[$j];
                        if ($inStr) {
                            $current .= $c;
                            if ($c === $strChar && ($j === 0 || $argsStr[$j-1] !== '\\')) $inStr = false;
                        } elseif ($c === "'" || $c === '"') {
                            $inStr = true;
                            $strChar = $c;
                            $current .= $c;
                        } elseif ($c === '(') {
                            $innerDepth++;
                            $current .= $c;
                        } elseif ($c === ')') {
                            $innerDepth--;
                            $current .= $c;
                        } elseif ($c === ',' && $innerDepth === 0) {
                            $params[] = trim($current);
                            $current = '';
                        } else {
                            $current .= $c;
                        }
                    }
                    if ($current !== '') $params[] = trim($current);

                    // 第4个参数(index 3)是 menu_slug（顶级菜单，parent_slug 固定为 admin.php）
                    if (isset($params[3])) {
                        if (preg_match('/[\'"]([^\'"]+)[\'"]/' , $params[3], $slugMatch)) {
                            $slug = $slugMatch[1];
                            if (!$isCapability($slug)) {
                                $candidates[$slug] = ($candidates[$slug] ?? 0) + 10;
                                $topLevelSlugs[$slug] = true;
                            }
                        }
                    }
                }
            }
            
            // Strategy 2: add_submenu_page - extract BOTH parent slug (param 1) AND menu slug (param 4)
            if (preg_match_all('/add_submenu_page\s*\(/', $content, $subStarts, PREG_OFFSET_CAPTURE)) {
                foreach ($subStarts[0] as $startMatch) {
                    $pos = $startMatch[1] + strlen($startMatch[0]);
                    $depth = 1;
                    $argsStr = '';
                    $len = strlen($content);
                    while ($pos < $len && $depth > 0) {
                        $ch = $content[$pos];
                        if ($ch === '(') $depth++;
                        elseif ($ch === ')') $depth--;
                        if ($depth > 0) $argsStr .= $ch;
                        $pos++;
                    }
                    // Split by top-level commas (same logic as Strategy 1)
                    $params = [];
                    $current = '';
                    $innerDepth = 0;
                    $inStr = false;
                    $strChar = '';
                    for ($j = 0; $j < strlen($argsStr); $j++) {
                        $c = $argsStr[$j];
                        if ($inStr) {
                            $current .= $c;
                            if ($c === $strChar && ($j === 0 || $argsStr[$j-1] !== '\\')) $inStr = false;
                        } elseif ($c === "'" || $c === '"') {
                            $inStr = true;
                            $strChar = $c;
                            $current .= $c;
                        } elseif ($c === '(') {
                            $innerDepth++;
                            $current .= $c;
                        } elseif ($c === ')') {
                            $innerDepth--;
                            $current .= $c;
                        } elseif ($c === ',' && $innerDepth === 0) {
                            $params[] = trim($current);
                            $current = '';
                        } else {
                            $current .= $c;
                        }
                    }
                    if ($current !== '') $params[] = trim($current);
            
                    // Param 0 = parent slug (e.g., 'options-general.php')
                    $submenuParent = '';
                    if (isset($params[0]) && preg_match('/[\'"]([^\'"]+)[\'"]/' , $params[0], $parentMatch)) {
                        $submenuParent = $parentMatch[1];
                        $this->detectedParentSlug = $submenuParent;
                    }
            
                    // Param 4 = menu slug (e.g., 'fluent-mail')
                    // 注意：add_submenu_page($parent, $page_title, $menu_title, $capability, $menu_slug)
                    // index 3 是 capability，index 4 才是 menu_slug
                    if (isset($params[4]) && preg_match('/[\'"]([^\'"]+)[\'"]/' , $params[4], $slugMatch)) {
                        $slug = $slugMatch[1];
                        if (!$isCapability($slug)) {
                            $candidates[$slug] = ($candidates[$slug] ?? 0) + 7;
                            if ($submenuParent !== '') {
                                $submenuParents[$slug] = $submenuParent;
                            }
                        }
                    }
                }
            }
            
            // Strategy 3: Look for common admin page slug patterns
            if (preg_match('/menu_slug\s*[\'"]([^\'"]+)[\'"]/i', $content, $slugMatch)) {
                if (!$isCapability($slugMatch[1])) {
                    $candidates[$slugMatch[1]] = ($candidates[$slugMatch[1]] ?? 0) + 3; // Low priority
                }
            }
            
            // Strategy 4: add_theme_page (for themes)
            if (preg_match_all('/add_theme_page\s*\(/', $content, $themeStarts, PREG_OFFSET_CAPTURE)) {
                foreach ($themeStarts[0] as $startMatch) {
                    $pos = $startMatch[1] + strlen($startMatch[0]);
                    $depth = 1;
                    $argsStr = '';
                    $len = strlen($content);
                    while ($pos < $len && $depth > 0) {
                        $ch = $content[$pos];
                        if ($ch === '(') $depth++;
                        elseif ($ch === ')') $depth--;
                        if ($depth > 0) $argsStr .= $ch;
                        $pos++;
                    }
                    // Extract strings, skip __() internals
                    if (preg_match_all('/[\'"]([^\'"]+)[\'"]/', $argsStr, $strMatches)) {
                        // add_theme_page($page_title, $menu_title, $capability, $menu_slug, ...)
                        // Find the slug by looking for a string that looks like a slug (contains hyphens)
                        foreach ($strMatches[1] as $str) {
                            if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)+$/', $str) && !$isCapability($str)) {
                                $candidates[$str] = ($candidates[$str] ?? 0) + 8;
                                $topLevelSlugs[$str] = true; // add_theme_page 创建的也是顶级菜单
                            }
                        }
                    }
                }
            }
        }
        
        if (!empty($candidates)) {
            arsort($candidates);
            $winner = key($candidates);

            // 计算 parent_slug：
            // - 顶级菜单（add_menu_page/add_theme_page）→ 'admin.php'
            // - 胜出 slug 本身就是某个子菜单的 parent → 说明它是顶级菜单 → 'admin.php'
            // - 子菜单 → 检测到的 parent_slug
            if (isset($topLevelSlugs[$winner]) || ($submenuParents[$winner] ?? '') === $winner) {
                $parentSlug = 'admin.php';
            } else {
                $parentSlug = $submenuParents[$winner] ?? 'admin.php';
            }

            $this->detectedParentSlug = $parentSlug;
            return ['slug' => $winner, 'parent_slug' => $parentSlug];
        }

        return null;
    }
    
    /**
     * Detect plugin option prefix by scanning PHP files for get_option/update_option/delete_option calls
     *
     * Example patterns:
     * - mbm_license_key -> mbm_
     * - wpo_minify_options -> wpo_
     * - move_bottom_menu_license_key -> move_bottom_menu_
     *
     * @param ZipArchive $zip ZIP instance
     * @return string Option prefix (without trailing underscore)
     */
    protected function detectPluginOptionPrefix(ZipArchive $zip): string
    {
        $fileCount = $zip->numFiles;
        $prefixCounts = [];
        
        for ($i = 0; $i < $fileCount; $i++) {
            $name = $zip->getNameIndex($i);
            if (!$name || !str_ends_with(strtolower($name), '.php')) continue;
        
            $content = $zip->getFromIndex($i);
            if (!$content) continue;
        
            // Strategy 1: Match get_option/update_option/delete_option with prefix_license pattern
            if (preg_match_all('/(get_|update_|delete_)option\s*\(\s*[\'"]([a-z]+)_license_[a-z]+[\'"]/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (isset($match[2])) {
                        $prefix = $match[2] . '_';
                        $prefixCounts[$prefix] = ($prefixCounts[$prefix] ?? 0) + 10; // High priority
                    }
                }
            }
                
            // Strategy 2: Match register_setting with option group names
            if (preg_match_all('/register_setting\s*\(\s*[\'"]([a-z]+)_[a-z]+[\'"]/', $content, $regMatches)) {
                foreach ($regMatches[1] as $prefix) {
                    $prefix = $prefix . '_';
                    $prefixCounts[$prefix] = ($prefixCounts[$prefix] ?? 0) + 5; // Medium priority
                }
            }
                
            // Strategy 3: Match common get_option/update_option/delete_option
            if (preg_match_all('/(?:get_|update_|delete_)option\s*\(\s*[\'"]([a-z0-9_-]+)[\'"]/i', $content, $optMatches)) {
                $uniquePrefixes = array_unique(array_filter(array_map(function($optName) {
                    // Extract the first word segment as prefix
                    // e.g., 'fluentmail-settings' -> 'fluentmail_'
                    // e.g., 'woocommerce_settings' -> 'woocommerce_'
                    if (preg_match('/^([a-z][a-z0-9]*)/', $optName, $prefixMatch)) {
                        return $prefixMatch[1] . '_';
                    }
                    return null;
                }, $optMatches[1])));

                if (!empty($uniquePrefixes)) {
                    foreach ($uniquePrefixes as $prefix) {
                        $weight = strlen($prefix) <= 12 ? 3 : 1;
                        $prefixCounts[$prefix] = ($prefixCounts[$prefix] ?? 0) + $weight;
                    }
                }
            }
        }
        
        if (!empty($prefixCounts)) {
            arsort($prefixCounts);
            return key($prefixCounts);
        }
        
        return '';
    }

    /**
     * 查找 ZIP 中的插件主文件索引
     *
     * 遍历 ZIP 内所有 .php 文件，读取前 8KB 内容，
     * 检查是否包含 Plugin Name: 头部标记。
     *
     * @return int|null 匹配文件的索引号，未找到返回 null
     */
    protected function findMainPluginFile(ZipArchive $zip): ?int
    {
        $fileCount = $zip->numFiles;
    
        for ($i = 0; $i < $fileCount; $i++) {
            $name = $zip->getNameIndex($i);
    
            if ($name === false || !str_ends_with(strtolower($name), '.php')) {
                continue;
            }
    
            // Skip files nested too deep (plugin root files are typically at 0-1 level)
            // but don't skip entirely — some plugins have subdirectory structures
            $stat = $zip->statIndex($i);
            $size = $stat['size'] ?? 0;
    
            // Read up to 8KB for header detection
            $readSize = min($size, 8192);
            if ($readSize <= 0) {
                continue;
            }
    
            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
    
            $header = fread($stream, $readSize);
            fclose($stream);
    
            if ($header === false) {
                continue;
            }
    
            // Must have BOTH: WordPress plugin header AND security check (ABSPATH or WPINC)
            // to distinguish the real main plugin file from other PHP files in the ZIP
            $hasPluginHeader = (bool) preg_match('/Plugin\s*Name\s*:/i', $header);
            $hasSecurityCheck = (bool) preg_match('/(defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)|!\s*defined\s*\(\s*[\'"]WPINC[\'"]\s*\))/i', $header);
    
            if ($hasPluginHeader && $hasSecurityCheck) {
                $this->detectedPluginFile = $name;
                return $i;
            }
        }
    
        return null;
    }
    
    /**
     * Verify that injection was successful by checking for required code blocks.
     *
     * @param string $zipPath Absolute path to the ZIP file
     * @return array{status: string, reason?: string, verified?: bool}
     */
    public function verifyInjection(string $zipPath): array
    {
        $zip = new ZipArchive();
            
        if ($zip->open($zipPath) !== true) {
            return ['status' => 'failed', 'reason' => 'Cannot open ZIP file'];
        }
        
        // 使用 inject() 阶段记录的索引，而不是重新扫描
        if ($this->mainPluginFileIndex !== null) {
            $mainIndex = $this->mainPluginFileIndex;
        } else {
            $mainIndex = $this->findMainPluginFile($zip);
            if ($mainIndex === null) {
                $zip->close();
                return ['status' => 'failed', 'reason' => 'No main plugin file found'];
            }
        }
            
        $content = $zip->getFromIndex($mainIndex);
        $zip->close();
            
        if ($content === false) {
            return ['status' => 'failed', 'reason' => 'Cannot read main plugin file'];
        }
            
        // Required code markers that must exist after successful injection
        $requiredMarkers = [
            '// === LicenseOS SDK Auto-Loader ===',
            'if (file_exists(__DIR__ . \'/includes/licenseos/licenseos-sdk.php\'))',
            'require_once __DIR__ . \'/includes/licenseos/licenseos-sdk.php\'',
            '// === LicenseOS Initialization Code ===',
            'LicenseOS::init(',
            'LicenseOS::enableAutoUpdate(',
            'LicenseOS::enableLicenseGate([',
            '// === LicenseOS License Protection ===',
            'licenseos_check_license()',
            '// === LicenseOS Integrity Guard ===',
            'licenseos_integrity_check()',
            '$apiKeyHash',
            '// === End LicenseOS Integrity Guard ===',
        ];
            
        $missing = [];
        foreach ($requiredMarkers as $marker) {
            if (strpos($content, $marker) === false) {
                $missing[] = $marker;
            }
        }
            
        if (!empty($missing)) {
            return [
                'status' => 'incomplete',
                'reason' => 'Missing code markers: ' . implode(', ', array_map(function($m) {
                    return '"' . substr($m, 0, 50) . '..."';
                }, $missing)),
                'markers' => $missing,
            ];
        }
            
        return [
            'status' => 'success',
            'verified' => true,
            'code_length' => strlen($content),
        ];
    }
}