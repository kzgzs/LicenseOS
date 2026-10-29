<?php
/**
 * LicenseOS WordPress SDK V3.3
 *
 * Usage in your WP plugin's main file:
 *
 *   require_once __DIR__ . '/licenseos-sdk.php';
 *   LicenseOS::init('https://your-licenseos-domain.com/api/v2', 'your-api-key');
 *   LicenseOS::enableAutoUpdate('your-plugin-slug', 'stored-license-key');
 *
 *   // License Gate — 许可证门槛（基于 get_plugin_page_hook + load-{hook}，不碰全局菜单数组）
 *   LicenseOS::enableLicenseGate([
 *       'slug'        => 'your-plugin-slug',
 *       'name'        => 'Your Plugin Name',
 *       'option_key'  => 'your_plugin_license_key',
 *       'status_key'  => 'your_plugin_license_status',
 *       'parent_slug' => 'options-general.php',  // 可选，默认 options-general.php
 *       'page_title'  => 'Your Plugin 设置',     // 可选
 *   ]);
 *
 * Public methods:
 *   LicenseOS::activate($key, $domain, $product)  — Activate a license
 *   LicenseOS::deactivate($key, $domain, $product) — Deactivate a license
 *   LicenseOS::check($key)                        — Check license status
 *   LicenseOS::checkUpdate($slug, $version)       — Check for updates
 *   LicenseOS::enableAutoUpdate($slug, $licenseKey) — WP auto-update integration
 *   LicenseOS::enableLicenseGate($config)          — License gate (基于 hook 拦截，不碰 $submenu/$menu)
 *   LicenseOS::enableThemeAutoUpdate($theme_slug)   — Theme auto-update integration
 *   LicenseOS::enableThemeLicenseGate($config)       — Theme license gate (admin_notices on themes.php)
 *
 * Tamper Protection:
 *   平台检测到篡改后，SDK 会弹出全屏警告弹窗并禁用管理员菜单，所有公共方法失效。
 */

if (!class_exists('LicenseOS')) {

    class LicenseOS
    {
        private static $api = '';
        private static $api_key = '';
        private static $license_keys = [];
        private static $update_cache = [];
        // 译文未就绪标记：API 返回成功但 localized_readme_sections 为空（zh_CN）
        private static $translation_pending = false;
        // 重写 update_plugins transient 为短缓存时的防递归标记
        private static $resaving_update_transient = false;
        private static $license_gates = [];
        private static $tampered = false;
        private static $base_dir = '';
        private static $pending_redirects = [];
        private static $plugin_details = [];

        // Theme support (V3.3+)
        private static $product_type = 'plugin';  // 'plugin' or 'theme'
        private static $theme_dir = '';            // Theme directory name (only for theme type)
        private static $slug = '';                 // Product slug (shared by plugin/theme methods)
        private static $license_gate_config = [];  // License gate config (shared by plugin/theme methods)

        public static function init($api_url, $api_key = '', $base_dir = '')
        {
            self::$api = rtrim($api_url, '/');
            self::$api_key = $api_key;
            self::$base_dir = $base_dir;

            // 检查 Inline Guard 的篡改标记
            if (defined('LICENSEOS_TAMPERED') && LICENSEOS_TAMPERED) {
                self::$tampered = true;
            }

            // 本地快速检查：API Key 是否完整（不读取持久化 option，只实时计算）
            if (empty(self::$api_key) || strlen(self::$api_key) < 10) {
                self::$tampered = true;
            }

            if (function_exists('add_action')) {
                add_action('admin_init', [self::class, 'integrityCheck']);
                add_action('admin_footer', [self::class, 'renderTamperPopup'], 1);
                add_action('admin_menu', [self::class, 'disableMenusOnTamper'], 999);
            }
        }

        /**
         * 发起非阻塞 HTTP POST 请求（fire-and-forget）。
         * 使用 curl 立即关闭连接，不等待响应。
         */
        private static function fireAndForget($url, $payload)
        {
            if (!function_exists('curl_init')) {
                return;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-API-KEY: ' . self::$api_key,
                ],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT_MS     => 1,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        public static function activate($key, $domain, $product)
        {
            if (self::$tampered) return ['status' => 'error', 'message' => 'tampered'];

            return self::request('/license/activate', [
                'license_key' => $key,
                'domain' => $domain,
                'product' => $product,
            ]);
        }

        public static function deactivate($key, $domain, $product)
        {
            if (self::$tampered) return ['status' => 'error', 'message' => 'tampered'];
            return self::request('/license/deactivate', [
                'license_key' => $key,
                'domain' => $domain,
                'product' => $product,
            ]);
        }

        public static function check($key)
        {
            if (self::$tampered) return ['status' => 'error', 'message' => 'tampered'];

            $result = self::request('/license/check', [
                'license_key' => $key,
            ]);

            // 处理请求失败（API Key 无效、被删除等情况）
            if (!empty($result['_http_error'])) {
                // 401/403 意味着 API Key 被删除或篡改
                if (isset($result['_http_code']) && in_array((int) $result['_http_code'], [401, 403])) {
                    self::$tampered = true;
                    return ['status' => 'tampered', 'message' => 'Unauthorized'];
                }
            }

            // 检查平台是否标记为篡改
            if (!empty($result['is_tampered']) || !empty($result['tamper_warning'])) {
                self::$tampered = true;
            }

            return $result;
        }

        /**
         * Check for product updates.
         */
        public static function checkUpdate($product_slug, $current_version = null)
        {
            if (self::$tampered) return null;

            $params = ['product' => $product_slug];
            if ($current_version) {
                $params['version'] = $current_version;
            }
            // Send WP site locale so the platform can return localized descriptions
            // Platform only supports en / zh_CN — map any zh_* locale to zh_CN
            $wp_locale = function_exists('get_locale') ? get_locale() : '';
            $params['locale'] = (strpos($wp_locale, 'zh') === 0) ? 'zh_CN' : 'en';
            // Include license key for authenticated update check
            if (isset(self::$license_keys[$product_slug])) {
                $params['license_key'] = self::$license_keys[$product_slug];
            }

            return self::requestGet('/update/check', $params);
        }

        /**
         * Enable WordPress auto-update integration.
         *
         * Call this in your plugin's main file after LicenseOS::init().
         *
         * @param string $plugin_slug   The plugin directory slug (e.g. 'my-seo-plugin')
         * @param string $license_key   The customer's license key (stored after activation)
         */
        public static function enableAutoUpdate($plugin_slug, $license_key = '', $product_slug = '', $plugin_file = '')
        {
            if (self::$tampered) return;
            if (!function_exists('add_filter')) {
                return;
            }

            if ($license_key) {
                self::$license_keys[$plugin_slug] = $license_key;
            }

            // 自动探测插件主文件路径
            if (empty($plugin_file)) {
                // 自动探测: 从 get_plugins() 扫描匹配 slug 前缀
                if (function_exists('get_plugins')) {
                    $all_plugins = get_plugins();
                    foreach ($all_plugins as $file => $data) {
                        if (strpos($file, $plugin_slug . '/') === 0) {
                            $plugin_file = $file;
                            break;
                        }
                    }
                }
                // 最终回退
                if (empty($plugin_file)) {
                    $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
                }
            }

            // === Hook 1: Inject update info into WP plugin list ===
            add_filter('pre_set_site_transient_update_plugins', function ($transient) use ($plugin_slug, $product_slug, $plugin_file) {
                // 短缓存重写 update_plugins 时会再次触发本过滤器，直接放行，避免重复请求 API
                if (self::$resaving_update_transient) {
                    return $transient;
                }

                $current_version = '';
                $source = '';

                if (!empty($transient->checked) && isset($transient->checked[$plugin_file])) {
                    $current_version = $transient->checked[$plugin_file];
                    $source = 'checked';
                } else {
                    // Fallback: 从 get_plugins() 获取版本号
                    $checked_empty = empty($transient->checked) ? 'empty' : 'has_but_no_plugin';
                    error_log('[LicenseOS Debug] transient fallback 触发: checked=' . $checked_empty . ', plugin=' . $plugin_file);
                    if (!function_exists('get_plugins')) {
                        require_once ABSPATH . 'wp-admin/includes/plugin.php';
                    }
                    $all_plugins = get_plugins();
                    if (isset($all_plugins[$plugin_file])) {
                        $current_version = $all_plugins[$plugin_file]['Version'] ?? '';
                        $source = 'get_plugins';
                    }
                }

                if (empty($current_version)) {
                    error_log('[LicenseOS Debug] 无法获取版本号, 放弃: ' . $plugin_file);
                    return $transient;
                }

                error_log('[LicenseOS Debug] transient filter: ' . $plugin_slug . ', version=' . $current_version . ', source=' . $source);

                $has_license = !empty(self::$license_keys[$plugin_slug]);

                // Build local icon URLs (shared by both branches)
                $plugin_url = plugins_url('', $plugin_file);
                $icons = [];
                foreach (['icon-256x256.png', 'icon-256x256.jpg'] as $file) {
                    if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug . '/assets/' . $file)) {
                        $icons['2x'] = $plugin_url . '/assets/' . $file;
                        break;
                    }
                }
                foreach (['icon-128x128.png', 'icon-128x128.jpg'] as $file) {
                    if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug . '/assets/' . $file)) {
                        $icons['1x'] = $plugin_url . '/assets/' . $file;
                        break;
                    }
                }

                if ($has_license) {
                    // Licensed: check remote API for updates
                    $update = self::checkUpdate($product_slug ?: $plugin_slug, $current_version);

                    // Cache the API response for Hook 2 (plugins_api) to avoid duplicate calls
                    if ($update) {
                        self::$update_cache[$plugin_slug] = $update;
                    }

                    // API 响应成功但译文未就绪（localized_readme_sections 为空）且站点为中文：
                    // 标记后由下方短缓存钩子（WP 6.8+ 为 set_site_transient，旧版为 setted_site_transient）
                    // 把 update_plugins 缓存缩短为 5 分钟，平台后台翻译完成后 SDK 自动重试即可拿到中文译文
                    try {
                        if ($update && isset($update['up_to_date']) && empty($update['localized_readme_sections'])) {
                            $site_locale = function_exists('get_locale') ? get_locale() : '';
                            if (strpos($site_locale, 'zh') === 0) {
                                self::$translation_pending = true;
                            }
                        }
                    } catch (\Throwable $e) {
                        // 标记失败不影响更新检查主流程
                    }

                    if ($update && isset($update['up_to_date']) && !$update['up_to_date']) {
                        // New version available — inject update info
                        $transient->response[$plugin_file] = (object) [
                            'slug' => $plugin_slug,
                            'plugin' => $plugin_file,
                            'new_version' => $update['latest_version'],
                            'url' => $update['product_url'] ?? '',
                            'package' => $update['download_url'] ?? '',
                            'tested' => $update['tested_up_to'] ?? '',
                            'requires_php' => $update['requires_php'] ?? '',
                            'icons' => !empty($icons) ? $icons : null,
                        ];
                    } elseif ($update) {
                        // Up to date — inject into no_update so WP renders "View Details"
                        $transient->no_update[$plugin_file] = (object) [
                            'slug' => $plugin_slug,
                            'plugin' => $plugin_file,
                            'new_version' => $update['latest_version'] ?? $current_version,
                            'url' => $update['product_url'] ?? '',
                            'package' => $update['download_url'] ?? '',
                            'tested' => $update['tested_up_to'] ?? '',
                            'requires_php' => $update['requires_php'] ?? '',
                            'icons' => !empty($icons) ? $icons : null,
                        ];
                    } else {
                        // API 调用失败或返回异常：回退到本地数据，确保 no_update 存在
                        $transient->no_update[$plugin_file] = (object) [
                            'slug' => $plugin_slug,
                            'plugin' => $plugin_file,
                            'new_version' => $current_version,
                            'url' => '',
                            'package' => '',
                            'tested' => '',
                            'requires_php' => '',
                            'icons' => !empty($icons) ? $icons : null,
                        ];
                    }
                } else {
                    // No license: still inject basic no_update data so "View Details" works
                    $transient->no_update[$plugin_file] = (object) [
                        'slug' => $plugin_slug,
                        'plugin' => $plugin_file,
                        'new_version' => $current_version,
                        'url' => '',
                        'package' => '',
                        'tested' => '',
                        'requires_php' => '',
                        'icons' => !empty($icons) ? $icons : null,
                    ];
                }

                return $transient;
            });

            // === Hook 1.5: 译文未就绪时把 update_plugins transient 缩短为 5 分钟 ===
            // WP 默认 update_plugins 缓存约 12 小时；译文就绪前改为 5 分钟，
            // 平台后台翻译落库后 SDK 最多 5 分钟自动重试并拿到中文译文（随后恢复长缓存）
            $update_transient_resave = function ($transient_name, $value = null, $expiration = 0) {
                try {
                    // 只处理 update_plugins，其他 transient 一律不碰
                    if ($transient_name !== 'update_plugins') return;
                    if (!self::$translation_pending || self::$resaving_update_transient) return;
                    // 健壮性校验：$value 必须是非空对象/数组才允许重写，
                    // 任何不确定情况直接 return，绝不用空值/错误值覆盖 update_plugins
                    if (!is_object($value) && !(is_array($value) && count($value) > 0)) return;
                    if (empty((array) $value)) return;
                    self::$resaving_update_transient = true;
                    self::$translation_pending = false;
                    try {
                        if (function_exists('set_site_transient')) {
                            // 原样写回回调参数传入的真实 $value：
                            // response / no_update / checked / last_checked 等所有键原样保留，只缩短过期时间
                            set_site_transient('update_plugins', $value, 5 * MINUTE_IN_SECONDS);
                        }
                    } finally {
                        // 无论成功失败都复位防递归标记，
                        // 防止同一请求内后续 update_plugins 写入被 Hook 1 守卫跳过注入
                        self::$resaving_update_transient = false;
                    }
                } catch (\Throwable $e) {
                    // 兜底：任何异常都回退为 WP 默认缓存时长，绝不影响站点运行
                    self::$resaving_update_transient = false;
                }
            };

            // 钩子版本兼容：WP 6.8 起 setted_site_transient 已废弃（触发 doing_it_wrong 警告），
            // 新钩子名为 set_site_transient，两者回调参数签名一致（$transient, $value, $expiration）
            $wp_version_str = isset($GLOBALS['wp_version']) ? $GLOBALS['wp_version'] : '';
            if ($wp_version_str === '' && function_exists('get_bloginfo')) {
                $wp_version_str = get_bloginfo('version');
            }
            $resave_hook = version_compare($wp_version_str, '6.8', '>=') ? 'set_site_transient' : 'setted_site_transient';
            add_action($resave_hook, $update_transient_resave, 10, 3);

            // === Hook 2: Plugin details popup (View version x.x.x details) ===
            add_filter('plugins_api', function ($result, $action, $args) use ($plugin_slug, $product_slug) {
                if ($action !== 'plugin_information') {
                    return $result;
                }
                // Match product slug OR menu slug — 只匹配当前插件，不遍历全局 gates，防止多插件串显
                $slug_matched = (isset($args->slug) && $args->slug === $plugin_slug);
                if (!$slug_matched && isset($args->slug)) {
                    // 只检查当前闭包捕获的 product_slug，不遍历全局 license_gates
                    if ($product_slug !== '' && $product_slug === $args->slug) {
                        $slug_matched = true;
                    }
                }
                if (!isset($args->slug) || !$slug_matched) {
                    return $result;
                }

                $has_license = !empty(self::$license_keys[$plugin_slug]);

                // Try to get API data (cached or fresh) — only meaningful with license
                $update = null;
                if ($has_license) {
                    $update = self::$update_cache[$plugin_slug] ?? self::checkUpdate($product_slug ?: $plugin_slug);
                    // Validate: if $update is a non-null error response, discard it
                    if ($update && !isset($update['latest_version']) && !isset($update['current_version']) && !isset($update['up_to_date'])) {
                        $update = null;
                    }
                }

                // Parse readme.txt from plugin directory (local content source)
                $readme = self::parseReadme($plugin_slug);

                if ($update) {
                    // === Licensed path: use API data + local readme.txt content ===
                    // Fall back to current_version when latest_version is absent (up-to-date case)
                    $version = $update['latest_version'] ?? $update['current_version'] ?? '';
                    if (!$version) {
                        return $result;
                    }

                    $sections = [
                        'description'  => trim(self::readmeToHtml($readme['description'] ?: ($update['short_description'] ?? ''))),
                        'installation' => trim(self::readmeToHtml($readme['installation'] ?? '')),
                        'changelog'    => trim(self::readmeToHtml($readme['changelog'] ?: ($update['changelog'] ?? ''))),
                        'screenshots'  => trim(self::buildScreenshotsFromReadme($readme['screenshots_text'] ?? '', $plugin_slug)),
                        'faq'          => trim(self::readmeToHtml($readme['faq'] ?? '')),
                    ];
                    // Platform-localized readme sections (description/installation/faq/changelog,
                    // matching the WP site locale) override local readme content
                    if (!empty($update['localized_readme_sections']) && is_array($update['localized_readme_sections'])) {
                        foreach (['description', 'installation', 'faq', 'changelog'] as $section_key) {
                            $translated_section = $update['localized_readme_sections'][$section_key] ?? '';
                            if (is_string($translated_section) && trim($translated_section) !== ''
                                && (isset($sections[$section_key]) || trim($translated_section) !== '')
                            ) {
                                $sections[$section_key] = trim(self::readmeToHtml($translated_section));
                            }
                        }
                    }
                    // Platform-localized description (matches WP site locale) overrides local readme
                    if (!empty($update['localized_description'])) {
                        $sections['description'] = trim(self::readmeToHtml($update['localized_description']));
                    }
                    if (isset($sections['short_description']) && !empty($update['localized_short_description'])) {
                        $sections['short_description'] = trim(self::readmeToHtml($update['localized_short_description']));
                    }
                    $reviewsHtml = trim(self::buildReviewsHtml($update));
                    if ($reviewsHtml !== '') {
                        $sections['reviews'] = $reviewsHtml;
                    }

                    $info = (object) [
                        'name' => $update['product_name'] ?? $plugin_slug,
                        'slug' => $plugin_slug,
                        'version' => $version,
                        'requires' => $update['requires_wp'] ?? '5.0',
                        'tested' => $update['tested_up_to'] ?? '',
                        'requires_php' => $update['requires_php'] ?? '7.4',
                        'active_installs' => $update['active_installs'] ?? 0,
                        'downloaded' => $update['downloaded'] ?? 0,
                        'last_updated' => $update['last_updated'] ?? '',
                        'added' => $update['added'] ?? '',
                        // readme.txt is the source of truth for content; fallback to API data
                        'sections' => $sections,
                        'download_link' => $update['download_url'] ?? '',
                        'homepage' => $update['product_url'] ?? '',
                        'short_description' => $readme['short_description'] ?: ($update['short_description'] ?? ''),
                    ];

                    // Platform-localized short description overrides local readme when available
                    if (!empty($update['localized_short_description'])) {
                        $info->short_description = $update['localized_short_description'];
                    }

                    // Rating & ratings distribution (WordPress renders these in the right sidebar)
                    $info->rating = $update['rating'] ?? 0;           // 0-100 scale
                    $info->num_ratings = $update['num_ratings'] ?? ($update['review_count'] ?? 0);

                    // Build ratings distribution from reviews so WP creates the "reviews" tab
                    $ratingDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                    foreach (($update['reviews'] ?? []) as $r) {
                        $star = (int)($r['rating'] ?? 0);
                        if ($star >= 1 && $star <= 5) $ratingDist[$star]++;
                    }
                    $info->ratings = $ratingDist;

                    // Author & compatibility info (from API)
                    $info->author = $update['author'] ?? '';
                    $info->author_profile = $update['author_profile'] ?? '';
                    $info->homepage = $update['plugin_homepage'] ?? ($info->homepage ?? '');
                } else {
                    // === No license / no API data: don't intercept — let WordPress handle it ===
                    error_log('[LicenseOS Debug] no license or no API data for ' . $plugin_slug . ', returning default plugins_api result');
                    return $result;
                }

                // Use local banner/icon files from plugin's assets/ directory
                $plugin_url = plugins_url('', $plugin_slug . '/' . $plugin_slug . '.php');

                // Check for local banner files (WordPress standard naming)
                $local_banners = [];
                foreach (['banner-1544x500.png', 'banner-1544x500.jpg', 'banner-1544x500.jpeg'] as $file) {
                    $path = WP_PLUGIN_DIR . '/' . $plugin_slug . '/assets/' . $file;
                    if (file_exists($path)) {
                        $local_banners['high'] = $plugin_url . '/assets/' . $file;
                        break;
                    }
                }
                foreach (['banner-772x250.png', 'banner-772x250.jpg', 'banner-772x250.jpeg'] as $file) {
                    $path = WP_PLUGIN_DIR . '/' . $plugin_slug . '/assets/' . $file;
                    if (file_exists($path)) {
                        $local_banners['low'] = $plugin_url . '/assets/' . $file;
                        break;
                    }
                }
                if (!empty($local_banners)) {
                    $info->banners = $local_banners;
                }

                // Check for local icon files (WordPress standard naming)
                $local_icons = [];
                foreach (['icon-256x256.png', 'icon-256x256.jpg'] as $file) {
                    $path = WP_PLUGIN_DIR . '/' . $plugin_slug . '/assets/' . $file;
                    if (file_exists($path)) {
                        $local_icons['2x'] = $plugin_url . '/assets/' . $file;
                        break;
                    }
                }
                foreach (['icon-128x128.png', 'icon-128x128.jpg'] as $file) {
                    $path = WP_PLUGIN_DIR . '/' . $plugin_slug . '/assets/' . $file;
                    if (file_exists($path)) {
                        $local_icons['1x'] = $plugin_url . '/assets/' . $file;
                        $local_icons['default'] = $plugin_url . '/assets/' . $file;
                        break;
                    }
                }
                if (!empty($local_icons)) {
                    $info->icons = $local_icons;
                }

                // Cache details for admin_head callback (JS/CSS injection)
                self::$plugin_details[$plugin_slug] = $info;

                return $info;
            }, 10, 3);

            // === Hook 3: CSS for review cards + JS to clean up WP.org text ===
            add_action('admin_head', function() {
                if (empty($_GET['tab']) || $_GET['tab'] !== 'plugin-information') return;
                $apiBase = self::$api;
                if (empty($apiBase)) return;
                $parsed = parse_url($apiBase);
                $platformUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                if (empty($parsed['host'])) return;
                $cached_details = self::$plugin_details[$plugin_slug] ?? null;
                $product_url = '';
                if ($cached_details && isset($cached_details->homepage)) {
                    $product_url = $cached_details->homepage;
                }
                ?>
<style>
/* Remove blank space from empty/whitespace paragraphs */
[id^="section-"] p:empty{display:none!important;height:0!important;margin:0!important;padding:0!important;line-height:0!important}
[id^="section-"] p{margin-top:0}
[id^="section-"] > p:first-child{margin-top:0!important}
/* Fix h3 default margin-top causing blank space at section top */
[id^="section-"] > h3:first-child,[id^="section-"] > h4:first-child,[id^="section-"] > ul:first-child,[id^="section-"] > ol:first-child{margin-top:0!important}
.licenseos-review-card{border:1px solid #ddd;border-radius:4px;padding:12px 16px;margin:12px 0;background:#fff}
.licenseos-review-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px}
.licenseos-reviewer{font-size:14px}
.licenseos-review-stars{color:#ffb900;font-size:16px;letter-spacing:1px}
.licenseos-review-date{color:#999;font-size:12px;margin-left:auto}
.licenseos-review-text{color:#333;font-size:13px;line-height:1.6;margin:6px 0 0}
.licenseos-stars{color:#ffb900;font-size:18px;letter-spacing:2px}
.review-count{color:#666;font-size:13px;margin-top:2px}
/* Tighten review summary spacing */
.plugin-reviews h3{margin:0 0 4px!important;font-size:20px!important}
.plugin-reviews .review-star-display{margin:0 0 2px!important}
.plugin-reviews .review-count{margin:0 0 12px!important}
/* Hide WP.org review text paragraph entirely */
#section-reviews p a[href*="wordpress.org"]{display:none!important}
#section-reviews p{font-size:0;line-height:0;margin:0;padding:0;height:0;overflow:hidden}
#section-reviews .plugin-reviews p{font-size:inherit;line-height:inherit;margin:inherit;padding:inherit;height:auto;overflow:visible}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
var dn=<?php echo json_encode($parsed['host'] ?? ''); ?>;
var slug=<?php echo json_encode($_GET['plugin'] ?? ''); ?>;
var purl=<?php echo json_encode($product_url); ?>;
if(!purl&&dn&&slug){purl=window.location.protocol+'//'+dn+'/products/'+slug;}

function cleanup(){
/* 1. Remove ALL empty p tags from every section container */
var allSecs=document.querySelectorAll('[id^="section-"]');
for(var s=0;s<allSecs.length;s++){
var ps=allSecs[s].getElementsByTagName('p');
for(var i=ps.length-1;i>=0;i--){
if(!ps[i].textContent.trim()&&!ps[i].querySelector('img')){ps[i].parentNode.removeChild(ps[i])}
}
}

/* 2. Remove WP.org links from #section-reviews */
var sr=document.getElementById('section-reviews');
if(sr){var lks=sr.getElementsByTagName('a');for(var i=lks.length-1;i>=0;i--){if((lks[i].href||'').indexOf('wordpress.org')>-1){var p=lks[i].parentNode;if(p&&p.tagName==='P'){p.parentNode.removeChild(p)}else{lks[i].parentNode.removeChild(lks[i])}}}}

/* 3. Replace ALL wordpress.org star/support links with platform product URL */
var allLinks=document.getElementsByTagName('a');
for(var k=allLinks.length-1;k>=0;k--){
var h=allLinks[k].href||'';
if(h.indexOf('wordpress.org')>-1){
if(h.indexOf('/support/plugin')>-1||h.indexOf('/reviews')>-1){
if(purl){allLinks[k].href=purl+'#reviews';allLinks[k].target='_blank';}
}else{
if(dn){allLinks[k].href=window.location.protocol+'//'+dn;allLinks[k].target='_blank';}
}
}
}

/* 4. Replace "WordPress.org" with domain name in all text nodes */
if(dn){
var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,null,false);
var nd;
while(nd=walker.nextNode()){
if(nd.nodeValue.indexOf('WordPress.org')!==-1){nd.nodeValue=nd.nodeValue.replace(/WordPress\.org/g,dn)}
}
}
}

cleanup();
setTimeout(cleanup,200);
setTimeout(cleanup,500);
setTimeout(cleanup,1000);
setTimeout(cleanup,2000);
});
</script>
                <?php
            });

        }

        /**
         * 启用许可证门槛（License Gate）
         *
         * 顶级菜单通过 load-{hook} action 拦截页面渲染。
         * 子菜单通过 remove_submenu_page + add_submenu_page 重新注册，
         * 用包含门槛逻辑的 Closure 作为回调，不直接修改 $submenu 数组元素。
         *
         * 未激活 → 只显示许可证激活表单，插件设置不可见
         * 已激活 → 顶部显示授权信息条 + 正常显示插件设置
         *
         * @param array $config Gate configuration
         */
        public static function enableLicenseGate($config)
        {
            $slug = $config['slug'];
            self::$license_gates[$slug] = $config;

            add_action('admin_menu', function () use ($slug, $config) {
                global $menu, $submenu;

                // === 1. 顶级菜单：用 load-{hook} 拦截（hook 格式固定为 toplevel_page_{slug}） ===
                $is_top_level = false;
                if (is_array($menu)) {
                    foreach ($menu as $item) {
                        if (isset($item[2]) && $item[2] === $slug) {
                            $is_top_level = true;
                            break;
                        }
                    }
                }

                if ($is_top_level) {
                    $top_hook = 'toplevel_page_' . $slug;
                    error_log('[LicenseOS Debug] 顶级菜单: ' . $slug . ', hook=' . $top_hook);
                    add_action('load-' . $top_hook, function () use ($slug, $config, $top_hook) {
                        self::applyLicenseGateLogic($top_hook, $slug, $config);
                    });
                }

                // === 2. 子菜单：remove_submenu_page + add_submenu_page 重新注册 ===
                if (isset($submenu[$slug]) && is_array($submenu[$slug])) {
                    $items = $submenu[$slug];
                    foreach ($items as $item) {
                        $sub_slug        = $item[2] ?? '';
                        $menu_title      = $item[0] ?? '';
                        $capability      = $item[1] ?? 'manage_options';
                        $original_callback = $item[3] ?? null;

                        if (empty($sub_slug)) continue;

                        // 镜像条目：注册 load-{hook} 拦截，与顶级菜单保持一致
                        if ($sub_slug === $slug) {
                            if (function_exists('get_plugin_page_hookname')) {
                                $mirror_hook = get_plugin_page_hookname($sub_slug, $slug);
                            } else {
                                $mirror_hook = '';
                            }
                            error_log('[LicenseOS Debug] 镜像子菜单: ' . $sub_slug . ', hook=' . $mirror_hook . ', top=toplevel_page_' . $slug);
                            if (!empty($mirror_hook) && $mirror_hook !== ('toplevel_page_' . $slug)) {
                                error_log('[LicenseOS Debug] 镜像注册 load-' . $mirror_hook . ' → license gate 拦截');
                                add_action('load-' . $mirror_hook, function () use ($mirror_hook, $slug, $config) {
                                    self::applyLicenseGateLogic($mirror_hook, $slug, $config);
                                });
                            }
                            continue;
                        }

                        // 仅处理字符串回调
                        if (!is_string($original_callback)) {
                            error_log('[LicenseOS Debug] 子菜单跳过: ' . $sub_slug . ', 回调类型=' . gettype($original_callback));
                            continue;
                        }

                        // 计算 hook suffix
                        if (function_exists('get_plugin_page_hookname')) {
                            $sub_hook = get_plugin_page_hookname($sub_slug, $slug);
                        } else {
                            $sub_hook = $slug . '_page_' . $sub_slug;
                        }
                        error_log('[LicenseOS Debug] 子菜单: ' . $sub_slug . ', callback=' . $original_callback . ', hook=' . $sub_hook . ' → remove+add 包裹');

                        // 移除原始子菜单
                        remove_submenu_page($slug, $sub_slug);

                        // 重新注册，回调为包含门槛逻辑的 Closure
                        add_submenu_page(
                            $slug,
                            $menu_title,  // page_title
                            $menu_title,  // menu_title
                            $capability,
                            $sub_slug,
                            function () use ($original_callback, $config, $slug) {
                                // 篡改检测
                                if (self::$tampered) {
                                    self::renderTamperPopup();
                                    self::disableMenusOnTamper($slug);
                                    return;
                                }

                                $status_key = $config['status_key'] ?? $slug . '_license_status';
                                $status = get_option($status_key, '');

                                if ($status !== 'valid') {
                                    // 未激活：只显示激活表单
                                    self::renderLicenseGateForm($config);
                                } else {
                                    // 已激活：调用原始回调（信息条由 admin_notices 渲染，见 section 3）
                                    if (is_callable($original_callback)) {
                                        call_user_func($original_callback);
                                    }
                                }
                            }
                        );
                    }
                }

                // === 3. 在插件管理页面通过 admin_notices 渲染信息条 ===
                // admin_menu 在 admin_notices 之前触发，此处注册不会错过
                // 获取当前页面
                $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

                // 获取 SDK 初始化时传入的 admin_page（未传则回退为 slug，slug 即菜单 page 参数）
                $admin_page = $config['admin_page'] ?? $slug;

                // 只有在对应插件页面才显示授权横幅，避免在其他插件页面串显
                if ($current_page !== $admin_page) {
                    $page_matches = false;
                } else {
                    $page_matches = true;
                }

                if ($page_matches) {
                    add_action('admin_notices', function() use ($config, $slug) {
                        $status_key = $config['status_key'] ?? $slug . '_license_status';
                        $status = get_option($status_key, '');
                        if ($status === 'valid' && !self::$tampered) {
                            self::renderLicenseInfoBar($config);
                        }
                    });
                }
            }, 999);

            // 处理激活/停用表单提交
            add_action('admin_init', function () use ($config) {
                self::handleLicenseGateActions($config);
            });

            // 延迟执行 redirect，确保其他插件的 admin_init 回调不被跳过
            add_action('admin_init', [self::class, 'flushPendingRedirects'], 999999);

            // === 4. 全局保护：未授权时用 CSS 隐藏设置内容 + 在顶部显示激活表单 ===
            // 作为 remove_submenu_page + add_submenu_page 的补充保护层，
            // 覆盖非字符串回调等无法被子菜单重注册拦截的情况
            add_action('admin_head', function() use ($slug, $config) {
                if (self::$tampered) return;
                $status_key = $config['status_key'] ?? $slug . '_license_status';
                $status = get_option($status_key, '');
                if ($status === 'valid') return;

                $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
                if (empty($page) || strpos($page, $slug) === false) return;
                ?>
                <style id="licenseos-gate-css-<?php echo esc_attr($slug); ?>">
                #wpbody-content > *:not(.licenseos-gate-overlay) { display:none!important; }
                .licenseos-gate-overlay { display:block!important; }
                </style>
                <?php
            }, 1);

            add_action('admin_notices', function() use ($slug, $config) {
                if (self::$tampered) return;
                $status_key = $config['status_key'] ?? $slug . '_license_status';
                $status = get_option($status_key, '');
                if ($status === 'valid') return;

                // 未授权激活界面同样只在对应插件页面显示
                $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
                $admin_page = $config['admin_page'] ?? $slug;
                if ($page !== $admin_page) return;

                echo '<div class="licenseos-gate-overlay">';
                self::renderLicenseGateForm($config);
                echo '</div>';
            }, 1); // 优先级 1，确保在其他 admin_notices 之前
        }

        /**
         * Enable WordPress auto-update integration for Themes.
         *
         * Call this in your theme's functions.php after LicenseOS::init().
         *
         * @param string $theme_slug   The theme directory slug
         * @param string $license_key  The customer's license key (stored after activation)
         */
        public static function enableThemeAutoUpdate($theme_slug, $license_key = '')
        {
            self::$product_type = 'theme';
            self::$theme_dir = $theme_slug;
            self::$slug = $theme_slug;

            if ($license_key) {
                self::$license_keys[$theme_slug] = $license_key;
            }

            // === Hook: Theme update transient ===
            add_filter('pre_set_site_transient_update_themes', function ($transient) use ($theme_slug) {
                if (empty($transient->checked) || !isset($transient->checked[$theme_slug . '/style.css'])) {
                    return $transient;
                }

                $theme = wp_get_theme($theme_slug);
                $current_version = $theme->get('Version');
                if (empty($current_version)) {
                    return $transient;
                }

                $has_license = !empty(self::$license_keys[$theme_slug]);
                if (!$has_license) {
                    return $transient;
                }

                $update = self::checkUpdate($theme_slug, $current_version);
                if ($update && isset($update['up_to_date']) && !$update['up_to_date']) {
                    $transient->response[$theme_slug] = [
                        'theme'       => $theme_slug,
                        'new_version' => $update['latest_version'] ?? '',
                        'url'         => $update['product_url'] ?? '',
                        'package'     => $update['download_url'] ?? '',
                    ];
                }

                return $transient;
            });

            // === Hook 2: Theme details popup (View version x.x.x details) ===
            add_filter('themes_api', function ($result, $action, $args) use ($theme_slug) {
                if ($action !== 'theme_information') {
                    return $result;
                }
                if (($args->slug ?? '') !== $theme_slug) {
                    return $result;
                }

                $has_license = !empty(self::$license_keys[$theme_slug]);
                if (!$has_license) {
                    return $result;
                }

                // Try to get API data (cached or fresh)
                $update = self::$update_cache[$theme_slug] ?? self::checkUpdate($theme_slug);
                // Validate: if $update is a non-null error response, discard it
                if ($update && !isset($update['latest_version']) && !isset($update['current_version']) && !isset($update['up_to_date'])) {
                    $update = null;
                }
                if (!$update) {
                    return $result;
                }

                // Fall back to current_version when latest_version is absent (up-to-date case)
                $version = $update['latest_version'] ?? $update['current_version'] ?? '';
                if (!$version) {
                    return $result;
                }

                // Parse readme.txt from theme directory (local content source)
                $readme = self::parseThemeReadme($theme_slug);

                $info = (object) [
                    'name'          => $update['product_name'] ?? $theme_slug,
                    'slug'          => $theme_slug,
                    'version'       => $version,
                    'requires'      => $update['requires_wp'] ?? '5.0',
                    'tested'        => $update['tested_up_to'] ?? '',
                    'requires_php'  => $update['requires_php'] ?? '7.4',
                    'last_updated'  => $update['last_updated'] ?? '',
                    'downloaded'    => $update['downloaded'] ?? 0,
                    'sections'      => [
                        'description'  => trim(self::readmeToHtml($readme['description'] ?: ($update['short_description'] ?? ''))),
                        'installation' => trim(self::readmeToHtml($readme['installation'] ?? '')),
                        'changelog'    => trim(self::readmeToHtml($readme['changelog'] ?: ($update['changelog'] ?? ''))),
                        'screenshots'  => trim(self::buildThemeScreenshotsFromReadme($readme['screenshots_text'] ?? '', $theme_slug)),
                        'faq'          => trim(self::readmeToHtml($readme['faq'] ?? '')),
                        'reviews'      => trim(self::buildReviewsHtml($update)),
                    ],
                    'download_link' => $update['download_url'] ?? '',
                    'homepage'      => $update['product_url'] ?? '',
                    'short_description' => $readme['short_description'] ?: ($update['short_description'] ?? ''),
                ];

                // Rating & ratings distribution
                $info->rating = $update['rating'] ?? 0;
                $info->num_ratings = $update['num_ratings'] ?? ($update['review_count'] ?? 0);

                $ratingDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                foreach (($update['reviews'] ?? []) as $r) {
                    $star = (int)($r['rating'] ?? 0);
                    if ($star >= 1 && $star <= 5) $ratingDist[$star]++;
                }
                $info->ratings = $ratingDist;

                // Author info
                $info->author = $update['author'] ?? '';
                $info->author_profile = $update['author_profile'] ?? '';

                return $info;
            }, 10, 3);

            // === Hook 3: CSS for theme details popup review cards + JS to clean up WP.org text ===
            add_action('admin_head', function() use ($theme_slug) {
                if (empty($_GET['tab']) || $_GET['tab'] !== 'theme-information') return;
                $apiBase = self::$api;
                if (empty($apiBase)) return;
                $parsed = parse_url($apiBase);
                $platformUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                if (empty($parsed['host'])) return;
                ?>
<style>
/* Remove blank space from empty/whitespace paragraphs */
[id^="section-"] p:empty{display:none!important;height:0!important;margin:0!important;padding:0!important;line-height:0!important}
[id^="section-"] p{margin-top:0}
[id^="section-"] > p:first-child{margin-top:0!important}
[id^="section-"] > h3:first-child,[id^="section-"] > h4:first-child,[id^="section-"] > ul:first-child,[id^="section-"] > ol:first-child{margin-top:0!important}
.licenseos-review-card{border:1px solid #ddd;border-radius:4px;padding:12px 16px;margin:12px 0;background:#fff}
.licenseos-review-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px}
.licenseos-reviewer{font-size:14px}
.licenseos-review-stars{color:#ffb900;font-size:16px;letter-spacing:1px}
.licenseos-review-date{color:#999;font-size:12px;margin-left:auto}
.licenseos-review-text{color:#333;font-size:13px;line-height:1.6;margin:6px 0 0}
.licenseos-stars{color:#ffb900;font-size:18px;letter-spacing:2px}
.review-count{color:#666;font-size:13px;margin-top:2px}
.plugin-reviews h3{margin:0 0 4px!important;font-size:20px!important}
.plugin-reviews .review-star-display{margin:0 0 2px!important}
.plugin-reviews .review-count{margin:0 0 12px!important}
#section-reviews p a[href*="wordpress.org"]{display:none!important}
#section-reviews p{font-size:0;line-height:0;margin:0;padding:0;height:0;overflow:hidden}
#section-reviews .plugin-reviews p{font-size:inherit;line-height:inherit;margin:inherit;padding:inherit;height:auto;overflow:visible}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
var dn=<?php echo json_encode($parsed['host'] ?? ''); ?>;
var slug=<?php echo json_encode($theme_slug); ?>;
var purl=<?php echo json_encode($platformUrl ? $platformUrl . '/products/' . $theme_slug : ''); ?>;

function cleanup(){
var allSecs=document.querySelectorAll('[id^="section-"]');
for(var s=0;s<allSecs.length;s++){
var ps=allSecs[s].getElementsByTagName('p');
for(var i=ps.length-1;i>=0;i--){
if(!ps[i].textContent.trim()&&!ps[i].querySelector('img')){ps[i].parentNode.removeChild(ps[i])}
}
}
var sr=document.getElementById('section-reviews');
if(sr){var lks=sr.getElementsByTagName('a');for(var i=lks.length-1;i>=0;i--){if((lks[i].href||'').indexOf('wordpress.org')>-1){var p=lks[i].parentNode;if(p&&p.tagName==='P'){p.parentNode.removeChild(p)}else{lks[i].parentNode.removeChild(lks[i])}}}}
var allLinks=document.getElementsByTagName('a');
for(var k=allLinks.length-1;k>=0;k--){
var h=allLinks[k].href||'';
if(h.indexOf('wordpress.org')>-1){
if(h.indexOf('/support/')>-1||h.indexOf('/reviews')>-1){
if(purl){allLinks[k].href=purl+'#reviews';allLinks[k].target='_blank';}
}else{
if(dn){allLinks[k].href=window.location.protocol+'//'+dn;allLinks[k].target='_blank';}
}
}
}
if(dn){
var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,null,false);
var nd;
while(nd=walker.nextNode()){
if(nd.nodeValue.indexOf('WordPress.org')!==-1){nd.nodeValue=nd.nodeValue.replace(/WordPress\.org/g,dn)}
}
}
}

cleanup();
setTimeout(cleanup,200);
setTimeout(cleanup,500);
setTimeout(cleanup,1000);
setTimeout(cleanup,2000);
});
</script>
                <?php
            });
        }

        /**
         * Enable License Gate for Themes.
         *
         * Themes have no menu, so the license UI is injected via admin_notices on themes.php.
         *
         * @param array $config Gate configuration for the theme
         */
        public static function enableThemeLicenseGate($config = [])
        {
            self::$product_type = 'theme';
            self::$theme_dir = $config['theme_dir'] ?? '';
            self::$slug = $config['slug'] ?? self::$theme_dir;
            $slug = self::$slug;

            $defaults = [
                'slug'             => $slug,
                'name'             => wp_get_theme(self::$theme_dir)->get('Name') ?: $slug,
                'option_key'       => $slug . '_license_key',
                'status_key'       => $slug . '_license_status',
                'menu_title'       => 'License',
                'page_title'       => 'License Activation',
                'protection_enabled' => true,
                'settings_url'     => '',
                'renewal_url'      => '',
                'purchase_url'     => '',
                'tutorial_url'     => '',
            ];
            self::$license_gate_config = array_merge($defaults, $config);

            // Register in license_gates for handleLicenseGateActions compatibility
            self::$license_gates[$slug] = self::$license_gate_config;

            // Inject license UI only on themes.php
            add_action('admin_enqueue_scripts', function ($hook) {
                if ($hook !== 'themes.php') return;
                self::renderThemeLicenseUI();
            });

            // Handle activate/deactivate form submissions
            add_action('admin_init', function () {
                self::handleLicenseGateActions(self::$license_gate_config);
            });

            // Delayed redirect flush
            add_action('admin_init', [self::class, 'flushPendingRedirects'], 999999);

            // Install/update mu-plugin for tamper monitoring
            add_action('admin_init', function () {
                $status = get_option(self::$license_gate_config['status_key'], '');
                if ($status === 'valid') {
                    self::installUniversalMuPlugin(self::$license_gate_config);
                }
            });
        }

        /**
         * Render Theme license UI on themes.php page.
         * Shows activation form (inactive) or license info bar (active).
         */
        private static function renderThemeLicenseUI()
        {
            $slug = self::$slug;
            $config = self::$license_gate_config;
            $option_key = $config['option_key'] ?? $slug . '_license_key';
            $status_key = $config['status_key'] ?? $slug . '_license_status';
            $license_key = get_option($option_key, '');
            $license_status = get_option($status_key, '');

            if (empty($license_key) || $license_status !== 'valid') {
                // === Not activated: show activation notice + form ===
                add_action('admin_notices', function () use ($config) {
                    $theme_name = wp_get_theme(self::$theme_dir)->get('Name') ?: self::$slug;
                    echo '<div class="notice notice-warning">';
                    echo '<h3>' . esc_html($theme_name) . ' - License Activation Required</h3>';
                    self::renderLicenseGateForm($config);
                    echo '</div>';
                });
                return;
            }

            // === Activated: show license info bar ===
            add_action('admin_notices', function () use ($config, $license_key, $status_key) {
                self::renderLicenseInfoBar($config);
            });
        }

        /**
         * 安全获取插件页面的 hook 名称。
         *
         * 优先使用 WP 原生 get_plugin_page_hook()；若返回空，
         * 则通过 sanitize_title() 手动计算，解决中文标题导致 hook 名不一致的问题。
         *
         * WP 内部格式：sanitize_title($parent) . '_page_' . sanitize_title($page)
         *
         * @param string $page_slug   页面 slug
         * @param string $parent_slug 父级 slug
         * @return string|null hook 名称，或 null
         */
        private static function getPluginPageHookSafe($page_slug, $parent_slug)
        {
            // 优先使用 WP 原生（对 ASCII slug 可靠）
            if (function_exists('get_plugin_page_hook')) {
                $hook = get_plugin_page_hook($page_slug, $parent_slug);
                if ($hook) return $hook;
            }

            // Fallback：按 WP 内部算法手动计算
            if (function_exists('sanitize_title')) {
                $parent = sanitize_title($parent_slug);
                $page   = sanitize_title($page_slug);
                return $parent . '_page_' . $page;
            }

            return null;
        }

        /**
         * load-{hook} 回调：对插件页面应用许可证门槛逻辑
         */
        private static function applyLicenseGateLogic($hook, $slug, $config)
        {
            // 篡改检测 — 优先于许可证检查
            if (self::$tampered) {
                remove_all_actions($hook);
                add_action($hook, function () use ($config) {
                    self::renderTamperPopup();
                });
                return;
            }

            $status_key = $config['status_key'] ?? $slug . '_license_status';
            $status = get_option($status_key, '');

            if ($status !== 'valid') {
                // 未激活：移除原始页面回调，替换为激活表单
                remove_all_actions($hook);
                add_action($hook, function () use ($config) {
                    self::renderLicenseGateForm($config);
                });
            }
            // 已激活：不修改 hook，保留原始回调（信息条由 enableLicenseGate 的 admin_menu 统一通过 admin_notices 渲染）
        }

        /**
         * 渲染许可证激活表单（未激活时显示）
         */
        private static function renderLicenseGateForm($config)
        {
            $slug = $config['slug'];
            $status = get_option($config['status_key'] ?? $slug . '_license_status', '');
            $page_title = $config['page_title'] ?? $config['name'] ?? $slug;

            echo '<div class="wrap">';
            echo '<h1>' . esc_html($page_title) . '</h1>';
            echo '<div style="background:#fff; border:1px solid #ccd0d4; padding:30px; margin:20px 0; max-width:500px; text-align:center;">';
            echo '<h2 style="margin-top:0;">&#x1F511; 激活许可证以使用此插件</h2>';
            echo '<p style="color:#666;">此功能需要先激活许可证才能使用。</p>';

            if ($status === 'invalid' || $status === 'expired') {
                echo '<div style="background:#fff3cd; border:1px solid #ffc107; padding:10px; margin:10px 0; border-radius:4px; color:#856404;">';
                echo '&#x26A0;&#xFE0F; 许可证已失效，请重新激活。';
                echo '</div>';
            }

            echo '<form method="post" style="text-align:left; margin-top:20px;">';
            wp_nonce_field('licenseos_gate_' . $slug);
            echo '<input type="hidden" name="licenseos_action" value="activate">';
            echo '<input type="hidden" name="licenseos_slug" value="' . esc_attr($slug) . '">';
            echo '<table class="form-table"><tr>';
            echo '<th><label for="license_key">许可证密钥</label></th>';
            echo '<td><input type="text" id="license_key" name="license_key" class="regular-text" placeholder="请输入许可证密钥" required>';
            echo '<p class="description">从 LicenseOS 平台「我的许可证」页面获取</p></td>';
            echo '</tr></table>';
            echo '<p><button type="submit" class="button button-primary button-large">激活许可证</button>';
            $purchase_url = $config['purchase_url'] ?? '';
            if ($purchase_url) {
                echo ' <a href="' . esc_url($purchase_url) . '" target="_blank" class="button button-large" style="margin-left:8px;">购买激活码</a>';
            }
            if (!empty($config['tutorial_url'])) {
                echo ' <a href="' . esc_url($config['tutorial_url']) . '" target="_blank" class="button button-large" style="margin-left:8px;">教程</a>';
            }
            // 查看详情链接 — 使用 product_slug（产品目录名）而非菜单 slug
            $plugin_slug = $config['product_slug'] ?? $config['slug'] ?? '';
            if ($plugin_slug) {
                $details_url = admin_url('plugin-install.php?tab=plugin-information&plugin=' . urlencode($plugin_slug) . '&TB_iframe=true&width=772&height=600');
                echo ' <a href="' . esc_url($details_url) . '" class="thickbox button button-large" style="margin-left:8px;">查看详情</a>';
            }
            echo '</p>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }

        /**
         * 渲染授权信息条（已激活时显示在设置页顶部）
         * 实时调用 check() 获取最新到期日期，不依赖缓存的 option
         * 提供 licenseos_info_bar_css / licenseos_info_bar_js 钩子防止样式冲突
         */
        private static function renderLicenseInfoBar($config)
        {
            if (self::$tampered) return;

            $slug = $config['slug'] ?? '';
            $status_key = $config['status_key'] ?? $slug . '_license_status';
            $option_key = $config['option_key'] ?? $slug . '_license_key';
            $license_key = get_option($option_key, '');

            // 实时查询到期日期
            $expires = '';
            if ($license_key) {
                $checkResult = self::check($license_key);
                $expires = $checkResult['expire_at'] ?? '';
                // 同时更新缓存的 option
                if ($expires) {
                    update_option($status_key . '_expires', $expires);
                }
            } else {
                // fallback 读缓存
                $expires = get_option($status_key . '_expires', '');
            }

            $masked = $license_key ? substr($license_key, 0, 8) . '****' . substr($license_key, -4) : '';

            // 构建到期日期 HTML
            $expires_html = '';
            $is_expired = false;
            if ($expires) {
                $expire_date = date('Y-m-d', strtotime($expires));
                $is_expired = strtotime($expires) < time();
                if ($is_expired) {
                    $expires_html = '<span class="licenseos-expired">已过期（' . esc_html($expire_date) . '）</span>';
                } else {
                    $expires_html = '<code>' . esc_html($expire_date) . '到期</code>';
                }
            }

            // 过期时切换为黄色横幅
            if ($is_expired) {
                $bar_bg = '#fff8e1';
                $bar_border = '#ffc107';
                $status_icon = '&#x26A0;';
                $status_text = '许可证已过期';
                $status_class = 'licenseos-expired';
                $warning_msg = '超过三天不续费，插件功能将被停用！';
            } else {
                $bar_bg = '#e7f5e7';
                $bar_border = '#46b450';
                $status_icon = '&#x2705;';
                $status_text = '许可证已激活';
                $status_class = 'licenseos-status';
                $warning_msg = '';
            }

            // 默认 CSS（通过 class 隔离，防止与其他插件样式冲突）
            $default_css = '.licenseos-info-bar{background:' . $bar_bg . ';border:1px solid ' . $bar_border . ';border-radius:4px;padding:12px 16px;margin:5px 0 15px;font-size:13px;line-height:1.6}'
                . '.licenseos-info-bar .licenseos-status{color:#46b450;font-weight:bold}'
                . '.licenseos-info-bar .licenseos-sep{color:#999;margin:0 6px}'
                . '.licenseos-info-bar .licenseos-expired{color:#e65100;font-weight:bold}'
                . '.licenseos-info-bar .licenseos-renew{color:#0073aa;text-decoration:none}'
                . '.licenseos-info-bar .licenseos-deactivate{color:#dc3232;text-decoration:none}'
                . '.licenseos-info-bar code{background:#f0f0f0;padding:2px 5px;border-radius:3px;font-size:12px}';

            // licenseos_info_bar_css 钩子：允许插件自定义 CSS
            $custom_css = '';
            if (function_exists('apply_filters')) {
                $custom_css = apply_filters('licenseos_info_bar_css', '', $slug);
            }

            // 输出样式（默认 + 自定义）通过 WP 原生 API
            $css_handle = 'licenseos-info-bar-css-' . sanitize_key($slug);
            $all_css = $default_css;
            if ($custom_css) {
                $all_css .= "\n" . $custom_css;
            }
            if (function_exists('wp_register_style') && function_exists('wp_add_inline_style')) {
                wp_register_style($css_handle, false);
                wp_add_inline_style($css_handle, $all_css);
                wp_enqueue_style($css_handle);
            } else {
                echo '<style id="' . esc_attr($css_handle) . '">';
                echo $all_css;
                echo '</style>';
            }

            // Inline CSS 兜底：防止主题/插件样式覆盖信息条
            echo '<style>.licenseos-info-bar{background:' . $bar_bg . '!important;border:1px solid ' . $bar_border . '!important;border-radius:4px!important;padding:12px 16px!important;margin:5px 0 15px!important;font-size:13px!important;line-height:1.6!important;display:block!important;visibility:visible!important;opacity:1!important;position:relative!important;z-index:1!important}</style>';

            // 显示信息条（使用 class 而非 inline style，防止样式冲突）
            echo '<div id="licenseos-info-bar" class="licenseos-info-bar">';
            echo '<span class="' . $status_class . '">' . $status_icon . ' ' . $status_text . '</span>';

            // 密钥（脱敏显示）
            if ($masked) {
                echo '<span class="licenseos-sep">|</span> 密钥: <code>' . esc_html($masked) . '</code>';
            }

            // 域名
            echo '<span class="licenseos-sep">|</span> 域名: <code>' . esc_html($_SERVER['HTTP_HOST'] ?? '') . '</code>';

            // 到期日期
            if ($expires_html) {
                echo '<span class="licenseos-sep">|</span> ' . $expires_html;
            }

            // 续费警告（仅过期时显示）
            if ($warning_msg) {
                echo '<br><strong style="color:#e65100">' . $warning_msg . '</strong>';
            }

            // 续期按钮
            $_renewHost = parse_url(self::$api, PHP_URL_HOST) ?: 'your-domain.com';
            echo '<span class="licenseos-sep">|</span> <a href="https://' . esc_attr($_renewHost) . '/my-licenses" target="_blank" class="licenseos-renew">续期</a>';

            // 停用按钮（通过 hidden form 提交）
            echo '<span class="licenseos-sep">|</span> <a href="#" class="licenseos-deactivate" onclick="if(confirm(\'确定要停用许可证吗？\')){document.getElementById(\'licenseos-deact-form\').submit();}return false;">停用</a>';
            echo '<form id="licenseos-deact-form" method="post" style="display:none;">';
            wp_nonce_field('licenseos_gate_' . $slug);
            echo '<input type="hidden" name="licenseos_action" value="deactivate">';
            echo '<input type="hidden" name="licenseos_slug" value="' . esc_attr($slug) . '">';
            echo '</form>';

            echo '</div>';

            // licenseos_info_bar_js 钩子：允许插件注入自定义 JS（通过 WP 原生 API）
            if (function_exists('apply_filters') && function_exists('wp_add_inline_script')) {
                $custom_js = apply_filters('licenseos_info_bar_js', '', $slug);
                if ($custom_js) {
                    $js_handle = 'licenseos-info-bar-js-' . sanitize_key($slug);
                    wp_register_script($js_handle, false, [], '', true);
                    wp_add_inline_script($js_handle, $custom_js);
                    wp_enqueue_script($js_handle);
                }
            }
        }

        /**
         * Handle license gate activate / deactivate form submissions.
         */
        private static function handleLicenseGateActions($config)
        {
            if (!isset($_POST['licenseos_action']) || !isset($_POST['licenseos_slug'])) {
                return;
            }
            if ($_POST['licenseos_slug'] !== $config['slug']) {
                return;
            }
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'licenseos_gate_' . $config['slug'])) {
                return;
            }
            if (!current_user_can('manage_options')) {
                return;
            }

            $slug = $config['slug'];
            $product_slug = $config['product_slug'] ?? $slug;
            $action = sanitize_text_field($_POST['licenseos_action']);

            if ($action === 'activate' && !empty($_POST['license_key'])) {
                $key = sanitize_text_field($_POST['license_key']);
                $result = self::activate($key, $_SERVER['HTTP_HOST'], $product_slug);
                if ($result && in_array($result['status'] ?? '', ['active', 'valid', 'success'])) {
                    update_option($config['option_key'], $key);
                    update_option($config['status_key'], 'valid');
                    // Clear all invalid flags that may have been set by previous failed checks
                    delete_option($slug . '_license_invalid');
                    delete_option($slug . '_license_reason');
                    delete_option($slug . '_license_expired');
                    delete_option($slug . '_license_expired_at');
                    // Activate API doesn't return valid_until, call check to get expiry date
                    $checkResult = self::check($key);
                    update_option($config['status_key'] . '_expires', $checkResult['expire_at'] ?? $checkResult['license']['valid_until'] ?? '');
                    // Enable auto-update with the newly activated key
                    self::$license_keys[$slug] = $key;
                    if ($product_slug !== $slug) {
                        self::$license_keys[$product_slug] = $key;
                    }
                    // 清除插件缓存，强制刷新 transient（确保激活后“查看详情”链接立即可见）
                    wp_clean_plugins_cache();
                    // 自动安装/更新通用 mu-plugin
                    self::installUniversalMuPlugin($config);
                    $parent = $config['parent_slug'] ?? 'options-general.php';
                    self::$pending_redirects[] = add_query_arg(['page' => $slug, 'licenseos' => 'activated'], admin_url($parent));
                    return;
                } else {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-error"><p>许可证激活失败，请检查密钥是否正确。</p></div>';
                    });
                }
            } elseif ($action === 'deactivate') {
                $key = get_option($config['option_key'], '');
                if ($key) {
                    self::deactivate($key, $_SERVER['HTTP_HOST'], $slug);
                }
                delete_option($config['option_key']);
                delete_option($config['status_key']);
                delete_option($config['status_key'] . '_expires');
                // Clear all invalid flags
                delete_option($slug . '_license_invalid');
                delete_option($slug . '_license_reason');
                delete_option($slug . '_license_expired');
                delete_option($slug . '_license_expired_at');
                unset(self::$license_keys[$slug]);
                // Remove this plugin from mu-plugin
                self::removePluginFromMuPlugin($slug);
                $parent = $config['parent_slug'] ?? 'options-general.php';
                self::$pending_redirects[] = add_query_arg(['page' => $slug, 'licenseos' => 'deactivated'], admin_url($parent));
                return;
            }
        }

        /**
         * 延迟执行 pending redirects（admin_init 最高优先级触发）
         * 确保其他插件的 admin_init 回调全部执行完毕后再跳转
         */
        public static function flushPendingRedirects()
        {
            if (!empty(self::$pending_redirects)) {
                $url = self::$pending_redirects[0]; // 只取第一个
                self::$pending_redirects = [];
                wp_redirect($url);
                exit;
            }
        }

        /**
         * 篡改检测（admin_init 钩子触发）：
         * 1. E.2 文件完整性检查
         * 2. 远程 API 验证（带 1 小时缓存）
         */
        public static function integrityCheck()
        {
            if (self::$tampered) return;
            if (empty(self::$api_key) || strlen(self::$api_key) < 10) {
                self::$tampered = true;
                return;
            }

            // Step 1: E.2 文件完整性检查
            self::checkE2FilesIntegrity();
            if (self::$tampered) return;

            // Step 2: 远程 API 验证
            // 检查缓存（1 小时），避免每次加载都远程请求
            $lastCheck = get_transient('licenseos_integrity');
            if ($lastCheck !== false) return;

            $licenseKey = '';
            foreach (self::$license_gates as $slug => $config) {
                $lk = get_option($config['option_key'] ?? '', '');
                if ($lk) {
                    $licenseKey = $lk;
                    break;
                }
            }

            if ($licenseKey) {
                $result = self::check($licenseKey);
                if (isset($result['status']) && in_array($result['status'], ['error', 'tampered', 'unauthorized'])) {
                    self::$tampered = true;
                } elseif (!empty($result['is_tampered'])) {
                    self::$tampered = true;
                }
            } else {
                // 未激活许可证，上报 hash
                $apiKeyHash = hash('sha256', self::$api_key);
                $payload = json_encode([
                    'domain'       => $_SERVER['HTTP_HOST'] ?? '',
                    'api_key'      => self::$api_key,
                    'api_key_hash' => $apiKeyHash,
                ]);
                self::fireAndForget(self::$api . '/integrity/report', $payload);
            }

            // 缓存检查结果 1 小时（仅通过时缓存）
            if (!self::$tampered) {
                set_transient('licenseos_integrity', 'checked', HOUR_IN_SECONDS);
            }
        }

        /**
         * 渲染篡改警告弹窗（admin_footer 钩子触发）
         * 只在插件自己的管理页面显示
         */
        public static function renderTamperPopup()
        {
            if (!self::$tampered) return;
            if (!self::isPluginAdminPage()) return;

            echo '<div id="licenseos-tamper-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:999999;display:flex;align-items:center;justify-content:center;">';
            echo '<div style="background:#fff;border-radius:12px;padding:40px;max-width:520px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.5);">';
            echo '<div style="font-size:56px;margin-bottom:20px;">&#x1F6AB;</div>';
            echo '<h2 style="color:#dc3232;margin:0 0 16px;font-size:22px;">安全警告</h2>';
            echo '<p style="font-size:16px;line-height:1.8;color:#333;margin:0;">';
            echo '嗨！那个谁！你不可以篡改代码的任何地方，否则不可使用！<br>';
            echo '请重新到官方下载最新版插件！';
            echo '</p></div></div>';
            echo '<script>document.body.style.overflow="hidden";</script>';
        }

        /**
         * 禁用管理员菜单（admin_menu 钩子触发）
         * 只在插件自己的管理页面禁用
         */
        public static function disableMenusOnTamper()
        {
            if (!self::$tampered) return;
            if (!self::isPluginAdminPage()) return;

            global $menu;
            if (is_array($menu)) {
                foreach ($menu as $key => $item) {
                    if (isset($item[1]) && $item[1] === 'manage_options') {
                        remove_menu_page($item[2]);
                    }
                }
            }
        }

        /**
         * 判断当前是否是插件的管理页面（支持顶级页面和子菜单页面）
         * 优先使用 license_gates 注册的 slug，回退到插件目录名
         */
        private static function isPluginAdminPage()
        {
            // Theme type: check themes.php
            if (self::$product_type === 'theme') {
                global $pagenow;
                return $pagenow === 'themes.php';
            }

            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            if (empty($page)) return false;

            foreach (self::$license_gates as $slug => $config) {
                if ($page === $slug) return true;
                if (strpos($page, $slug) === 0) return true;
            }

            // Fallback: 从插件目录名推断页面 slug 前缀
            if (!empty(self::$base_dir)) {
                $dirName = basename(rtrim(self::$base_dir, '/\\'));
                if ($dirName && (strpos($page, $dirName) === 0 || $page === $dirName)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * E.2 代码块完整性检查：验证 licenseos-sdk.php 和插件主文件中的 E.2 代码是否存在
         */
        public static function checkE2FilesIntegrity()
        {
            if (self::$tampered) return;
            if (empty(self::$base_dir)) return;

            // 检查 1：licenseos-sdk.php 文件是否存在（兼容新路径 includes/licenseos/ 与旧根目录路径）
            $sdkFileNew = rtrim(self::$base_dir, '/') . '/includes/licenseos/licenseos-sdk.php';
            $sdkFileOld = rtrim(self::$base_dir, '/') . '/licenseos-sdk.php';
            if (!file_exists($sdkFileNew) && !file_exists($sdkFileOld)) {
                self::$tampered = true;
                return;
            }

            // Theme type: check theme's functions.php instead of plugin main file
            if (self::$product_type === 'theme') {
                $themePath = get_theme_root() . '/' . self::$theme_dir;
                $mainFile = $themePath . '/functions.php';
                $content = @file_get_contents($mainFile);
                if ($content) {
                    $_apiKeyFragment = self::$api_key;
                    $_sdkRef = 'licenseos' . '-sdk.php';
                    $_initRef = 'LicenseOS' . '::init';
                    $_requireRefNew = 'require_once' . " __DIR__ . '/includes/licenseos/licenseos-sdk.php'";
                    $_requireRefOld = 'require_once' . " __DIR__ . '/licenseos-sdk.php'";
                    if (strpos($content, $_apiKeyFragment) === false
                        || strpos($content, $_sdkRef) === false
                        || strpos($content, $_initRef) === false
                        || (strpos($content, $_requireRefNew) === false && strpos($content, $_requireRefOld) === false)) {
                        self::$tampered = true;
                    }
                    unset($content, $_apiKeyFragment, $_sdkRef, $_initRef, $_requireRefNew, $_requireRefOld);
                }
                return;
            }

            // 检查 2：插件主文件中 E.2 代码是否完整
            $pluginSlug = !empty(self::$slug) ? self::$slug : basename(self::$base_dir);
            $pluginFile = rtrim(self::$base_dir, '/') . '/' . $pluginSlug . '.php';
            $content = @file_get_contents($pluginFile);
            if ($content) {
                $_apiKeyFragment = self::$api_key;
                $_sdkRef = 'licenseos' . '-sdk.php';
                $_initRef = 'LicenseOS' . '::init';
                $_requireRefNew = 'require_once' . " __DIR__ . '/includes/licenseos/licenseos-sdk.php'";
                $_requireRefOld = 'require_once' . " __DIR__ . '/licenseos-sdk.php'";
                if (strpos($content, $_apiKeyFragment) === false
                    || strpos($content, $_sdkRef) === false
                    || strpos($content, $_initRef) === false
                    || (strpos($content, $_requireRefNew) === false && strpos($content, $_requireRefOld) === false)) {
                    self::$tampered = true;
                }
                unset($content, $_apiKeyFragment, $_sdkRef, $_initRef, $_requireRefNew, $_requireRefOld);
            }
        }

        /**
         * 自动安装/更新通用 mu-plugin（WP System Health Monitor）
         * 从数据库 licenseos_mu_plugins option 读取配置，文件内容固定不变
         */
        private static function installUniversalMuPlugin($config)
        {
            $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
            if (!is_dir($mu_dir)) {
                @mkdir($mu_dir, 0755, true);
            }

            $mu_file = $mu_dir . '/wp-system-health-monitor.php';

            // 读取现有配置（如果有）
            $plugins = get_option('licenseos_mu_plugins', []);
            if (!is_array($plugins)) $plugins = [];

            // 添加/更新当前插件/主题
            $slug = $config['slug'];
            $plugins[$slug] = [
                'name'        => $config['name'] ?? $slug,
                'api'         => self::$api,
                'api_key'     => self::$api_key,
                'option_key'  => $config['option_key'] ?? $slug . '_license_key',
                'status_key'  => $config['status_key'] ?? $slug . '_license_status',
                'plugin_file' => self::$product_type === 'theme'
                    ? self::$theme_dir . '/functions.php'
                    : $slug . '/' . $slug . '.php',
                'product_type' => self::$product_type,
                'site_name'   => $config['site_name'] ?? 'LicenseOS',
                'added_at'    => time(),
            ];

            update_option('licenseos_mu_plugins', $plugins);

            // 生成 mu-plugin 文件（从数据库读取配置，文件内容固定）
            $code = self::generateUniversalMuPluginCode();
            @file_put_contents($mu_file, $code);
        }

        /**
         * 从 mu-plugin 中移除指定插件条目
         * 若无插件则删除 mu-plugin 文件和 option
         */
        private static function removePluginFromMuPlugin($slug)
        {
            $plugins = get_option('licenseos_mu_plugins', []);
            unset($plugins[$slug]);
            update_option('licenseos_mu_plugins', $plugins);

            // 如果没有插件了，删除 mu-plugin 文件和 option
            if (empty($plugins)) {
                $mu_file = WP_CONTENT_DIR . '/mu-plugins/wp-system-health-monitor.php';
                if (file_exists($mu_file)) {
                    @unlink($mu_file);
                }
                delete_option('licenseos_mu_plugins');
            }
            // 否则不需要重写文件，因为 mu-plugin 从数据库读取配置
        }

        /**
         * 生成通用 mu-plugin 代码
         * 文件内容固定，运行时从 WP 数据库 licenseos_mu_plugins option 读取配置
         */
        private static function generateUniversalMuPluginCode()
        {
            return <<<'PHP'
<?php
/**
 * WP System Health Monitor
 * Monitors plugin integrity for licensed products.
 * @package WordPress
 */
if (!defined('ABSPATH')) exit;

// === Remote license verification (every 24 hours) ===
add_action('admin_init', function() {
    $plugins = get_option('licenseos_mu_plugins', []);
    if (!is_array($plugins) || empty($plugins)) return;

    foreach ($plugins as $slug => $info) {
        $cacheKey = 'licenseos_remote_check_' . $slug;
        if (get_transient($cacheKey) !== false) continue;

        $license_key = get_option($info['option_key'] ?? '', '');
        if (!$license_key) continue;

        $api = rtrim($info['api'] ?? '', '/');
        // Remove duplicate /api/v2 suffix if present
        if (strpos($api, '/api/v2') === strlen($api) - 7) {
            $api = substr($api, 0, -7);
        }
        $api_key = $info['api_key'] ?? '';
        if (!$api || !$api_key) continue;

        $response = @wp_remote_post($api . '/api/v2/license/check', [
            'headers' => [
                'X-API-KEY' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'license_key' => $license_key,
                'domain' => $_SERVER['HTTP_HOST'] ?? ''
            ]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            set_transient($cacheKey, 'error', 24 * HOUR_IN_SECONDS);
            continue;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $status = $data['status'] ?? '';

        if ($status === 'valid') {
            // 许可证有效，清除所有失效标记
            delete_option($slug . '_license_invalid');
            delete_option($slug . '_license_reason');
            delete_option($slug . '_license_expired');
            delete_option($slug . '_license_expired_at');
            // 更新平台名称（如果 API 返回）
            if (!empty($data['site_name'])) {
                $allPlugins = get_option('licenseos_mu_plugins', []);
                if (is_array($allPlugins) && isset($allPlugins[$slug])) {
                    $allPlugins[$slug]['site_name'] = $data['site_name'];
                    update_option('licenseos_mu_plugins', $allPlugins);
                }
            }
        } elseif ($status === 'expired') {
            // 许可证过期 — 标记但不停用（给用户续费机会）
            update_option($slug . '_license_invalid', true);
            update_option($slug . '_license_reason', '许可证已过期，请及时续费');
            update_option($slug . '_license_expired', true);
            // 过期超过 3 天 → 停用插件
            $expiredAt = get_option($slug . '_license_expired_at', '');
            if (empty($expiredAt)) {
                update_option($slug . '_license_expired_at', time());
            } elseif ((time() - intval($expiredAt)) > 3 * DAY_IN_SECONDS) {
                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                if (isset($info['plugin_file']) && function_exists('is_plugin_active') && is_plugin_active($info['plugin_file'])) {
                    deactivate_plugins([$info['plugin_file']]);
                }
            }
        } else {
            // 其他无效状态（tampered, suspended, 等）— 立即停用
            update_option($slug . '_license_invalid', true);
            update_option($slug . '_license_reason', $data['tamper_warning'] ?? '许可证验证失败');
            delete_option($slug . '_license_expired');
            if (isset($info['plugin_file'])) {
                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                if (function_exists('is_plugin_active') && is_plugin_active($info['plugin_file'])) {
                    deactivate_plugins([$info['plugin_file']]);
                }
            }
        }

        set_transient($cacheKey, $status, 24 * HOUR_IN_SECONDS);
    }
}, 5);

// === Deactivation notice for invalid license ===
add_action('admin_notices', function() {
    $plugins = get_option('licenseos_mu_plugins', []);
    if (!is_array($plugins)) return;

    $cleaned = false;

    foreach ($plugins as $slug => $info) {
        // 检查插件文件是否存在，如果不存在则清理
        $plugin_file = $info['plugin_file'] ?? '';
        if (!empty($plugin_file)) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
            if (!file_exists($plugin_path)) {
                // 插件已卸载，清理所有相关 option
                delete_option($slug . '_license_invalid');
                delete_option($slug . '_license_reason');
                delete_option($slug . '_license_expired');
                delete_option($slug . '_license_expired_at');
                delete_option($slug . '_tampered');
                // 从 mu-plugin 中移除
                unset($plugins[$slug]);
                $cleaned = true;
                continue;
            }
        }

        if (get_option($slug . '_license_invalid')) {
            $reason = get_option($slug . '_license_reason', '许可证验证失败');
            $name = $info['name'] ?? $slug;
            $isExpired = get_option($slug . '_license_expired', false);
            if ($isExpired) {
                // 过期提示（黄色警告，不停用）
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>LicenseOS:</strong> 插件 <strong>' . esc_html($name) . '</strong> 的许可证已过期，请及时续费。';
                echo ' 过期超过 3 天将自动停用插件。';
                echo '</p></div>';
            } else {
                // 停用提示（红色错误）
                echo '<div class="notice notice-error"><p>';
                echo '<strong>LicenseOS:</strong> 插件 <strong>' . esc_html($name) . '</strong> 已停用 — ' . esc_html($reason);
                echo '</p></div>';
            }
        }
    }

    // 如果有清理，更新 option
    if ($cleaned) {
        if (!empty($plugins)) {
            update_option('licenseos_mu_plugins', $plugins);
        } else {
            delete_option('licenseos_mu_plugins');
        }
    }
});

add_action('admin_init', function() {
    $plugins = get_option('licenseos_mu_plugins', []);
    if (!is_array($plugins) || empty($plugins)) return;

    foreach ($plugins as $slug => $info) {
        $license_key = get_option($info['option_key'] ?? '', '');
        $status = get_option($info['status_key'] ?? '', '');
        if (!$license_key || $status !== 'valid') continue;

        // 检查插件文件是否存在
        $plugin_path = WP_PLUGIN_DIR . '/' . ($info['plugin_file'] ?? '');
        if (!file_exists($plugin_path)) continue;

        // 读取插件主文件，检查 SDK 集成代码是否完整
        $content = @file_get_contents($plugin_path);
        if (!$content) continue;

        $api_key = $info['api_key'] ?? '';
        $has_sdk = strpos($content, 'licenseos-sdk.php') !== false;
        $has_init = strpos($content, 'LicenseOS::init') !== false;
        $has_api_key = strlen($api_key) > 10 && strpos($content, substr($api_key, 0, 20)) !== false;

        if (!$has_sdk || !$has_init || !$has_api_key) {
            // 向平台报告篡改
            $api = rtrim($info['api'] ?? '', '/');
            if ($api) {
                @wp_remote_post($api . '/integrity/report', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode([
                        'domain' => $_SERVER['HTTP_HOST'] ?? '',
                        'api_key' => $api_key,
                        'license_key' => $license_key,
                        'tamper_reason' => 'code_modified',
                    ]),
                    'timeout' => 5,
                    'blocking' => false,
                ]);
            }
            update_option($slug . '_tampered', true);

            // Deactivate plugin on local tamper detection
            if (isset($info['plugin_file'])) {
                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                if (function_exists('is_plugin_active') && is_plugin_active($info['plugin_file'])) {
                    deactivate_plugins([$info['plugin_file']]);
                }
            }
        } else {
            delete_option($slug . '_tampered');
        }
    }
});

// 篡改时阻止目标插件
add_action('plugins_loaded', function() {
    $plugins = get_option('licenseos_mu_plugins', []);
    if (!is_array($plugins) || empty($plugins)) return;

    foreach ($plugins as $slug => $info) {
        if (!get_option($slug . '_tampered', false)) continue;

        // 只在目标插件的管理页面弹窗
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if (strpos($page, $slug) !== 0) continue;

        // 移除插件菜单
        add_action('admin_menu', function() use ($slug) {
            remove_menu_page($slug);
        }, 999);

        // 全屏弹窗
        add_action('admin_footer', function() {
            echo '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.92);z-index:9999999;display:flex;align-items:center;justify-content:center;">';
            echo '<div style="background:#fff;border-radius:12px;padding:40px;max-width:520px;text-align:center;">';
            echo '<div style="font-size:56px;margin-bottom:20px;">&#x1F6AB;</div>';
            echo '<h2 style="color:#dc3232;margin:0 0 16px;font-size:22px;">安全警告</h2>';
            echo '<p style="font-size:16px;line-height:1.8;color:#333;margin:0;">';
            echo '嗨！那个谁！你不可以篡改代码的任何地方，否则不可使用！<br>';
            echo '请重新到官方下载最新版插件！';
            echo '</p></div></div>';
            echo '<script>document.body.style.overflow="hidden";</script>';
        }, 1);
    }
}, 1);

// === Expired license modal (after 3-day grace period) ===
add_action('admin_footer', function() {
    $plugins = get_option('licenseos_mu_plugins', []);
    if (!is_array($plugins)) return;
    foreach ($plugins as $slug => $info) {
        if (!get_option($slug . '_license_expired')) continue;
        $expiredAt = get_option($slug . '_license_expired_at', '');
        if (empty($expiredAt) || (time() - intval($expiredAt)) <= 3 * DAY_IN_SECONDS) continue;

        $name = $info['name'] ?? $slug;
        $api = rtrim($info['api'] ?? '', '/');
        $siteName = $info['site_name'] ?? 'LicenseOS';
        echo '<div id="licenseos-expired-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999999;display:flex;align-items:center;justify-content:center">';
        echo '<div style="background:#fff;border-radius:12px;padding:40px;max-width:500px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.3)">';
        echo '<div style="font-size:48px;margin-bottom:16px">🔒</div>';
        echo '<h2 style="margin:0 0 12px;color:#333;font-size:20px">插件功能已停用</h2>';
        echo '<p style="color:#666;font-size:14px;line-height:1.8;margin:0 0 20px">';
        echo '因续费超时，<strong>' . esc_html($name) . '</strong> 的插件功能已自动停用。<br>';
        echo '如需继续使用，请到 <a href="' . esc_url($api . '/my-licenses') . '" target="_blank" style="color:#0073aa">平台</a> 为您的证书续费。';
        echo '</p>';
        echo '<p style="color:#999;font-size:13px;margin:0">' . esc_html($siteName) . ' 期待与您再次相逢！</p>';
        echo '</div></div>';
        echo '<script>document.body.style.overflow="hidden";</script>';
        break;
    }
});

// === Plugin details fallback when plugin is deactivated ===
// Serves local readme.txt data so "View details" works even when the plugin is inactive.
add_filter('plugins_api', function($result, $action, $args) {
    if ($action !== 'plugin_information') return $result;

    $registered = get_option('licenseos_mu_plugins', []);
    if (!is_array($registered) || empty($registered)) return $result;

    $matched_slug = null;
    foreach ($registered as $slug => $info) {
        if ($slug && isset($args->slug) && $args->slug === $slug) {
            $matched_slug = $slug;
            break;
        }
    }
    if (!$matched_slug) return $result;

    // If plugin is active, let the SDK handle it
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_file = $matched_slug . '/' . $matched_slug . '.php';
    if (is_plugin_active($plugin_file)) return $result;

    // Plugin is deactivated: build details from local readme.txt
    $plugin_dir = WP_PLUGIN_DIR . '/' . $matched_slug;
    $readme_path = $plugin_dir . '/readme.txt';
    if (!file_exists($readme_path)) return $result;

    $raw = @file_get_contents($readme_path);
    if (!$raw) return $result;
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);

    // --- Inline readme parser ---
    $plugin_name = $matched_slug;
    if (preg_match('/^===\s*(.+?)\s*===/m', $raw, $nm)) {
        $plugin_name = trim($nm[1]);
    }

    $version = '';
    if (preg_match('/^Stable tag:\s*(.+)$/mi', $raw, $vm)) {
        $version = trim($vm[1]);
    }

    $requires = $tested = $requires_php = '';
    if (preg_match('/^Requires at least:\s*(.+)$/mi', $raw, $rm)) $requires = trim($rm[1]);
    if (preg_match('/^Tested up to:\s*(.+)$/mi', $raw, $tm)) $tested = trim($tm[1]);
    if (preg_match('/^Requires PHP:\s*(.+)$/mi', $raw, $pm)) $requires_php = trim($pm[1]);

    $short_desc = '';
    if (preg_match('/^===\s*.+?\s*===\s*\n(.*?)(?=^==\s|\z)/ms', $raw, $hm)) {
        foreach (explode("\n", $hm[1]) as $ln) {
            $ln = trim($ln);
            if ($ln === '') continue;
            if (preg_match('/^(Contributors|Tags|Requires|Tested|Stable|License|Donate):/i', $ln)) continue;
            $short_desc = $ln;
            break;
        }
    }

    // Split by == Section == headings
    $parts = preg_split('/^==\s*(.+?)\s*==/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
    $sections_raw = [];
    for ($i = 1; $i < count($parts); $i += 2) {
        $sections_raw[strtolower(trim($parts[$i]))] = trim($parts[$i + 1] ?? '');
    }

    // Simple markdown-to-HTML converter
    $_toHtml = function($text) {
        if ($text === '') return '';
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/^= (.+?) =$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<!\w)\*([^*\n]+?)\*(?!\w)/', '<em>$1</em>', $text);
        $text = preg_replace('/\[([^\]]+?)\]\((.+?)\)/', '<a href="$2" target="_blank">$1</a>', $text);
        $blocks = preg_split('/\n{2,}/', $text);
        $html = '';
        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '') continue;
            if (strpos($b, '<h3>') === 0) { $html .= $b; continue; }
            if (preg_match('/^[\*\-]\s/m', $b)) {
                $items = explode("\n", $b);
                $html .= '<ul>';
                foreach ($items as $it) {
                    $it = preg_replace('/^[\*\-]\s+/', '', trim($it));
                    if ($it) $html .= '<li>' . $it . '</li>';
                }
                $html .= '</ul>';
                continue;
            }
            if (preg_match('/^\d+\.\s/m', $b)) {
                $items = explode("\n", $b);
                $html .= '<ol>';
                foreach ($items as $it) {
                    $it = preg_replace('/^\d+\.\s+/', '', trim($it));
                    if ($it) $html .= '<li>' . $it . '</li>';
                }
                $html .= '</ol>';
                continue;
            }
            $html .= '<p>' . nl2br($b) . '</p>';
        }
        return $html;
    };

    $sections = [];
    foreach (['description', 'installation', 'changelog', 'faq'] as $key) {
        $src = $sections_raw[$key] ?? ($sections_raw['frequently asked questions'] ?? '');
        if ($key === 'faq' && !isset($sections_raw['faq'])) $src = $sections_raw['frequently asked questions'] ?? '';
        $sections[$key] = trim($_toHtml($src));
    }

    // Screenshots
    $ss_text = $sections_raw['screenshots'] ?? '';
    $ss_html = '';
    if ($ss_text !== '') {
        $plugin_url = plugins_url('', $matched_slug . '/' . $matched_slug . '.php');
        if (preg_match_all('/^\d+\.\s*(.+)$/m', $ss_text, $ssm)) {
            foreach ($ssm[1] as $si => $sd) {
                $num = $si + 1;
                foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
                    $file = 'screenshot-' . $num . '.' . $ext;
                    $path = $plugin_dir . '/assets/screenshots/' . $file;
                    if (!file_exists($path)) continue;
                    $ss_html .= '<div style="margin:12px 0;">';
                    $ss_html .= '<img src="' . esc_url($plugin_url . '/assets/screenshots/' . $file) . '" alt="' . esc_attr(trim($sd)) . '" style="max-width:100%;border:1px solid #ddd;border-radius:4px;" />';
                    $ss_html .= '<p style="font-size:13px;color:#666;">' . esc_html(trim($sd)) . '</p>';
                    $ss_html .= '</div>';
                    break;
                }
            }
        }
    }
    $sections['screenshots'] = $ss_html;

    // Local banner/icon files
    $plugin_url = plugins_url('', $matched_slug . '/' . $matched_slug . '.php');
    $banners = [];
    foreach (['banner-1544x500.png', 'banner-1544x500.jpg', 'banner-1544x500.jpeg'] as $bf) {
        if (file_exists($plugin_dir . '/assets/' . $bf)) {
            $banners['high'] = $plugin_url . '/assets/' . $bf;
            break;
        }
    }
    foreach (['banner-772x250.png', 'banner-772x250.jpg', 'banner-772x250.jpeg'] as $bf) {
        if (file_exists($plugin_dir . '/assets/' . $bf)) {
            $banners['low'] = $plugin_url . '/assets/' . $bf;
            break;
        }
    }

    // Build the info object (no download_url → WP shows "Activate" button)
    $info = (object) [
        'name'              => $plugin_name,
        'slug'              => $matched_slug,
        'version'           => $version,
        'requires'          => $requires,
        'tested'            => $tested,
        'requires_php'      => $requires_php,
        'sections'          => $sections,
        'short_description' => $short_desc,
    ];
    if (!empty($banners)) $info->banners = $banners;

    return $info;
}, 10, 3);
PHP;
        }

        /**
         * Convert WordPress readme.txt markup to HTML.
         */
        private static function readmeToHtml($text) {
            if (empty($text)) return '';

            $text = esc_html($text);

            // Convert = Heading = to <h3>
            $text = preg_replace('/^= (.+?) =$/m', '<h3>$1</h3>', $text);

            // Convert **bold** to <strong>
            $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

            // Convert *italic* to <em> (but not list items)
            $text = preg_replace('/(?<!\w)\*([^*\n]+?)\*(?!\w)/', '<em>$1</em>', $text);

            // Convert [url text] to <a>
            $text = preg_replace('/\[([^\]]+?)\]\((.+?)\)/', '<a href="$2" target="_blank">$1</a>', $text);

            // Process blocks (split by double newlines)
            $blocks = preg_split('/\n{2,}/', $text);
            $html = '';

            foreach ($blocks as $block) {
                $block = trim($block);
                if (empty($block)) continue;

                // Already an h3
                if (strpos($block, '<h3>') === 0) {
                    $html .= $block;
                    continue;
                }

                // Unordered list: lines starting with * or -
                if (preg_match('/^[\*\-]\s/m', $block)) {
                    $items = preg_split('/\n/', $block);
                    $html .= '<ul>';
                    foreach ($items as $item) {
                        $item = preg_replace('/^[\*\-]\s+/', '', trim($item));
                        if ($item) $html .= '<li>' . $item . '</li>';
                    }
                    $html .= '</ul>';
                    continue;
                }

                // Ordered list: lines starting with number.
                if (preg_match('/^\d+\.\s/m', $block)) {
                    $items = preg_split('/\n/', $block);
                    $html .= '<ol>';
                    foreach ($items as $item) {
                        $item = preg_replace('/^\d+\.\s+/', '', trim($item));
                        if ($item) $html .= '<li>' . $item . '</li>';
                    }
                    $html .= '</ol>';
                    continue;
                }

                // Regular paragraph - convert single newlines to <br>
                $html .= '<p>' . nl2br($block) . '</p>';
            }

            return $html;
        }

        /**
         * Parse WordPress readme.txt from plugin directory.
         *
         * @param string $pluginSlug Plugin slug (directory name)
         * @return array Parsed readme sections
         */
        private static function parseReadme($pluginSlug)
        {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $pluginSlug;
            $candidates = ['readme.txt', 'README.txt', 'Readme.txt'];
            $readme_path = '';
            foreach ($candidates as $candidate) {
                $path = $plugin_dir . '/' . $candidate;
                if (file_exists($path)) {
                    $readme_path = $path;
                    break;
                }
            }
            if (empty($readme_path)) {
                return [];
            }

            $content = file_get_contents($readme_path);
            if (!$content) {
                return [];
            }

            // Normalize line endings
            $content = str_replace(["\r\n", "\r"], "\n", $content);

            $result = [
                'description' => '',
                'installation' => '',
                'faq' => '',
                'changelog' => '',
                'screenshots_text' => '',
                'short_description' => '',
            ];

            // Extract short description from header (between === Plugin Name === and first == Section ==)
            if (preg_match('/^===\s*.+?\s*===\s*\n(.*?)(?=^==\s|\z)/ms', $content, $matches)) {
                $header = $matches[1] ?? '';
                $lines = explode("\n", $header);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    // Skip metadata lines like "Contributors:", "Tags:", etc.
                    if (preg_match('/^(Contributors|Tags|Requires|Tested|Stable|License|Donate):/i', $line)) continue;
                    if (empty($result['short_description'])) {
                        $result['short_description'] = $line;
                    }
                }
            }

            // Split by == Section == pattern
            $sections = preg_split('/^==\s*(.+?)\s*==/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

            for ($i = 1; $i < count($sections); $i += 2) {
                $title = strtolower(trim($sections[$i] ?? ''));
                $body = trim($sections[$i + 1] ?? '');

                switch ($title) {
                    case 'description':
                        $result['description'] = $body;
                        break;
                    case 'installation':
                        $result['installation'] = $body;
                        break;
                    case 'frequently asked questions':
                    case 'faq':
                        $result['faq'] = $body;
                        break;
                    case 'changelog':
                        $result['changelog'] = $body;
                        break;
                    case 'screenshots':
                        $result['screenshots_text'] = $body;
                        break;
                }
            }

            return $result;
        }

        /**
         * Parse WordPress readme.txt from theme directory.
         *
         * @param string $themeSlug Theme directory slug
         * @return array Parsed readme sections
         */
        private static function parseThemeReadme($themeSlug)
        {
            $theme_dir = get_theme_root() . '/' . $themeSlug;
            $candidates = ['readme.txt', 'README.txt', 'Readme.txt'];
            $readme_path = '';
            foreach ($candidates as $candidate) {
                $path = $theme_dir . '/' . $candidate;
                if (file_exists($path)) {
                    $readme_path = $path;
                    break;
                }
            }
            if (empty($readme_path)) {
                return [];
            }

            $content = file_get_contents($readme_path);
            if (!$content) {
                return [];
            }

            // Normalize line endings
            $content = str_replace(["\r\n", "\r"], "\n", $content);

            $result = [
                'description' => '',
                'installation' => '',
                'faq' => '',
                'changelog' => '',
                'screenshots_text' => '',
                'short_description' => '',
            ];

            // Extract short description from header (between === Theme Name === and first == Section ==)
            if (preg_match('/^===\s*.+?\s*===\s*\n(.*?)(?=^==\s|\z)/ms', $content, $matches)) {
                $header = $matches[1] ?? '';
                $lines = explode("\n", $header);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    // Skip metadata lines like "Contributors:", "Tags:", etc.
                    if (preg_match('/^(Contributors|Tags|Requires|Tested|Stable|License|Donate):/i', $line)) continue;
                    if (empty($result['short_description'])) {
                        $result['short_description'] = $line;
                    }
                }
            }

            // Split by == Section == pattern
            $sections = preg_split('/^==\s*(.+?)\s*==/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

            for ($i = 1; $i < count($sections); $i += 2) {
                $title = strtolower(trim($sections[$i] ?? ''));
                $body = trim($sections[$i + 1] ?? '');

                switch ($title) {
                    case 'description':
                        $result['description'] = $body;
                        break;
                    case 'installation':
                        $result['installation'] = $body;
                        break;
                    case 'frequently asked questions':
                    case 'faq':
                        $result['faq'] = $body;
                        break;
                    case 'changelog':
                        $result['changelog'] = $body;
                        break;
                    case 'screenshots':
                        $result['screenshots_text'] = $body;
                        break;
                }
            }

            return $result;
        }

        /**
         * Build screenshots HTML from readme.txt Screenshots section.
         *
         * readme.txt format: "1. Screenshot description" per line.
         * Screenshot files: screenshot-1.png, screenshot-2.png, etc. in plugin directory.
         *
         * @param string $text Screenshots section text from readme.txt
         * @param string $slug Plugin slug
         * @return string HTML for screenshots
         */
        private static function buildScreenshotsFromReadme($text, $slug)
        {
            if (empty($text)) return '';

            $plugin_url = function_exists('plugins_url') ? plugins_url('', $slug . '/' . $slug . '.php') : '';
            if (empty($plugin_url)) return '';

            $html = '<div class="plugin-screenshots">';
            $count = 0;

            // Match numbered lines like "1. Description"
            if (preg_match_all('/^\d+\.\s*(.+)$/m', $text, $matches)) {
                foreach ($matches[1] as $i => $desc) {
                    $num = $i + 1;
                    // Try common screenshot file extensions in assets/screenshots/
                    foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
                        $file = 'screenshot-' . $num . '.' . $ext;
                        $path = WP_PLUGIN_DIR . '/' . $slug . '/assets/screenshots/' . $file;
                        if (!file_exists($path)) continue;
                        $url = $plugin_url . '/assets/screenshots/' . $file;
                        $html .= '<div class="screenshot" style="margin:12px 0;">';
                        $html .= '<img src="' . esc_url($url) . '" alt="' . esc_attr(trim($desc)) . '" style="max-width:100%;border:1px solid #ddd;border-radius:4px;" />';
                        $html .= '<p style="font-size:13px;color:#666;">' . esc_html(trim($desc)) . '</p>';
                        $html .= '</div>';
                        $count++;
                        break;  // Use first matching extension
                    }
                }
            }

            $html .= '</div>';
            return $count > 0 ? $html : '';
        }

        /**
         * Build screenshots HTML from readme.txt Screenshots section for themes.
         *
         * @param string $text Screenshots section text from readme.txt
         * @param string $slug Theme directory slug
         * @return string HTML for screenshots
         */
        private static function buildThemeScreenshotsFromReadme($text, $slug)
        {
            if (empty($text)) return '';

            $theme_url = function_exists('get_theme_root_uri') ? get_theme_root_uri() . '/' . $slug : '';
            if (empty($theme_url)) return '';

            $theme_path = function_exists('get_theme_root') ? get_theme_root() . '/' . $slug : '';
            if (empty($theme_path)) return '';

            $html = '<div class="plugin-screenshots">';
            $count = 0;

            // Match numbered lines like "1. Description"
            if (preg_match_all('/^\d+\.\s*(.+)$/m', $text, $matches)) {
                foreach ($matches[1] as $i => $desc) {
                    $num = $i + 1;
                    // Try common screenshot file extensions in assets/screenshots/
                    foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
                        $file = 'screenshot-' . $num . '.' . $ext;
                        $path = $theme_path . '/assets/screenshots/' . $file;
                        if (!file_exists($path)) continue;
                        $url = $theme_url . '/assets/screenshots/' . $file;
                        $html .= '<div class="screenshot" style="margin:12px 0;">';
                        $html .= '<img src="' . esc_url($url) . '" alt="' . esc_attr(trim($desc)) . '" style="max-width:100%;border:1px solid #ddd;border-radius:4px;" />';
                        $html .= '<p style="font-size:13px;color:#666;">' . esc_html(trim($desc)) . '</p>';
                        $html .= '</div>';
                        $count++;
                        break;  // Use first matching extension
                    }
                }
            }

            $html .= '</div>';
            return $count > 0 ? $html : '';
        }

        /**
         * Build screenshots HTML for plugin details popup (from API URLs).
         */
        private static function buildScreenshotsHtml($screenshots)
        {
            if (empty($screenshots) || !is_array($screenshots)) return '';
            $html = '<ul class="screenshots">';
            foreach ($screenshots as $i => $url) {
                $url = is_array($url) ? ($url['url'] ?? '') : $url;
                if (!$url) continue;
                $num = $i + 1;
                $html .= '<li><img src="' . esc_url($url) . '" alt="Screenshot ' . $num . '" class="screenshot" /></li>';
            }
            $html .= '</ul>';
            return $html;
        }

        /**
         * Build reviews summary HTML for plugin details popup.
         * Renders custom star distribution with links pointing to the platform product page,
         * replacing WP core's hardcoded wordpress.org star links.
         */
        private static function buildReviewsHtml($update)
        {
            $reviews = $update['reviews'] ?? [];
            $avgRating = $update['average_rating'] ?? 0;
            $reviewCount = $update['review_count'] ?? count($reviews);

            if ($reviewCount <= 0 && empty($reviews)) {
                return '';
            }

            // Build star distribution
            $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
            foreach ($reviews as $r) {
                $star = (int)($r['rating'] ?? 0);
                if ($star >= 1 && $star <= 5) $dist[$star]++;
            }

            $html = '<div class="plugin-reviews">';

            // Review cards only (no summary - sidebar already shows average rating)
            foreach ($reviews as $review) {
                $author  = esc_html($review['author'] ?? 'Anonymous');
                $rating  = (int)($review['rating'] ?? 0);
                $date    = esc_html($review['date'] ?? '');
                $content = esc_html($review['content'] ?? '');
                $rStars  = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

                $html .= '<div class="licenseos-review-card">';
                $html .= '<div class="licenseos-review-head">';
                $html .= '<strong class="licenseos-reviewer">' . $author . '</strong>';
                $html .= '<span class="licenseos-review-stars">' . $rStars . '</span>';
                if ($date) {
                    $html .= '<span class="licenseos-review-date">' . $date . '</span>';
                }
                $html .= '</div>';
                if ($content) {
                    $html .= '<p class="licenseos-review-text">' . $content . '</p>';
                }
                // Render developer replies
                if (!empty($review['replies']) && is_array($review['replies'])) {
                    foreach ($review['replies'] as $reply) {
                        $replyAuthor  = esc_html($reply['author'] ?? 'Developer');
                        $replyContent = esc_html($reply['content'] ?? '');
                        $replyDate    = esc_html($reply['date'] ?? '');
                        if ($replyContent) {
                            $html .= '<div class="licenseos-review-reply" style="margin-left:30px;padding:10px;background:#f8f9fa;border-left:3px solid #0073aa;border-radius:4px;margin-top:8px;">';
                            $html .= '<strong>' . $replyAuthor . '</strong>';
                            if ($replyDate) {
                                $html .= ' <span style="color:#666;font-size:12px;">' . $replyDate . '</span>';
                            }
                            $html .= '<p style="margin:6px 0 0;">' . $replyContent . '</p>';
                            $html .= '</div>';
                        }
                    }
                }
                $html .= '</div>';
            }

            $html .= '</div>';
            return $html;
        }

        private static function request($path, $data)
        {
            $headers = [];
            if (self::$api_key) {
                $headers['X-API-KEY'] = self::$api_key;
            }

            $res = wp_remote_post(self::$api . $path, [
                'body' => $data,
                'headers' => $headers,
                'timeout' => 15,
            ]);

            if (is_wp_error($res)) {
                return ['status' => 'error', 'message' => $res->get_error_message(), '_http_error' => true, '_http_code' => 0];
            }

            $code = wp_remote_retrieve_response_code($res);
            $body = json_decode(wp_remote_retrieve_body($res), true);

            if ($code >= 400) {
                return array_merge($body ?? [], ['_http_error' => true, '_http_code' => $code]);
            }

            return $body ?? [];
        }

        private static function requestGet($path, $params = [])
        {
            $url = self::$api . $path;
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            $headers = [];
            if (self::$api_key) {
                $headers['X-API-KEY'] = self::$api_key;
            }

            $res = wp_remote_get($url, [
                'headers' => $headers,
                'timeout' => 15,
            ]);

            if (is_wp_error($res)) {
                return null;
            }

            return json_decode(wp_remote_retrieve_body($res), true);
        }
    }
}
