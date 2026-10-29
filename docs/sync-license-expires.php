#!/usr/bin/env php
<?php
/**
 * LicenseOS 许可证到期日期同步工具
 *
 * 功能：
 * 1. 查询平台 API 获取所有许可证的最新 expire_at
 * 2. 对比 WP 数据库中缓存的 _expires 字段
 * 3. 修复不一致的记录
 *
 * 用法：
 *   php sync-license-expires.php                      # 仅检查，输出对比报告
 *   php sync-license-expires.php --fix                 # 检查并修复不一致
 *   php sync-license-expires.php --fix --key=XXXX-XXXX # 只修复指定密钥
 *   php sync-license-expires.php --wp-config=/path/wp-config.php  # 指定 wp-config 路径
 *
 * 参数：
 *   --fix              启用修复模式（默认仅检查）
 *   --key=LICENSE_KEY  只处理指定的许可证密钥
 *   --wp-config=PATH   指定 wp-config.php 路径（默认自动查找）
 *   --api-url=URL      覆盖平台 API 地址（默认从 licenseos_mu_plugins 读取）
 *   --api-key=KEY      覆盖 API Key（默认从 licenseos_mu_plugins 读取）
 *   --quiet            静默模式，只输出错误和修复结果
 */

// ========== 颜色输出辅助 ==========
function out($msg, $color = '') {
    $colors = [
        'red'    => "\033[31m",
        'green'  => "\033[32m",
        'yellow' => "\033[33m",
        'blue'   => "\033[34m",
        'cyan'   => "\033[36m",
        'bold'   => "\033[1m",
        'reset'  => "\033[0m",
    ];
    $prefix = $color && isset($colors[$color]) ? $colors[$color] : '';
    $suffix = $prefix ? $colors['reset'] : '';
    echo $prefix . $msg . $suffix . PHP_EOL;
}

function out_ok($msg)   { out("  ✅ $msg", 'green'); }
function out_warn($msg)  { out("  ⚠️  $msg", 'yellow'); }
function out_err($msg)   { out("  ❌ $msg", 'red'); }
function out_info($msg)  { out("  ℹ️  $msg", 'cyan'); }

// ========== 参数解析 ==========
function parse_args($argv) {
    $args = [
        'fix'        => false,
        'key'        => '',
        'wp_config'  => '',
        'api_url'    => '',
        'api_key'    => '',
        'quiet'      => false,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--fix')        $args['fix'] = true;
        elseif ($arg === '--quiet')  $args['quiet'] = true;
        elseif (strpos($arg, '--key=') === 0)         $args['key'] = substr($arg, 6);
        elseif (strpos($arg, '--wp-config=') === 0)   $args['wp_config'] = substr($arg, 13);
        elseif (strpos($arg, '--api-url=') === 0)     $args['api_url'] = substr($arg, 10);
        elseif (strpos($arg, '--api-key=') === 0)     $args['api_key'] = substr($arg, 10);
    }
    return $args;
}

// ========== 查找 wp-config.php ==========
function find_wp_config($explicit_path = '') {
    if ($explicit_path && file_exists($explicit_path)) {
        return $explicit_path;
    }
    // 从当前目录向上查找
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        $candidate = $dir . '/wp-config.php';
        if (file_exists($candidate)) return $candidate;
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    return null;
}

// ========== 加载 WordPress（仅数据库） ==========
function load_wp_db($wp_config_path) {
    // 防止 WordPress 加载主题和插件
    define('SHORTINIT', true);

    require_once $wp_config_path;

    // 手动加载 wpdb
    require_once ABSPATH . WPINC . '/wp-db.php';
    $GLOBALS['wpdb'] = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $GLOBALS['wpdb']->set_prefix($table_prefix ?? 'wp_');

    return $GLOBALS['wpdb'];
}

// ========== 读取 WP option（直接 SQL，不依赖 WP 完整加载） ==========
function wp_option_get($wpdb, $name) {
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
        $name
    ));
    return $row ? maybe_unserialize($row->option_value) : false;
}

function wp_option_update($wpdb, $name, $value) {
    $existing = wp_option_get($wpdb, $name);
    if ($existing === false) {
        return $wpdb->insert($wpdb->options, [
            'option_name'  => $name,
            'option_value' => maybe_serialize($value),
            'autoload'     => 'yes',
        ]);
    }
    return $wpdb->update($wpdb->options,
        ['option_value' => maybe_serialize($value)],
        ['option_name' => $name]
    );
}

// ========== 简单 maybe_unserialize / maybe_serialize ==========
if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data) {
        if (is_serialized($data)) return @unserialize(trim($data));
        return $data;
    }
    function is_serialized($data) {
        if (!is_string($data)) return false;
        $data = trim($data);
        if ($data === 'N;') return true;
        if (preg_match('/^([adObis]):/', $data)) return true;
        return false;
    }
    function maybe_serialize($data) {
        if (is_array($data) || is_object($data)) return serialize($data);
        if (is_string($data) && is_serialized($data)) return serialize($data);
        return $data;
    }
}

// ========== 平台 API 调用 ==========
function licenseos_check($api_url, $api_key, $license_key) {
    $url = rtrim($api_url, '/') . '/license/check';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['license_key' => $license_key]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $api_key,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['_error' => $error, '_http_code' => 0];
    }

    $data = json_decode($response, true);
    if ($http_code >= 400) {
        return array_merge($data ?? [], ['_http_error' => true, '_http_code' => $http_code]);
    }

    return $data ?? [];
}

// ========== 主流程 ==========
function main($args) {
    out('');
    out('╔══════════════════════════════════════════════════╗', 'bold');
    out('║   LicenseOS 许可证到期日期同步工具              ║', 'bold');
    out('╚══════════════════════════════════════════════════╝', 'bold');
    out('');

    // Step 1: 加载 WordPress 数据库
    $wp_config = find_wp_config($args['wp_config']);
    if (!$wp_config) {
        out_err('找不到 wp-config.php，请用 --wp-config=/path/wp-config.php 指定路径');
        exit(1);
    }
    out_info('wp-config.php: ' . $wp_config);

    $wpdb = load_wp_db($wp_config);
    if (!$wpdb || $wpdb->last_error) {
        out_err('数据库连接失败: ' . ($wpdb->last_error ?? 'unknown'));
        exit(1);
    }
    out_ok('数据库连接成功');

    // Step 2: 获取 API 配置
    $api_url = $args['api_url'];
    $api_key = $args['api_key'];

    if (!$api_url || !$api_key) {
        // 从 licenseos_mu_plugins option 读取
        $mu_plugins = wp_option_get($wpdb, 'licenseos_mu_plugins');
        if (is_array($mu_plugins) && !empty($mu_plugins)) {
            $first = reset($mu_plugins);
            $api_url = $api_url ?: ($first['api'] ?? '');
            $api_key = $api_key ?: ($first['api_key'] ?? '');
        }
    }

    if (!$api_url || !$api_key) {
        out_err('无法获取 API 配置，请用 --api-url 和 --api-key 手动指定');
        out_info('示例: php sync-license-expires.php --api-url=https://sz.djkz.top/api/v2 --api-key=losk_xxx');
        exit(1);
    }
    out_info('API URL: ' . $api_url);
    out_info('API Key: ' . substr($api_key, 0, 12) . '****');

    // Step 3: 扫描 WP 数据库中的许可证
    out('');
    out('━━━ 扫描许可证 ━━━', 'bold');

    $like = '%_license_key';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value != ''",
        $like
    ));

    if (empty($rows)) {
        out_warn('未找到任何许可证密钥（option_name LIKE "%_license_key"）');
        exit(0);
    }

    // 解析许可证列表
    $licenses = [];
    foreach ($rows as $row) {
        $option_name = $row->option_name;
        $license_key = trim($row->option_value);

        // 提取 slug：xxx_license_key → xxx
        $slug = preg_replace('/_license_key$/', '', $option_name);
        if (!$slug) continue;

        // 如果指定了 --key，只处理匹配的
        if ($args['key'] && $args['key'] !== $license_key) continue;

        $status_key = $slug . '_license_status';
        $expires_key = $status_key . '_expires';

        $licenses[] = [
            'slug'        => $slug,
            'option_name' => $option_name,
            'license_key' => $license_key,
            'status_key'  => $status_key,
            'expires_key' => $expires_key,
            'wp_status'   => wp_option_get($wpdb, $status_key) ?: '(empty)',
            'wp_expires'  => wp_option_get($wpdb, $expires_key) ?: '(empty)',
        ];
    }

    if (empty($licenses)) {
        out_warn('没有匹配的许可证' . ($args['key'] ? " (筛选: {$args['key']})" : ''));
        exit(0);
    }

    out_info('找到 ' . count($licenses) . ' 个许可证');
    out('');

    // Step 4: 逐个查询平台 API 并对比
    $stats = ['total' => 0, 'match' => 0, 'mismatch' => 0, 'error' => 0, 'fixed' => 0];

    foreach ($licenses as $lic) {
        $stats['total']++;
        $key_masked = substr($lic['license_key'], 0, 8) . '****' . substr($lic['license_key'], -4);

        out("[$stats[total]] {$lic['slug']} — $key_masked", 'bold');

        // 查询平台
        $result = licenseos_check($api_url, $api_key, $lic['license_key']);

        if (isset($result['_error'])) {
            out_err("API 请求失败: {$result['_error']}");
            $stats['error']++;
            out('');
            continue;
        }

        if (isset($result['_http_error'])) {
            out_err("API 返回错误: HTTP {$result['_http_code']}");
            $stats['error']++;
            out('');
            continue;
        }

        $platform_status  = $result['status'] ?? '(unknown)';
        $platform_expires = $result['expire_at'] ?? '';

        // 格式化对比
        $wp_exp  = $lic['wp_expires'];
        $api_exp = $platform_expires;

        // 标准化日期对比（都转为 Y-m-d）
        $wp_exp_date  = ($wp_exp && $wp_exp !== '(empty)') ? date('Y-m-d', strtotime($wp_exp)) : '(empty)';
        $api_exp_date = $api_exp ? date('Y-m-d', strtotime($api_exp)) : '(none)';

        out_info("平台状态: $platform_status | expire_at: " . ($api_exp_date ?: '(none)'));
        out_info("WP 状态: {$lic['wp_status']} | _expires: $wp_exp_date");

        // 判断一致性
        $is_match = ($wp_exp_date === $api_exp_date);

        // 也检查状态一致性
        $status_match = true;
        if ($platform_status === 'valid' && $lic['wp_status'] !== 'valid') {
            $status_match = false;
        } elseif ($platform_status === 'expired' && $lic['wp_status'] !== 'expired' && $lic['wp_status'] !== 'invalid') {
            $status_match = false;
        }

        if ($is_match && $status_match) {
            out_ok('一致');
            $stats['match']++;
        } else {
            $stats['mismatch']++;
            if (!$is_match) {
                out_warn("到期日期不一致: WP=$wp_exp_date vs 平台=$api_exp_date");
            }
            if (!$status_match) {
                out_warn("状态不一致: WP={$lic['wp_status']} vs 平台=$platform_status");
            }

            // 修复
            if ($args['fix']) {
                $fix_count = 0;

                // 修复到期日期
                if (!$is_match && $api_exp) {
                    wp_option_update($wpdb, $lic['expires_key'], $api_exp);
                    out_ok("已修复 _expires: $wp_exp_date → $api_exp_date");
                    $fix_count++;
                }

                // 修复状态
                if (!$status_match) {
                    $new_status = ($platform_status === 'valid') ? 'valid' : 'invalid';
                    wp_option_update($wpdb, $lic['status_key'], $new_status);
                    out_ok("已修复 status: {$lic['wp_status']} → $new_status");
                    $fix_count++;
                }

                if ($fix_count > 0) {
                    $stats['fixed'] += $fix_count;
                }
            }
        }

        out('');
    }

    // Step 5: 汇总报告
    out('━━━ 汇总报告 ━━━', 'bold');
    out("  总计: {$stats['total']} 个许可证");
    out_ok("一致: {$stats['match']}");
    if ($stats['mismatch'] > 0) {
        out_warn("不一致: {$stats['mismatch']}");
    }
    if ($stats['error'] > 0) {
        out_err("API 错误: {$stats['error']}");
    }
    if ($args['fix'] && $stats['fixed'] > 0) {
        out_ok("已修复: {$stats['fixed']} 项");
    }
    if (!$args['fix'] && $stats['mismatch'] > 0) {
        out('');
        out_info('提示: 使用 --fix 参数自动修复不一致的记录');
    }
    out('');
}

// ========== 入口 ==========
$args = parse_args($argv ?? []);
main($args);
