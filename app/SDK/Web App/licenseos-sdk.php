<?php
/**
 * LicenseOS Web App SDK v1.0（PHP Web App 通用适配，可扩展）
 *
 * 一键注入时由平台把下方 LICENSEOS-CONFIG 配置块填实，并在项目既有入口文件
 * （index.php / public/index.php 等）插入一行 require 钩子；不新建任何专用目录。
 *
 * 授权阶段界面（访问 /licenseos 路由，镜像 WP 插件/主题授权逻辑）：
 *   1) 未激活：提示输入授权证书激活；
 *   2) 已激活：显示授权域名、激活时间、到期时间；
 *   3) 快到期（warning_days 内）：剩余天数提示 + 续费按钮（直达平台产品详情页）；
 *   4) 已过期 / 无效 / 篡改：冻结提示 + 续费/购买按钮。
 *
 * 防删除/防破解：本文件自哈希 + 项目文件清单哈希上报平台 integrity/report，
 * 不一致即标记篡改并冻结授权；入口钩子含本文件缺失兜底上报。
 *
 * 扩展方式（不影响其它已接入项目）：
 *   $GLOBALS['licenseos_webapp_hooks']['ui_footer'] = function($cfg,$state){ echo '...'; };
 *   $GLOBALS['licenseos_webapp_hooks']['config'] = function($cfg){ $cfg['warning_days']=14; return $cfg; };
 */

// LICENSEOS-CONFIG-BEGIN
$LICENSEOS_CONFIG = [
    'api_url'      => '',
    'api_key'      => '',
    'slug'         => '',
    'product_url'  => '',
    'warning_days' => 7,
    'injected_at'  => '',
    'manifest'     => [],
];
// LICENSEOS-CONFIG-END

if (!function_exists('licenseos_webapp_boot')) {
    function licenseos_webapp_cfg(): array
    {
        global $LICENSEOS_CONFIG;
        $cfg = $LICENSEOS_CONFIG;
        if (isset($GLOBALS['licenseos_webapp_hooks']['config']) && is_callable($GLOBALS['licenseos_webapp_hooks']['config'])) {
            $cfg = call_user_func($GLOBALS['licenseos_webapp_hooks']['config'], $cfg);
        }
        return $cfg;
    }

    function licenseos_webapp_state_path(): string
    {
        return __DIR__ . '/.licenseos_state.json';
    }

    function licenseos_webapp_state(): array
    {
        $p = licenseos_webapp_state_path();
        $s = is_file($p) ? json_decode((string) file_get_contents($p), true) : null;
        return is_array($s) ? $s : [];
    }

    function licenseos_webapp_save_state(array $s): void
    {
        @file_put_contents(licenseos_webapp_state_path(), json_encode($s, JSON_UNESCAPED_UNICODE));
    }

    function licenseos_webapp_api(string $path, array $data): array
    {
        $cfg = licenseos_webapp_cfg();
        $ch = curl_init(rtrim($cfg['api_url'], '/') . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . $cfg['api_key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $r = json_decode((string) $body, true);
        return is_array($r) ? $r : ['status' => 'error', 'message' => 'network'];
    }

    /** 自哈希 + 清单哈希上报（每日限频）；缺失/不一致即篡改 */
    function licenseos_webapp_integrity(): void
    {
        $cfg = licenseos_webapp_cfg();
        if ($cfg['api_url'] === '' || $cfg['slug'] === '') return;
        $mark = sys_get_temp_dir() . '/licenseos_' . md5($cfg['slug']) . '_report';
        if (is_file($mark) && time() - (int) filemtime($mark) < 86400) return;
        @touch($mark);
        $codeHash = hash_file('sha256', __FILE__);
        foreach ((array) $cfg['manifest'] as $rel => $h) {
            $f = __DIR__ . '/' . $rel;
            if (!is_file($f) || hash_file('sha256', $f) !== $h) { $codeHash = 'manifest-mismatch'; break; }
        }
        $state = licenseos_webapp_state();
        licenseos_webapp_api('/integrity/report', [
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'code_hash' => $codeHash,
            'api_key_hash' => hash('sha256', $cfg['api_key']),
            'license_key' => $state['key'] ?? '',
            'product_slug' => $cfg['slug'],
        ]);
    }

    /** 阶段界面：/licenseos 路由全页渲染 */
    function licenseos_webapp_ui(): void
    {
        $cfg = licenseos_webapp_cfg();
        $state = licenseos_webapp_state();
        $msg = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['licenseos_action'] ?? '';
            if ($action === 'activate' && !empty($_POST['license_key'])) {
                $r = licenseos_webapp_api('/license/activate', [
                    'license_key' => trim($_POST['license_key']),
                    'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                    'product' => $cfg['slug'],
                ]);
                if (in_array($r['status'] ?? '', ['valid', 'active'], true)) {
                    $c = licenseos_webapp_api('/license/check', ['license_key' => trim($_POST['license_key'])]);
                    licenseos_webapp_save_state([
                        'key' => trim($_POST['license_key']),
                        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                        'activated_at' => date('c'),
                        'expires' => $c['expire_at'] ?? ($c['license']['valid_until'] ?? ''),
                    ]);
                    $state = licenseos_webapp_state();
                    $msg = '激活成功';
                } else {
                    $msg = '激活失败：' . ($r['message'] ?? ($r['error'] ?? '无效密钥'));
                }
            } elseif ($action === 'deactivate') {
                licenseos_webapp_api('/license/deactivate', ['license_key' => $state['key'] ?? '', 'domain' => $state['domain'] ?? '']);
                licenseos_webapp_save_state([]);
                $state = [];
                $msg = '已停用';
            }
        }
        $key = $state['key'] ?? '';
        $check = $key !== '' ? licenseos_webapp_api('/license/check', ['license_key' => $key, 'domain' => $state['domain'] ?? '']) : ['status' => 'inactive'];
        $status = $check['status'] ?? 'inactive';
        $expires = $state['expires'] ?? ($check['expire_at'] ?? '');
        $daysLeft = $expires !== '' ? (int) floor((strtotime($expires) - time()) / 86400) : null;
        $renew = htmlspecialchars($cfg['product_url'] ?: rtrim($cfg['api_url'], '/') . '/products/' . $cfg['slug'], ENT_QUOTES);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>授权管理</title><style>body{font-family:system-ui,sans-serif;background:#f5f6fa;margin:0;padding:24px}.card{max-width:640px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08)}h1{font-size:20px} .bar{padding:10px 14px;border-radius:8px;margin:12px 0;font-size:14px}.ok{background:#e8f5e9;color:#2e7d32}.warn{background:#fff3e0;color:#e65100}.bad{background:#ffebee;color:#c62828}.btn{display:inline-block;padding:8px 18px;border-radius:8px;background:#4f46e5;color:#fff;text-decoration:none;border:0;font-size:14px;cursor:pointer}.btn.ghost{background:#eee;color:#333}input[type=text]{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:14px}code{background:#f0f0f0;padding:2px 6px;border-radius:4px}</style></head><body><div class="card"><h1>授权管理 · ' . htmlspecialchars($cfg['slug']) . '</h1>';
        if ($msg !== '') echo '<div class="bar ok">' . htmlspecialchars($msg) . '</div>';
        if ($status === 'tampered' || $status === 'suspended') {
            echo '<div class="bar bad">检测到代码篡改或域名被平台暂停，授权已冻结。请恢复原始代码后联系平台管理员。</div>';
        } elseif ($key === '' || in_array($status, ['invalid', 'inactive', 'error'], true)) {
            echo '<div class="bar warn">本产品尚未激活或授权无效：请输入授权证书激活后使用全部功能。</div>';
            echo '<form method="post"><input type="hidden" name="licenseos_action" value="activate"><p><input type="text" name="license_key" placeholder="例如 SP03-XXXX-XXXX-XXXX" required></p><p><button class="btn" type="submit">激活授权</button> <a class="btn ghost" href="' . $renew . '" target="_blank">去购买/续费</a></p></form>';
        } elseif ($status === 'expired' || ($daysLeft !== null && $daysLeft < 0)) {
            echo '<div class="bar bad">授权已过期（' . htmlspecialchars(date('Y-m-d', strtotime($expires))) . '），新增写入已冻结。</div>';
            echo '<p><a class="btn" href="' . $renew . '" target="_blank">去续费/购买</a></p>';
        } else {
            echo '<div class="bar ok">授权有效 · 域名 <code>' . htmlspecialchars($state['domain'] ?? '') . '</code> · 激活于 ' . htmlspecialchars(date('Y-m-d', strtotime($state['activated_at'] ?? 'now'))) . ' · ' . ($expires !== '' ? '<code>' . htmlspecialchars(date('Y-m-d', strtotime($expires))) . ' 到期</code>' : '永久授权') . '</div>';
            if ($daysLeft !== null && $daysLeft <= (int) $cfg['warning_days']) {
                echo '<div class="bar warn">授权即将到期：仅剩 ' . $daysLeft . ' 天，请及时续费更换证书，避免功能冻结。</div><p><a class="btn" href="' . $renew . '" target="_blank">立即续费</a></p>';
            }
            echo '<form method="post" onsubmit="return confirm(\'确定停用许可证？\')"><input type="hidden" name="licenseos_action" value="deactivate"><button class="btn ghost" type="submit">停用</button></form>';
        }
        if (isset($GLOBALS['licenseos_webapp_hooks']['ui_footer']) && is_callable($GLOBALS['licenseos_webapp_hooks']['ui_footer'])) {
            call_user_func($GLOBALS['licenseos_webapp_hooks']['ui_footer'], $cfg, $state);
        }
        echo '</div></body></html>';
        exit;
    }

    function licenseos_webapp_boot(): void
    {
        $cfg = licenseos_webapp_cfg();
        if ($cfg['api_url'] === '') return; // 干净包未注入：不启用
        licenseos_webapp_integrity();
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (rtrim($uri, '/') === '/licenseos') licenseos_webapp_ui();
    }
}

licenseos_webapp_boot();