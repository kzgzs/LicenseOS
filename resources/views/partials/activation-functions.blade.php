// LicenseOS 授权代码模板 - Activation Functions Reference
// ================================================
// 注意：以下函数为参考示例，需在你的 WordPress 插件/主题设置页中适当调用
// 平台已通过一键注入系统自动处理核心授权检查逻辑
//
// 查找注释位置：=== LicenseOS Initialization Code ===
// 
// PHP 激活函数 (推荐在设置页保存时调用)
@php
$activationCode = <<<PHP
function activate_license(\$license_key) {
    \$result = LicenseOS::activate(
        \$license_key, 
        \$_SERVER['HTTP_HOST'], 
        '{$product->slug}'
    );
    
    if ((\$result['status'] ?? '') === 'success') {
        // 存储密钥到 WordPress 选项表
        update_option('{{ {str_replace(' ', '', strtoupper($product->name))}}_license_key', \$license_key);
        // 存储授权状态
        update_option('{{ {str_replace(' ', '', strtoupper($product->name))}}_license_status', 'valid');
        return true;
    }
    return false;
}
PHP;
@endphp
<pre style="color: #4b5563 dark:color:#d1d5db;"><code>{{ trim($activationCode) }}</code></pre>

// PHP 停用函数 (推荐在设置页取消授权时调用)
@php
$deactivationCode = <<<PHP
function deactivate_license() {
    \$license_key = get_option('{{ {str_replace(' ', '', strtoupper($product->name))}}_license_key');
    if (!\$license_key) return true;
    
    // 调用 LicenseOS API 停用它
    \$response = wp_remote_post(
        LICENSEOS_API_URL . '/api/v2/license/deactivate',
        [
            'body' => [
                'license_key' => \$license_key, 
                'domain' => \$_SERVER['HTTP_HOST']
            ],
            'headers' => [
                'X-API-KEY' => '$apiKey'
            ],
            'timeout' => 15
        ]
    );
    
    // 检查响应状态
    if (is_wp_error(\$response)) {
        error_log('Deactivate license failed: ' . \$response->get_error_message());
        return false;
    }
    
    \$body = json_decode(wp_remote_retrieve_body(\$response), true);
    if (\$body && \$body['status'] === 'success') {
        // 本地删除选项数据
        delete_option('{{ {str_replace(' ', '', strtoupper($product->name))}}_license_key');
        delete_option('{{ {str_replace(' ', '', strtoupper($product->name))}}_license_status');
        return true;
    }
    return false;
}
PHP;
@endphp
<pre style="color: #4b5563 dark:color:#d1d5db;"><code>{{ trim($deactivationCode) }}</code></pre>
