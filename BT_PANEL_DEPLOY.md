# LicenseOS 宝塔面板部署教程

> Laravel 11 + Filament 3.2 + PHP 8.3  
> 上传代码后，严格按以下步骤操作

---

## 一、宝塔环境准备（仅首次需要）

### 1.1 安装软件

> 宝塔 → 软件商店

| 软件            | 版本  |
| ------------- | --- |
| Nginx         | 最新  |
| MySQL         | 8.0 |
| PHP           | 8.3 |
| Redis         | 最新  |
| Supervisor管理器 | 最新  |

### 1.2 PHP 扩展

> 软件商店 → PHP-8.3 → 设置 → 安装扩展

必装：**fileinfo、redis、opcache、bcmath、zip**

### 1.3 PHP 配置

> PHP 设置 → 配置修改，找到并修改：

```
memory_limit = 256M
max_execution_time = 60
post_max_size = 64M
upload_max_filesize = 64M
max_input_vars = 5000
display_errors = Off
```

保存 → 点「重载配置」

---

## 二、创建网站

> 宝塔 → 网站 → 添加站点

| 字段  | 值                             |
| --- | ----------------------------- |
| 域名  | 你的域名                          |
| 数据库 | MySQL，名称 `licenseos`，**记下密码** |
| PHP | PHP-8.3                       |

### ⚠️ 设置运行目录

> 站点设置 → 网站目录 → 运行目录 → 选 **/public** → 保存

---

## 三、上传代码

上传 ZIP 到站点根目录 → 解压 → 删除默认 index.html

确保目录结构正确（根目录下有 app/、public/、composer.json 等）

---

## 四、配置 .env（必须在 Composer 前完成）

> ⚠️ **必须在安装依赖前完成**，否则 Composer 的 `package:discover` 会因缺少 APP_KEY 报错

### 4.1 复制配置文件

```bash
cp /www/wwwroot/你的站点目录/.env.example /www/wwwroot/你的站点目录/.env
```

### 4.2 编辑 .env

> 宝塔 → 文件 → 双击 `.env` 编辑

**找到并修改以下 3 处（其余不动）：**

| 行              | 改前                        | 改后               |
| -------------- | ------------------------- | ---------------- |
| `APP_URL=`     | `https://your-domain.com` | `https://你的实际域名` |
| `DB_PASSWORD=` | （空）                       | `宝塔创建数据库时的密码`    |
| `DB_USERNAME=` | `licenseos`               | `宝塔数据库用户名`（如果不同） |

保存。

### 4.3 生成 APP_KEY

```bash
cd /www/wwwroot/你的站点目录 && /www/server/php/83/bin/php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

复制输出的密钥 → 宝塔编辑 `.env` → 找到 `APP_KEY=` → 粘贴：

```
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## 五、安装依赖（生成 vendor/）

> 在宝塔终端逐条执行

### 5.1 下载 Composer

```bash
cd /www/wwwroot/你的站点目录 && wget https://mirrors.aliyun.com/composer/composer.phar -O composer.phar && chmod +x composer.phar
```

### 5.2 配置国内镜像

```bash
cd /www/wwwroot/你的站点目录 && COMPOSER_ALLOW_SUPERUSER=1 /www/server/php/83/bin/php -d opcache.jit=disable composer.phar config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

### 5.3 安装依赖

```bash
cd /www/wwwroot/你的站点目录 && COMPOSER_ALLOW_SUPERUSER=1 /www/server/php/83/bin/php -d memory_limit=512M -d opcache.jit=disable composer.phar update --optimize-autoloader --no-dev --no-interaction --no-scripts
```

> 看到 `Generating optimized autoload files` 表示成功，`vendor/` 目录自动生成
> 
> ⚠️ 如果出现 `make() on null` 错误，说明 .env 未配置好（缺 APP_KEY），先完成第四步再重新执行本步

---

## 六、数据库迁移 + 初始化（使用 PHP 脚本，绕过 artisan）

> ⚠️ Laravel 11.44.7 的 artisan 命令存在 `make() on null` 已知问题，以下所有操作均使用 **heredoc PHP 脚本** 替代 artisan
> 
> ⚠️ 所有 PHP 脚本必须用 `cat > /tmp/xxx.php << 'PHPEOF'` 方式写入文件再执行，**禁止用 `php -r`**（bash 会吞掉 `$` 和 `!` 符号）

### 6.1 一键迁移 + 初始化

> 将以下整段复制粘贴到宝塔终端，一次完成所有操作：

```bash
cat > /tmp/deploy_init.php << 'PHPEOF'
<?php
require '/www/wwwroot/你的站点目录/vendor/autoload.php';
$app = require '/www/wwwroot/你的站点目录/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// === 前置步骤：在 bootstrap 之前用 raw PDO 建好 cache 表 ===
// 否则 AppServiceProvider::boot() 会因读 cache 表报错
$envFile = '/www/wwwroot/你的站点目录/.env';
$envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($envLines as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port=" . ($env['DB_PORT'] ?? 3306) . ";dbname={$env['DB_DATABASE']}",
    $env['DB_USERNAME'], $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec('CREATE TABLE IF NOT EXISTS cache (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    value MEDIUMTEXT NOT NULL,
    expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE IF NOT EXISTS cache_locks (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
echo "0. cache tables pre-created (raw PDO)\n";

// === 前置步骤：确保 storage 目录存在 ===
// 否则 BladeIconsServiceProvider::boot() 会因 views 缓存路径不存在报错
$baseDir = '/www/wwwroot/你的站点目录';
$dirs = [
    "$baseDir/storage/framework/views",
    "$baseDir/storage/framework/cache",
    "$baseDir/storage/framework/sessions",
    "$baseDir/storage/app/public",
    "$baseDir/storage/logs",
    "$baseDir/bootstrap/cache",
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}
echo "0.1 storage directories ensured\n";

$kernel->bootstrap();
$db = $app->make('db');

$db->statement('CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
echo "1. migrations table OK\n";

$publicStorage = '/www/wwwroot/你的站点目录/public/storage';
if (!file_exists($publicStorage)) {
    symlink('/www/wwwroot/你的站点目录/storage/app/public', $publicStorage);
    echo "2. storage link created\n";
} else {
    echo "2. storage link exists\n";
}

$migrator = $app->make('migrator');
$migrator->setOutput(new Symfony\Component\Console\Output\ConsoleOutput());
$migrator->run(database_path('migrations'));
echo "3. migrations complete\n";

$existing = $db->table('users')->where('email', 'admin@licenseos.com')->first();
if (!$existing) {
    $tenant = $db->table('tenants')->first();
    if (!$tenant) {
        $tenantId = $db->table('tenants')->insertGetId([
            'name' => 'LicenseOS', 'email' => 'admin@licenseos.com',
            'commission_rate' => 10, 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    } else { $tenantId = $tenant->id; }
    $db->table('users')->insertGetId([
        'name' => 'Admin', 'email' => 'admin@licenseos.com',
        'password' => password_hash('123456', PASSWORD_BCRYPT),
        'role' => 'super_admin', 'tenant_id' => $tenantId,
        'email_verified_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    echo "4. admin user created\n";
} else { echo "4. admin user exists\n"; }

echo "\n=== All done! Login: admin@licenseos.com / 123456 ===\n";
PHPEOF
/www/server/php/83/bin/php -d opcache.jit=disable /tmp/deploy_init.php
```

> ⚠️ 执行前必须将脚本中 **两处** `你的站点目录` 替换为实际路径（如 `sz.djkz.top`）
> 
> 如果迁移中途报错 `Duplicate column` 或 `Table already exists`，说明迁移部分执行过。
> 再次运行本脚本即可（幂等安全），已完成的迁移会自动跳过。

---

## 七、目录权限（必须设置）

在宝塔**文件管理器**中，右键以下两个文件夹 → **权限** → 设为 **775**，并勾选**应用到子目录**：

1. `storage/` 文件夹
2. `bootstrap/cache/` 文件夹

同时确认所有者为 `www`（文件列表的"所有者"列应显示 `www`，否则右键 → **所有者** 改为 `www`）。

### 7.5 建立存储链接（仅首次需要）

> 如果 Logo/Favicon 使用上传模式，需确保已建立存储链接：

```bash
cd /www/wwwroot/你的站点目录 && /www/server/php/83/bin/php artisan storage:link
```

> 此命令只需执行一次。执行后 `public/storage` 会链接到 `storage/app/public`，上传的文件才能通过 URL 访问。

---

## 八、发布前端资产（必须执行，共 3 步）

> 宝塔终端依次执行以下 3 个脚本，解决：后台 UI 无样式、登录无反应、组件加载失败

### 8.1 发布 Filament CSS/JS

```bash
cat > /tmp/publish_filament.php << 'PHPEOF'
<?php
$vendorBase = '/www/wwwroot/你的站点目录/vendor/filament';
$jsBase = '/www/wwwroot/你的站点目录/public/js/filament';
$cssBase = '/www/wwwroot/你的站点目录/public/css/filament';
$distDirs = glob($vendorBase . '/*/dist', GLOB_ONLYDIR);
$jsCount = 0;
$cssCount = 0;
foreach ($distDirs as $distDir) {
    $package = basename(dirname($distDir));
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($distDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $ext = $file->getExtension();
        if ($ext === 'js') {
            $targetDir = $jsBase . '/' . $package;
        } elseif ($ext === 'css') {
            $targetDir = $cssBase . '/' . $package;
        } else {
            continue;
        }
        $relativePath = str_replace($distDir . '/', '', $file->getPathname());
        $targetPath = $targetDir . '/' . $relativePath;
        @mkdir(dirname($targetPath), 0755, true);
        if (copy($file->getPathname(), $targetPath)) {
            if ($ext === 'js') $jsCount++; else $cssCount++;
        }
    }
}
echo "Filament: {$cssCount} CSS + {$jsCount} JS files published\n";
PHPEOF
/www/server/php/83/bin/php /tmp/publish_filament.php
```

### 8.2 修复 Filament 资产文件名（Livewire 登录依赖此步）

> Filament 注册器用 `support.js` 等名称加载，但 dist 目录中的文件叫 `index.js`。此脚本自动修正。

```bash
cat > /tmp/fix_asset_names.php << 'PHPEOF'
<?php
$base = '/www/wwwroot/你的站点目录/public';
$jsMap = [
    "$base/js/filament/support/index.js" => "$base/js/filament/support/support.js",
    "$base/js/filament/filament/index.js" => "$base/js/filament/filament/app.js",
    "$base/js/filament/notifications/index.js" => "$base/js/filament/notifications/notifications.js",
    "$base/js/filament/forms/index.js" => "$base/js/filament/forms/forms.js",
    "$base/js/filament/tables/index.js" => "$base/js/filament/tables/tables.js",
];
$cssMap = [
    "$base/css/filament/support/index.css" => "$base/css/filament/support/support.css",
    "$base/css/filament/filament/theme.css" => "$base/css/filament/filament/app.css",
    "$base/css/filament/forms/index.css" => "$base/css/filament/forms/forms.css",
];
foreach (array_merge($jsMap, $cssMap) as $src => $dst) {
    if (file_exists($src) && !file_exists($dst)) {
        copy($src, $dst);
        echo "Fixed: " . basename($dst) . "\n";
    }
}
echo "Asset names fixed\n";
PHPEOF
/www/server/php/83/bin/php /tmp/fix_asset_names.php
```

### 8.3 发布 Livewire JS

> Livewire 是 Filament 表单交互的核心，缺少此步会导致登录按钮无反应。

```bash
cat > /tmp/publish_livewire.php << 'PHPEOF'
<?php
$src = '/www/wwwroot/你的站点目录/vendor/livewire/livewire/dist';
$dst = '/www/wwwroot/你的站点目录/public/vendor/livewire';
@mkdir($dst, 0755, true);
$count = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    $relativePath = str_replace($src . '/', '', $file->getPathname());
    $targetPath = $dst . '/' . $relativePath;
    @mkdir(dirname($targetPath), 0755, true);
    if (copy($file->getPathname(), $targetPath)) {
        $count++;
    }
}
echo "Livewire: $count files published\n";
PHPEOF
/www/server/php/83/bin/php /tmp/publish_livewire.php
```

> ⚠️ 以上 3 个脚本中的 `你的站点目录` 都必须替换为实际路径

### 8.4 清除缓存

> 更新代码或迁移后必须执行，确保路由和配置缓存为最新状态。

```bash
cat > /tmp/clear_cache.php << 'PHPEOF'
<?php
require '/www/wwwroot/你的站点目录/vendor/autoload.php';
$app = require '/www/wwwroot/你的站点目录/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 删除无效的路由缓存（防止首页 404）
$cachePath = $app->getCachedRoutesPath();
if (file_exists($cachePath)) {
    unlink($cachePath);
    echo "Deleted route cache: $cachePath\n";
} else {
    echo "No route cache found\n";
}

// 清除应用缓存
$app->make('cache')->flush();
echo "Application cache flushed\n";
echo "Done!\n";
PHPEOF
/www/server/php/83/bin/php -d opcache.jit=disable /tmp/clear_cache.php
```

> 输出 `Application cache flushed` 表示成功

---

## 九、配置伪静态

> 站点设置 → 伪静态 → 粘贴 → 保存
> 
> ⚠️ **禁止写 `$uri/`**，否则 Livewire POST 请求会被 Nginx 转换为 GET 导致 405 错误

```nginx
location / {
    try_files $uri /index.php?$query_string;
}
```

---

## 十、配置 SSL

> 站点设置 → SSL → Let's Encrypt → 勾选域名 → 申请 → 开启「强制 HTTPS」

---

## 十一、验证网站是否正常

浏览器访问：

| URL                   | 预期                        |
| --------------------- | ------------------------- |
| `https://你的域名`        | 看到首页                      |
| `https://你的域名/admin`  | 看到登录页面                    |
| `https://你的域名/health` | 看到 `{"status":"healthy"}` |

> 管理员账号：`admin@example.com` / `123456`（登录后请立即修改密码）

**重置密码命令（如果忘记密码或密码失效）：**

```bash
# 重置默认管理员密码为 123456
/www/server/php/83/bin/php artisan admin:reset-password

# 重置指定邮箱用户的密码
/www/server/php/83/bin/php artisan admin:reset-password your@email.com newpassword123
```

---

## 十二、站点初始化配置

> 部署完成后，进入后台 **系统设置** 完成以下初始配置。

### 12.1 站点名称

进入 **系统设置 → 基本设置 → 站点名称**，填写你的品牌名。

| 填写方式        | 中文界面 | 英文界面           | 说明           |
| ----------- | ---- | -------------- | ------------ |
| `数字刻栈`      | 数字刻栈 | 数字刻栈           | 纯文本，中英显示相同名称 |
| `site.name` | 数字刻栈 | Digital KeZhan | 翻译键，中英显示不同名称 |

**如何使用翻译键实现中英双语品牌名：**

1. 后台站点名称填 `site.name`
2. 编辑翻译文件：
   - `lang/zh_CN.json` 中找到 `"site.name"` 改为你的中文名
   - `lang/en.json` 中找到 `"site.name"` 改为你的英文名
3. 清缓存生效

> 翻译文件路径：`lang/zh_CN.json` 和 `lang/en.json`，搜索 `"site.name"` 即可定位。

### 12.2 其他必做配置

| 配置项            | 位置              | 说明                                  |
| -------------- | --------------- | ----------------------------------- |
| Logo / Favicon | 系统设置 → 基本设置     | 直接上传图片（PNG/SVG/ICO），保存后立即生效         |
| 预加载动画          | 系统设置 → 基本设置     | 选择动画类型或上传自定义图像，页面加载时显示全屏遮罩          |
| SEO 标题/描述/关键词  | 系统设置 → 搜索引擎优化   | 支持翻译键（如 `seo.title`）或纯文本，详见 12.3    |
| 页脚版权文字         | 系统设置 → 页脚       | 支持 `{year}`、`{site_name}`、`<br>` 换行 |
| 联系邮箱/电话        | 系统设置 → 联系方式     | 显示在联系页面                             |
| 微信支付           | 系统设置 → 微信支付     | AppID、商户号、API密钥                     |
| 支付宝            | 系统设置 → 支付宝      | AppID、私钥、公钥                         |
| Stripe         | 系统设置 → Stripe支付 | API Key（国际用户）                       |
| 导航菜单           | 后台 → 导航菜单       | 标签支持翻译键（如 `nav.home`）               |

> 导航菜单标签同样支持翻译键。例如标签填 `nav.home`，中英文自动显示“首页”和“Home”。
> 翻译键格式：`nav.home`、`nav.marketplace`、`nav.faq`、`nav.contact`，对应翻译文件中已有的键值。

### 12.3 SEO 配置详解（翻译键模式）

SEO 字段支持两种填写方式：

| 填写方式 | 示例            | 效果            |
| ---- | ------------- | ------------- |
| 纯文本  | `数字刻栈 - 插件市场` | 中英文显示相同内容     |
| 翻译键  | `seo.title`   | 中英文显示不同内容（推荐） |

**使用翻译键实现中英双语 SEO：**

1. 后台系统设置 → 搜索引擎优化，三个字段分别填写：
   - SEO 标题：`seo.title`
   - Meta 描述：`seo.description`
   - Meta 关键词：`seo.keywords`
2. 编辑翻译文件修改实际内容：
   - `lang/zh_CN.json` 搜索 `"seo.title"` → 改为你想要的中文标题
   - `lang/en.json` 搜索 `"seo.title"` → 改为你想要的英文标题
   - 同理修改 `seo.description` 和 `seo.keywords`
3. 清缓存生效

> 页脚版权文字（`footer_copyright`）同样支持翻译键，格式与 SEO 相同。例如填 `footer.copyright`，在翻译文件中定义中英文版本。

---

## 附录 A：Supervisor 队列（后台任务，可选）

> 软件商店 → Supervisor管理器 → 设置 → 添加守护进程

| 名称            | 命令                                                                                                                                           | 目录   | 进程数 | 用户  |
| ------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | ---- | --- | --- |
| queue-default | `/www/server/php/83/bin/php /www/wwwroot/你的站点目录/artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=60 --memory=128`      | 站点目录 | 2   | www |
| queue-high    | `/www/server/php/83/bin/php /www/wwwroot/你的站点目录/artisan queue:work redis --queue=high,default --sleep=1 --tries=5 --timeout=90 --memory=256` | 站点目录 | 2   | www |

---

## 附录 B：定时任务（可选）

> 宝塔 → 计划任务 → Shell 脚本 → 每 1 分钟

```bash
cd /www/wwwroot/你的站点目录 && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## 附录 C：版本更新

> 更新代码后，在宝塔终端依次执行：

**第 1 步：Composer + 迁移**

```bash
cd /www/wwwroot/你的站点目录 && COMPOSER_ALLOW_SUPERUSER=1 /www/server/php/83/bin/php -d memory_limit=512M -d opcache.jit=disable composer.phar update --optimize-autoloader --no-dev --no-interaction --no-scripts
```

然后运行第六章 6.1 的迁移脚本（复制整段执行）。

**第 2 步：前端资产发布**（第八章 8.1 + 8.2 + 8.3，依次执行三个脚本）

**第 3 步：清缓存**

执行 8.4 清除缓存脚本（删除旧路由缓存 + 刷新应用缓存）。

---

## 常见问题

| 错误                      | 解决                                                                                  |
| ----------------------- | ----------------------------------------------------------------------------------- |
| 403 Forbidden           | 运行目录没设为 /public → 站点设置修改                                                            |
| vendor/autoload.php 不存在 | 第五章 Composer 命令没成功 → 重新执行                                                           |
| make() on null          | Composer 缺少 `--no-scripts` 参数，Laravel 11.44.7 已知 bug，本教程已加 `--no-scripts`；如仍报错见附录 E |
| MissingAppKeyException  | 4.3 没执行 → 重新生成 APP_KEY                                                              |
| 500 Permission denied   | 第七章没执行 → 重新执行权限命令                                                                   |
| 首页 404（其他页面正常）          | 路由缓存损坏 → 执行 8.4 清除缓存脚本                                                              |
| 所有页面都 404               | 第九章伪静态没配 → 粘贴 try_files 规则                                                          |
| 数据库连接失败                 | DB_HOST 必须是 `127.0.0.1`（不是 db）                                                      |
| Composer 卡住             | 必须加 `COMPOSER_ALLOW_SUPERUSER=1`                                                    |
| 后台搜索/删除/编辑转圈不停          | 伪静态规则含 `$uri/` → 改为 `try_files $uri /index.php?$query_string;`                      |
| 后台 UI 无样式/纯 HTML        | 8.1 Filament CSS/JS 资产未发布 → 重新执行发布脚本                                                |
| 后台登录按钮无反应               | 8.2/8.3 未执行 → Livewire JS 缺失或 Filament 资产文件名未修复                                     |
| 登录报 "凭证不匹配"             | 6.1 创建管理员脚本未执行 → 重新运行部署初始化脚本                                                        |
| 后台按钮 SVG 图标持续旋转         | `FilamentServiceProvider.php` 中的 CSS renderHook 未部署 → 上传更新文件                        |
| Livewire 405 错误         | 伪静态规则含 `$uri/` → 删除 `$uri/`，见第九章                                                    |
| 迁移报 Duplicate column    | 再次运行 6.1 脚本即可（幂等安全）                                                                 |

---

## 附录 D：重新部署 / 迁移服务器 FAQ

### Q1：备份恢复后还需要 SSH 操作吗？

**需要。** 每次重新部署都必须执行以下步骤：

| 操作          | 何时需要          | 参考章节        |
| ----------- | ------------- | ----------- |
| Composer 安装 | vendor/ 不存在时  | 第五章         |
| 数据库迁移 + 初始化 | **每次必须**      | 第六章 6.1 脚本  |
| 目录权限        | **每次必须**      | 第七章         |
| 前端资产发布      | **每次必须**（3 步） | 第八章 8.1-8.3 |

### Q2：快速重新部署命令

> 将以下 4 步复制到宝塔终端依次执行（已包含完整备份恢复场景）：

**第 1 步：数据库迁移 + 初始化**（即第六章 6.1 的脚本，复制整段执行）

**第 2 步：目录权限**（即第七章，在宝塔文件管理器中设置 `storage/` 和 `bootstrap/cache/` 权限为 775，所有者为 www）

**第 3 步：发布前端资产**（即第八章 8.1 + 8.2 + 8.3，依次执行三个脚本）

**第 4 步：清缓存**

```bash
rm -f /www/wwwroot/你的站点目录/bootstrap/cache/config.php /www/wwwroot/你的站点目录/bootstrap/cache/routes-v7.php /www/wwwroot/你的站点目录/bootstrap/cache/services.php /www/wwwroot/你的站点目录/bootstrap/cache/packages.php
```

### Q3：登录提示 "These credentials do not match our records" 但邮箱没错

这是密码哈希损坏导致的，在 Admin 面板修改邮箱后可能发生。执行以下命令重置密码：

```bash
/www/server/php/83/bin/php artisan admin:reset-password sz@djkz.top 123456
```

将 `sz@djkz.top` 替换为你的实际登录邮箱，`123456` 替换为你想设置的新密码。

### Q4：完整备份包含哪些内容？

| 备份项        | 路径                              | 重要性   |
| ---------- | ------------------------------- | ----- |
| 网站代码       | 整个站点目录（含 `vendor/`）             | ★★★★★ |
| 数据库        | MySQL 导出 `.sql`                 | ★★★★★ |
| `.env` 文件  | 站点根目录                           | ★★★★★ |
| `storage/` | `storage/` 目录（含 sessions、logs）  | ★★★★  |
| SSL 证书     | `/www/server/panel/vhost/cert/` | ★★★   |

---

## 附录 E：开发者接入授权教程

> LicenseOS 提供 API 接口，开发者可将授权验证集成到各类软件产品中。

### E.1 获取 API Key

1. 登录 `https://你的平台域名/developer`
2. 左侧菜单 **API Keys** → **Generate New Key**
3. 复制生成的 API Key（仅显示一次，请妥善保存）

> **注意事项：**
> 
> - API Key 在开发者后台（`/developer`）的 **API Keys** 页面生成与管理
> - API Key 是调用 LicenseOS 所有接口的唯一凭证，请妥善保管，切勿泄露
> - 如 Key 遗失或泄露，可在后台随时撤销并重新生成新 Key

---

### E.2 WordPress 插件

> 本教程将引导你完成 LicenseOS SDK 接入 WordPress 插件的全部步骤。

**涉及文件（共 1 个需要编辑）：**

| 文件                     | 操作  | 用途               |
| ---------------------- | --- | ---------------- |
| `your-plugin-slug.php` | 编辑  | 插件主文件，添加初始化和授权代码 |

> SDK 文件（`licenseos-sdk.php`）由平台一键注入自动添加到插件 ZIP 中，无需手动复制。

---

#### 插件主代码块（平台自动注入，无需手动填写）

在创建产品时上传 ZIP，平台会自动注入以下初始化代码：

```php
// === LicenseOS Initialization Code ===
define('LICENSEOS_API_URL', 'https://your-platform.com/api/v2');
define('LICENSEOS_API_KEY', 'losk_xxxxxxxxxxxx');
define('LICENSEOS_PRODUCT_SLUG', 'your-plugin-slug');

add_action('plugins_loaded', function() {
    LicenseOS::init(LICENSEOS_API_URL, LICENSEOS_API_KEY, __DIR__);
});
// === End LicenseOS Initialization Code ===
```

> **重要提示**：以上代码已包含 SDK 加载、API Key 定义、LicenseOS::init() 调用等所有内容，下载后直接安装即可使用。

---

#### 开发者额外任务（仅需一处）

为了提供许可证激活界面，你只需编写一个简单的设置表单页面：

```php
// 在你的插件设置页面中添加这个表单
<form method="post">
    @csrf

    <label><?php __('许可证激活'); ?></label>
    <input type="text" name="license_key" placeholder="输入你的许可证密钥" required>
    <button type="submit" name="activate_license"><?php __('激活许可证'); ?></button>
</form>

<?php
if (isset($_POST['activate_license'])) {
    $result = activate_license($_POST['license_key']);
    if ($result) {
        echo '<div class="notice notice-success"><p>' . __('许可证激活成功！') . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . __('激活失败，请检查密钥是否正确。') . '</p></div>';
    }
}

function activate_license($license_key) {
    $product_slug = get_option('licenseos_product_slug') ?: 'your-plugin-slug';
    $result = LicenseOS::activate(
        $license_key, 
        $_SERVER['HTTP_HOST'],
        $product_slug
    );

    if (($result['status'] ?? '') === 'success') {
        update_option('your_plugin_name_license_key', $license_key);
        update_option('your_plugin_name_license_status', 'valid');
        return true;
    }
    return false;
}
```

---

#### 选项名称建议

默认情况下，平台会使用产品名称生成存储选项名。如果你看到类似这些字段，说明使用了平台推荐格式：

- `your_plugin_name_license_key` - 存储许可证密钥
- `your_plugin_name_license_status` - 存储许可证状态（valid/invalid）

如果想自定义，可以在主文件顶部添加：

```php
define('MY_CUSTOM_OPTION_PREFIX', 'my_custom_name_');
```

然后在上述函数中将 `'your_plugin_name_license_key'` 替换为 `'MY_CUSTOM_OPTION_PREFIX_license_key'`。

---

#### 测试清单

完成开发后，请确认以下几点：

- ✅ 下载原始 ZIP 并解压
- ✅ 查看主文件是否包含 `=== LicenseOS Initialization Code ===` 注释块
- ✅ 确认有 `require_once 'licenseos-sdk.php'` 或类似语句
- ✅ 上传到 WordPress，插件应该可以正常工作
- ✅ 访问产品详情页查看生成的函数参考代码
- ✅ 在插件设置页尝试激活许可证

---

#### 安全保护

平台一键注入时会自动处理以下安全机制：

| 机制              | 说明                                                             |
| --------------- | -------------------------------------------------------------- |
| SDK 文件注入        | 自动将 `licenseos-sdk.php` 注入到插件 ZIP 中                            |
| Integrity Guard | 注入到插件主文件，独立检测 SDK 引用和 `LicenseOS::init` 调用是否被删除或篡改，被删后插件功能自动失效 |
| 二级交叉校验          | 在 includes/src 等子目录文件中注入交叉校验，检测主文件保护代码是否被删除                    |
| mu-plugin 守护    | SDK 激活许可证后自动安装 `mu-plugin`，持续监控所有已注册插件的完整性                     |

**自动更新与查看详情**：

| 位置       | 显示内容                         |
| -------- | ---------------------------- |
| 插件列表     | `有可用更新。查看版本 x.x.x 详情 或 立即更新` |
| 点击「查看详情」 | 弹窗显示：更新日志、描述、截图、评分、版本号       |
| 点击「立即更新」 | WP 自动下载 ZIP → 解压替换 → 更新完成    |
| 插件行底部    | `Licensed via LicenseOS` 标记  |

**激活管理界面**：

- 开发者需要在插件设置页添加一个简洁的许可证激活表单
- 表单调用 `LicenseOS::activate()` API 即可
- 成功后自动启用自动更新和安全防护功能

---

### E.3 WordPress 主题

> 本教程将引导你完成 LicenseOS SDK 接入 WordPress 主题的全部步骤。

**涉及文件（共 2 个需要编辑）：**

| 文件              | 操作  | 用途                    |
| --------------- | --- | --------------------- |
| `style.css`     | 编辑  | 主题头部元数据（WordPress 必须） |
| `functions.php` | 编辑  | 添加初始化和授权代码            |

> SDK 文件（`licenseos-sdk.php`）由平台一键注入自动添加到主题 ZIP 中，无需手动复制。

---

#### 步骤 1：编辑 style.css 头部

打开 `style.css`，确认顶部有以下格式（按需修改）：

```css
/*
Theme Name: Your Theme Name               ← 改成你的主题名
Theme URI: https://example.com/your-theme  ← 改成你的主题地址
Description: Your theme description here.  ← 改成你的主题简介
Version: 1.0.0                             ← 改成你的当前版本号
Author: Your Name                          ← 改成你的作者名
Author URI: https://example.com            ← 改成你的作者地址
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: your-theme-slug               ← 改成你的主题 slug
Tags: blog, responsive, custom-colors
*/
```

---

#### 步骤 2：编辑 functions.php

打开 `functions.php`，在文件顶部添加以下代码：

```php
<?php
if (!defined('ABSPATH')) exit;

require_once get_template_directory() . '/licenseos-sdk.php';

add_action('after_setup_theme', function () {

    LicenseOS::init(
        'https://your-licenseos-domain.com/api/v2',  // ← 改成你的平台地址
        'losk_your_api_key_here',                     // ← 改成你的 API Key
        get_template_directory()
    );

    $license_key = get_option('your_theme_license_key', ''); // ← 改成你的 option 名，与下方保持一致
    if ($license_key) {
        LicenseOS::enableThemeAutoUpdate(
            'your-theme-slug',  // ← 改成你的主题 slug（与目录名一致）
            $license_key
        );
    }

    LicenseOS::enableThemeLicenseGate([
        'slug'        => 'your-theme-slug',            // ← 改成你的主题 slug
        'name'        => 'Your Theme Name',            // ← 改成你的主题名
        'theme_dir'   => 'your-theme-slug',            // ← 改成你的主题目录名
        'option_key'  => 'your_theme_license_key',     // ← 改成你的 option 名，与上方保持一致
        'status_key'  => 'your_theme_license_status',  // ← 改成你的 status option 名
        'menu_title'  => 'License',                    // ← 自定义菜单标题
        'page_title'  => 'License Activation',         // ← 自定义页面标题
    ]);
});

function your_theme_activate_license($license_key) {
    $result = LicenseOS::activate(
        $license_key,
        $_SERVER['HTTP_HOST'],
        'your-theme-slug'  // ← 改成你的主题 slug
    );

    if (($result['status'] ?? '') === 'success') {
        update_option('your_theme_license_key', $license_key);    // ← 改成你的 option 名
        update_option('your_theme_license_status', 'valid');      // ← 改成你的 status option 名
        LicenseOS::enableThemeAutoUpdate('your-theme-slug', $license_key); // ← 改成你的主题 slug
        return true;
    }

    return false;
}

function your_theme_check_license() {
    $key = get_option('your_theme_license_key'); // ← 改成你的 option 名
    if (!$key) return false;

    $result = LicenseOS::check($key);
    $is_valid = ($result['status'] ?? '') === 'valid';
    update_option('your_theme_license_status', $is_valid ? 'valid' : 'invalid'); // ← 改成你的 status option 名
    return $is_valid;
}

function your_theme_deactivate_license() {
    $license_key = get_option('your_theme_license_key'); // ← 改成你的 option 名
    if (!$license_key) return true;

    $response = wp_remote_post(
        'https://your-licenseos-domain.com/api/v2/license/deactivate', // ← 改成你的平台地址
        [
            'body' => [
                'license_key' => $license_key,
                'domain'      => $_SERVER['HTTP_HOST'],
            ],
            'headers' => [
                'X-API-KEY' => 'losk_your_api_key_here', // ← 改成你的 API Key
            ],
            'timeout' => 15,
        ]
    );

    if (!is_wp_error($response)) {
        $result = json_decode(wp_remote_retrieve_body($response), true);
        if (($result['status'] ?? '') === 'success') {
            delete_option('your_theme_license_key');    // ← 改成你的 option 名
            delete_option('your_theme_license_status'); // ← 改成你的 status option 名
            return true;
        }
    }

    return false;
}
```

---

#### 步骤 3：上传到 LicenseOS 平台并一键注入

1. 在开发者后台创建产品 → 选择「WordPress 主题」→ 上传 ZIP
2. 进入产品详情页，在「授权保护管理」面板中勾选需要的保护模块（默认全选）
3. 点击「一键注入」，平台会自动将 SDK 文件和保护代码注入到 ZIP 中

> 一键注入完成后，ZIP 中会自动包含 `licenseos-sdk.php`（SDK 主文件）以及所有保护机制，开发者无需手动添加任何文件。

---

#### 安全保护

平台一键注入时会自动处理以下安全机制：

| 机制              | 说明                                                                      |
| --------------- | ----------------------------------------------------------------------- |
| SDK 文件注入        | 自动将 `licenseos-sdk.php` 注入到主题 ZIP 中                                     |
| Integrity Guard | 注入到 functions.php，独立检测 SDK 引用和 `LicenseOS::init` 调用是否被删除或篡改，被删后主题功能自动失效 |
| 二级交叉校验          | 在 includes/src 等子目录文件中注入交叉校验，检测 functions.php 保护代码是否被删除                 |
| mu-plugin 守护    | SDK 激活许可证后自动安装 `mu-plugin`，持续监控所有已注册主题的完整性                              |

> **开发者无需手动添加任何防篡改代码**，只需按步骤 2 添加 SDK 初始化代码，平台一键注入会自动处理所有安全保护。

---

#### 接入后效果

**自动更新：**

| 位置       | 显示内容                      |
| -------- | ------------------------- |
| 仪表板 → 更新 | 主题列表显示可用更新                |
| 点击「查看详情」 | 弹窗显示更新日志、版本信息             |
| 点击「立即更新」 | WP 自动下载 ZIP → 解压替换 → 更新完成 |

**许可证门槛（License Gate）：**

| 状态  | 行为                              |
| --- | ------------------------------- |
| 未激活 | 外观 → 主题页面顶部显示黄色警告通知 + 许可证激活表单   |
| 已激活 | 主题页面顶部显示蓝色信息条（密钥脱敏 + 域名 + 停用按钮） |
| 已过期 | 重新显示激活表单                        |

---

### E.4 PHP 通用应用（Laravel / ThinkPHP / 纯 PHP）

**核心函数库（复制即可用）：**

```php
define('LICENSEOS_API', 'https://你的平台域名/api/v2');
define('LICENSEOS_KEY', '你的API_KEY');

function licenseos_activate($key, $domain, $product) {
    $ch = curl_init(LICENSEOS_API . '/license/activate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'license_key' => $key, 'domain' => $domain, 'product' => $product,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . LICENSEOS_KEY],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    return json_decode(curl_exec($ch), true);
}

function licenseos_check($key) {
    $ch = curl_init(LICENSEOS_API . '/license/check');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['license_key' => $key]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . LICENSEOS_KEY],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    return json_decode(curl_exec($ch), true);
}

// 使用示例
$result = licenseos_activate('用户输入的KEY', $_SERVER['HTTP_HOST'], 'my-app');
if ($result['status'] === 'success') {
    // 激活成功，存入本地
    file_put_contents('license.dat', '用户输入的KEY');
}
```

**Laravel 中间件示例：**

```php
class LicenseCheckMiddleware {
    public function handle($request, $next) {
        $key = config('app.license_key');
        if (!$key) return redirect('/activate');
        $result = licenseos_check($key);
        if ($result['status'] !== 'valid') {
            return response('License expired. Please renew.', 403);
        }
        return $next($request);
    }
}
```

---

### E.5 移动 APP（iOS / Android）

> 适用于原生 APP、Flutter、React Native、UniApp 等

**核心逻辑：APP 启动时检查许可证，过期则限制功能。**

**Flutter / Dart 示例：**

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class LicenseOS {
  static const String api = 'https://你的平台域名/api/v2';
  static const String key = '你的API_KEY';

  static Future<Map> activate(String licenseKey, String deviceId, String product) async {
    final res = await http.post(
      Uri.parse('$api/license/activate'),
      headers: {'Content-Type': 'application/json', 'X-API-KEY': key},
      body: jsonEncode({'license_key': licenseKey, 'domain': deviceId, 'product': product}),
    );
    return jsonDecode(res.body);
  }

  static Future<Map> check(String licenseKey) async {
    final res = await http.post(
      Uri.parse('$api/license/check'),
      headers: {'Content-Type': 'application/json', 'X-API-KEY': key},
      body: jsonEncode({'license_key': licenseKey}),
    );
    return jsonDecode(res.body);
  }
}

// 使用示例 - 在 main() 或 splash screen 中调用
void main() async {
  final savedKey = await SharedPreferences.getInstance().then((p) => p.getString('license'));
  if (savedKey != null) {
    final result = await LicenseOS.check(savedKey);
    if (result['status'] == 'valid') {
      runApp(PremiumApp()); // 完整功能
    } else {
      runApp(FreeApp());   // 限制功能
    }
  } else {
    runApp(FreeApp());     // 未激活
  }
}
```

**Android / Kotlin 示例：**

```kotlin
object LicenseOS {
    private const val API = "https://你的平台域名/api/v2"
    private const val KEY = "你的API_KEY"

    fun activate(key: String, deviceId: String, product: String): JSONObject {
        val url = URL("$API/license/activate")
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = "POST"
        conn.setRequestProperty("Content-Type", "application/json")
        conn.setRequestProperty("X-API-KEY", KEY)
        conn.doOutput = true
        conn.outputStream.write(json {
            put("license_key", key)
            put("domain", deviceId)    // 使用 Settings.Secure.ANDROID_ID
            put("product", product)
        }.toString().toByteArray())
        return JSONObject(conn.inputStream.bufferedReader().readText())
    }

    fun check(key: String): JSONObject {
        val url = URL("$API/license/check")
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = "POST"
        conn.setRequestProperty("Content-Type", "application/json")
        conn.setRequestProperty("X-API-KEY", KEY)
        conn.doOutput = true
        conn.outputStream.write(json { put("license_key", key) }.toString().toByteArray())
        return JSONObject(conn.inputStream.bufferedReader().readText())
    }
}
```

**iOS / Swift 示例：**

```swift
class LicenseOS {
    static let api = "https://你的平台域名/api/v2"
    static let key = "你的API_KEY"

    static func activate(licenseKey: String, deviceId: String, product: String, completion: @escaping ([String: Any]?) -> Void) {
        var request = URLRequest(url: URL(string: "\(api)/license/activate")!)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue(key, forHTTPHeaderField: "X-API-KEY")
        request.httpBody = try? JSONSerialization.data(withJSONObject: [
            "license_key": licenseKey, "domain": deviceId, "product": product
        ])
        URLSession.shared.dataTask(with: request) { data, _, _ in
            let result = try? JSONSerialization.jsonObject(with: data!) as? [String: Any]
            completion(result)
        }.resume()
    }

    static func check(licenseKey: String, completion: @escaping ([String: Any]?) -> Void) {
        var request = URLRequest(url: URL(string: "\(api)/license/check")!)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue(key, forHTTPHeaderField: "X-API-KEY")
        request.httpBody = try? JSONSerialization.data(withJSONObject: ["license_key": licenseKey])
        URLSession.shared.dataTask(with: request) { data, _, _ in
            let result = try? JSONSerialization.jsonObject(with: data!) as? [String: Any]
            completion(result)
        }.resume()
    }
}
```

> 💡 **移动端最佳实践：** 使用设备唯一 ID（Android ID / IDFV）代替域名作为 `domain` 参数

---

### E.6 桌面软件（Windows / macOS / Linux）

> 适用于 Electron、Qt、C#/.NET、Java、Python GUI 等

**Electron / Node.js 示例：**

```javascript
const { app, dialog } = require('electron');
const fetch = require('node-fetch');
const Store = require('electron-store');
const store = new Store();

const LICENSEOS_API = 'https://你的平台域名/api/v2';
const LICENSEOS_KEY = '你的API_KEY';

async function activateLicense(licenseKey) {
    const machineId = require('node-machine-id').machineIdSync();
    const res = await fetch(`${LICENSEOS_API}/license/activate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-KEY': LICENSEOS_KEY },
        body: JSON.stringify({ license_key: licenseKey, domain: machineId, product: 'your-app-name' }),
    });
    const result = await res.json();
    if (result.status === 'success') {
        store.set('license', licenseKey);
        return true;
    }
    return false;
}

async function validateLicense() {
    const key = store.get('license');
    if (!key) return false;
    const res = await fetch(`${LICENSEOS_API}/license/check`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-KEY': LICENSEOS_KEY },
        body: JSON.stringify({ license_key: key }),
    });
    const result = await res.json();
    return result.status === 'valid';
}

// APP 启动时检查
app.whenReady().then(async () => {
    if (await validateLicense()) {
        createMainWindow(); // 完整功能
    } else {
        showActivationDialog(); // 弹出激活窗口
    }
});
```

**C# / .NET 示例：**

```csharp
using System.Net.Http;
using System.Text.Json;

public class LicenseOS {
    private const string Api = "https://你的平台域名/api/v2";
    private const string Key = "你的API_KEY";
    private static readonly HttpClient client = new HttpClient();

    public static async Task<JsonElement> Activate(string licenseKey, string product) {
        var machineId = Environment.MachineName + "-" + Environment.UserName;
        var content = new StringContent(
            JsonSerializer.Serialize(new { license_key = licenseKey, domain = machineId, product }),
            System.Text.Encoding.UTF8, "application/json");
        client.DefaultRequestHeaders.Add("X-API-KEY", Key);
        var res = await client.PostAsync($"{Api}/license/activate", content);
        return JsonSerializer.Deserialize<JsonElement>(await res.Content.ReadAsStringAsync());
    }

    public static async Task<JsonElement> Check(string licenseKey) {
        var content = new StringContent(
            JsonSerializer.Serialize(new { license_key = licenseKey }),
            System.Text.Encoding.UTF8, "application/json");
        client.DefaultRequestHeaders.Add("X-API-KEY", Key);
        var res = await client.PostAsync($"{Api}/license/check", content);
        return JsonSerializer.Deserialize<JsonElement>(await res.Content.ReadAsStringAsync());
    }
}
```

**Java 示例：**

```java
public class LicenseOS {
    private static final String API = "https://你的平台域名/api/v2";
    private static final String KEY = "你的API_KEY";

    public static JsonObject activate(String licenseKey, String product) throws Exception {
        String machineId = InetAddress.getLocalHost().getHostName();
        String body = "{\"license_key\":\"" + licenseKey + "\",\"domain\":\"" + machineId + "\",\"product\":\"" + product + "\"}";
        return request("/license/activate", body);
    }

    public static JsonObject check(String licenseKey) throws Exception {
        String body = "{\"license_key\":\"" + licenseKey + "\"}";
        return request("/license/check", body);
    }

    private static JsonObject request(String path, String body) throws Exception {
        HttpURLConnection conn = (HttpURLConnection) new URL(API + path).openConnection();
        conn.setRequestMethod("POST");
        conn.setRequestProperty("Content-Type", "application/json");
        conn.setRequestProperty("X-API-KEY", KEY);
        conn.setDoOutput(true);
        conn.getOutputStream().write(body.getBytes());
        return Json.createReader(conn.getInputStream()).readObject();
    }
}
```

> 💡 **桌面软件最佳实践：** 使用机器指纹（MAC地址、硬盘序列号、主机名组合）作为 `domain` 参数

---

### E.7 源码 / 脚本（Python / Shell / Go / Rust）

**Python 示例：**

```python
import requests
import hashlib
import platform

LICENSEOS_API = 'https://你的平台域名/api/v2'
LICENSEOS_KEY = '你的API_KEY'

def get_machine_id():
    raw = platform.node() + platform.machine()
    return hashlib.md5(raw.encode()).hexdigest()[:16]

def activate(license_key, product='your-script'):
    res = requests.post(f'{LICENSEOS_API}/license/activate',
        json={'license_key': license_key, 'domain': get_machine_id(), 'product': product},
        headers={'X-API-KEY': LICENSEOS_KEY})
    return res.json()

def check(license_key):
    res = requests.post(f'{LICENSEOS_API}/license/check',
        json={'license_key': license_key},
        headers={'X-API-KEY': LICENSEOS_KEY})
    return res.json()

# 使用示例 - 脚本入口处检查
if __name__ == '__main__':
    saved_key = open('license.key').read().strip() if os.path.exists('license.key') else None
    if saved_key:
        result = check(saved_key)
        if result.get('status') != 'valid':
            print('License expired. Please renew.')
            exit(1)
    else:
        key = input('Enter license key: ')
        result = activate(key)
        if result.get('status') == 'success':
            open('license.key', 'w').write(key)
        else:
            print('Activation failed:', result.get('message'))
            exit(1)
```

**Shell / Bash 示例：**

```bash
#!/bin/bash
LICENSEOS_API="https://你的平台域名/api/v2"
LICENSEOS_KEY="你的API_KEY"
MACHINE_ID=$(hostname)-$(uname -m | md5sum | cut -c1-8)

licenseos_check() {
    curl -s -X POST "$LICENSEOS_API/license/check" \
        -H "Content-Type: application/json" \
        -H "X-API-KEY: $LICENSEOS_KEY" \
        -d "{\"license_key\":\"$1\"}"
}

LICENSE_FILE="${HOME}/.your-script/license.key"
if [ -f "$LICENSE_FILE" ]; then
    KEY=$(cat "$LICENSE_FILE")
    RESULT=$(licenseos_check "$KEY")
    STATUS=$(echo "$RESULT" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    if [ "$STATUS" != "valid" ]; then
        echo "License expired. Please renew."
        exit 1
    fi
else
    echo "Enter license key:"
    read KEY
    # ... 激活逻辑
fi
```

**Go 示例：**

```go
package main

import (
    "bytes"
    "encoding/json"
    "net/http"
    "os"
)

const (
    api = "https://你的平台域名/api/v2"
    key = "你的API_KEY"
)

func activateLicense(licenseKey, product string) (map[string]interface{}, error) {
    hostname, _ := os.Hostname()
    body, _ := json.Marshal(map[string]string{
        "license_key": licenseKey, "domain": hostname, "product": product,
    })
    req, _ := http.NewRequest("POST", api+"/license/activate", bytes.NewReader(body))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("X-API-KEY", key)
    res, err := http.DefaultClient.Do(req)
    if err != nil { return nil, err }
    defer res.Body.Close()
    var result map[string]interface{}
    json.NewDecoder(res.Body).Decode(&result)
    return result, nil
}
```

---

### E.8 网页应用 / SaaS

> 适用于 Vue、React、Next.js、Nuxt 等前端应用，或全栈 Web SaaS

**前端激活页面（Vue / React 通用）：**

```javascript
// license.js - 独立的许可证验证模块
const LICENSEOS_API = 'https://你的平台域名/api/v2';
const LICENSEOS_KEY = '你的API_KEY';

export async function activateLicense(key) {
    const res = await fetch(`${LICENSEOS_API}/license/activate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-KEY': LICENSEOS_KEY },
        body: JSON.stringify({ license_key: key, domain: window.location.hostname, product: 'your-saas' }),
    });
    return res.json();
}

export async function checkLicense(key) {
    const res = await fetch(`${LICENSEOS_API}/license/check`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-KEY': LICENSEOS_KEY },
        body: JSON.stringify({ license_key: key }),
    });
    return res.json();
}

// 路由守卫示例 (Vue Router)
router.beforeEach(async (to, from, next) => {
    const key = localStorage.getItem('license');
    if (!key) return next('/activate');
    const result = await checkLicense(key);
    if (result.status !== 'valid') return next('/expired');
    next();
});
```

> ⚠️ **注意：** 前端验证仅作为用户体验层，核心功能验证应在后端 API 层实现

**后端 API 保护（Node.js / Express）：**

```javascript
const express = require('express');
const app = express();

// 中间件：保护 API 路由
async function requireLicense(req, res, next) {
    const key = req.headers['x-license-key'];
    if (!key) return res.status(401).json({ error: 'License required' });
    const result = await checkLicense(key);
    if (result.status !== 'valid') return res.status(403).json({ error: 'License expired' });
    req.license = result;
    next();
}

app.get('/api/data', requireLicense, (req, res) => {
    res.json({ data: 'Protected content' });
});
```

---

### E.9 付费内容（课程 / 电子书 / 模板 / 素材）

> 适用于知识付费、内容订阅、数字下载类产品

**典型流程：**

1. 用户购买后获得许可证 Key
2. 通过 Key 下载内容或访问付费页面
3. 许可证控制下载次数或访问时效

**PHP 实现示例（WordPress 付费下载）：**

```php
// 用户输入许可证后下载
if (isset($_POST['license_key'])) {
    $result = licenseos_check($_POST['license_key']);
    if ($result['status'] === 'valid') {
        $downloadUrl = $result['download_url'] ?? '';
        if ($downloadUrl) {
            header('Location: ' . $downloadUrl);
            exit;
        }
        echo '下载链接已发送到您的邮箱';
    } else {
        echo '许可证无效或已过期';
    }
}
```

**付费视频 / 课程内容保护：**

```javascript
// 前端：检查许可证后解锁课程
async function unlockCourse(licenseKey) {
    const result = await checkLicense(licenseKey);
    if (result.status === 'valid') {
        localStorage.setItem('course_license', licenseKey);
        document.getElementById('course-content').style.display = 'block';
        document.getElementById('purchase-form').style.display = 'none';
        return true;
    }
    alert('许可证无效：' + (result.message || '请联系客服'));
    return false;
}

// 每次加载页面时自动检查
window.addEventListener('DOMContentLoaded', () => {
    const savedKey = localStorage.getItem('course_license');
    if (savedKey) unlockCourse(savedKey);
});
```

---

### E.10 API 接口参考

| 接口                           | 方法   | 参数                                 | 说明                        |
| ---------------------------- | ---- | ---------------------------------- | ------------------------- |
| `/api/v2/license/activate`   | POST | `license_key`, `domain`, `product` | 激活许可证，绑定域名/设备             |
| `/api/v2/license/check`      | POST | `license_key`                      | 检查许可证状态和有效期               |
| `/api/v2/license/deactivate` | POST | `license_key`, `domain`            | 解绑域名/设备                   |
| `/api/v2/update/check`       | GET  | `product`, `version`               | 检查产品更新（支持 WordPress 自动更新） |

> 所有接口需在请求头中携带 `X-API-KEY: 你的API_KEY`

### E.11 各类型接入速查表

| 软件类型                  | domain 参数建议                     | 激活时机     | SDK 语言           |
| --------------------- | ------------------------------- | -------- | ---------------- |
| WordPress 插件/主题       | `$_SERVER['HTTP_HOST']`         | 插件设置页    | PHP              |
| PHP 源码/框架             | `$_SERVER['HTTP_HOST']` 或机器名    | 安装/配置页   | PHP              |
| iOS APP               | IDFV / Bundle ID                | 首次启动/设置页 | Swift            |
| Android APP           | Android ID                      | 首次启动/设置页 | Kotlin/Java      |
| Flutter / RN / UniApp | 设备唯一 ID                         | 首次启动     | Dart/JS          |
| Windows 桌面            | 主机名+用户名                         | 安装/注册窗口  | C#/.NET/Electron |
| macOS 桌面              | Hostname + UUID                 | 首次启动     | Swift/Electron   |
| Linux 桌面              | Machine ID                      | 首次启动     | Python/Go/Shell  |
| Python 脚本             | hashlib(MAC+hostname)           | 脚本入口     | Python           |
| Shell 脚本              | hostname-machine                | 脚本入口     | Bash             |
| 网页 SaaS               | window.location.hostname        | 登录/激活页   | JavaScript       |
| 付费内容                  | window.location.hostname 或浏览器指纹 | 购买后访问    | JavaScript       |

---

## 附录 F：支付方式配置说明

### 资金流向说明

```
客户付款 $100 → 平台收款账户（Stripe/微信/支付宝）
              → 平台抽佣 10%（$10）
              → 开发者余额 $90
开发者申请提现 → 平台打款到开发者收款账户
              → Stripe Connect 自动转账 / 支付宝转账 / 微信企业付款
```

### 平台级配置（Admin 后台）

> Admin 后台 → **Settings** 页面（左侧菜单最下方）

| 配置项       | 说明                                              | 用途            |
| --------- | ----------------------------------------------- | ------------- |
| Stripe 密钥 | Publishable Key + Secret + Webhook + Connect ID | 接收客户付款 + 自动分账 |
| 微信支付      | AppID + 商户号 + API Key                           | 接收中国客户付款      |
| 支付宝       | AppID + 私钥 + 公钥 + 网关                            | 接收中国客户付款      |
| 平台规则      | 抽佣率 + 最低提现 + 提现延迟                               | 控制平台运营策略      |

> Settings 页面直接写入 `.env` 文件，保存后自动清除配置缓存。

### 开发者级配置（租户收款账户）

> Admin 后台 → **Tenants** → 编辑某个租户 → **Payout Account Configuration**

| 配置项                       | 说明               | 用途            |
| ------------------------- | ---------------- | ------------- |
| Stripe Connect Account ID | 开发者的 Stripe 关联账户 | Stripe 自动转账打款 |
| Alipay Account            | 开发者的支付宝账号        | 支付宝转账打款       |
| WeChat OpenID             | 开发者的微信 OpenID    | 微信企业付款        |

### 当前支付方式集成状态

| 支付方式       | 收款（客户→平台）              | 打款（平台→开发者）             | 状态      |
| ---------- | ---------------------- | ---------------------- | ------- |
| **Stripe** | ✅ 完整（Checkout Session） | ✅ 完整（Connect Transfer） | 生产可用    |
| **微信支付**   | ✅ 回调处理已实现              | ✅ 占位符（需接入 SDK）         | 需集成 SDK |
| **支付宝**    | ✅ 回调处理已实现              | ✅ 占位符（需接入 SDK）         | 需集成 SDK |

> 微信/支付宝的 Webhook 回调处理逻辑已完整，实际 SDK 集成需安装官方 SDK 包。
> Stripe 已完全可用，建议优先使用 Stripe 进行收款和打款。

### 后续可扩展

- Adyen（欧洲市场）
- Razorpay（印度市场）
- PayPal
- 加密货币支付

---

## 附录 G：多语言支持

### 当前状态

| 功能        | 状态      | 说明                                  |
| --------- | ------- | ----------------------------------- |
| 默认语言      | English | `config/app.php` 中 `locale => 'en'` |
| 产品多语言     | ✅ 已实现   | `ProductTranslation` 模型，支持按语言查询     |
| 用户语言偏好    | ✅ 已实现   | User 模型含 `locale` 字段                |
| 前端语言切换 UI | ❌ 待开发   | 需添加语言切换组件                           |
| 后台中文界面    | ❌ 待开发   | 需发布 Filament 语言包                    |

### 如何添加中文支持

后续可通过以下步骤实现：

1. **创建中文语言文件**：`resources/lang/zh-CN/` 目录
2. **安装 Filament 中文包**：`composer require filament/translations` → 发布中文语言文件
3. **添加语言切换中间件**：根据用户 `locale` 或请求参数切换语言
4. **前端语言切换 UI**：在导航栏添加语言下拉菜单

> 💡 当前英文界面符合国际化标准，中文支持可作为后续迭代目标

---

## 附录 H：`artisan migrate` 报错时的绕过方案

### 何时使用

当 `php artisan migrate` 报以下错误时：

```
Call to a member function make() on null
at vendor/laravel/framework/src/Illuminate/Console/Command.php:173
```

这是 Laravel 11 + 某些 PHP 8.3 扩展（如 opcache JIT）的兼容性问题，导致 Artisan CLI 的容器绑定失败。**网站本身运行正常**，仅命令行工具受影响。

### 绕过命令

直接调用 Laravel 的 `migrator` 服务执行迁移，完全绕过 Artisan CLI：

```bash
cd /www/wwwroot/你的站点目录 && /www/server/php/83/bin/php -d opcache.jit=disable -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$kernel = \$app->make(\Illuminate\Contracts\Console\Kernel::class); \$kernel->bootstrap(); \$migrator = \$app->make('migrator'); \$migrator->setOutput(new \Symfony\Component\Console\Output\ConsoleOutput()); \$migrator->run(database_path('migrations'), ['pretend' => false, 'force' => true]); echo 'Done'.PHP_EOL;"
```

### 工作原理

| 步骤                              | 说明                                |
| ------------------------------- | --------------------------------- |
| `require 'vendor/autoload.php'` | 加载 Composer 自动加载器                 |
| `require 'bootstrap/app.php'`   | 创建 Laravel 应用实例                   |
| `$kernel->bootstrap()`          | 启动应用（加载配置、服务提供者等）                 |
| `$app->make('migrator')`        | 获取 Laravel 内置迁移服务                 |
| `$migrator->run(...)`           | 直接执行 `database/migrations` 下的迁移文件 |

### 与其他 artisan 命令的替代

| 原命令                                | 替代方案                                       |
| ---------------------------------- | ------------------------------------------ |
| `php artisan migrate`              | 使用上述绕过命令                                   |
| `php artisan config:clear`         | `rm -f bootstrap/cache/config.php`         |
| `php artisan cache:clear`          | `rm -f bootstrap/cache/cache.php` 或清 Redis |
| `php artisan route:clear`          | `rm -f bootstrap/cache/routes-v7.php`      |
| `php artisan view:clear`           | `rm -rf storage/framework/views/*.php`     |
| `php artisan admin:reset-password` | 网站正常运行则不影响，仅 CLI 受影响                       |

### 根治方法

此问题通常由以下原因引起：

1. **PHP opcache JIT 冲突**：命令中加 `-d opcache.jit=disable` 可缓解
2. **Composer 安装时未加 `--no-scripts`**：重新执行 `composer install --no-scripts --no-dev --optimize-autoloader`
3. **vendor 目录不完整**：删除 `vendor/` 重新执行 Composer 安装

---

---

## 📚 附录 J：WordPress 授权接入完整教程

本教程详细介绍如何为通用 WordPress 插件和主题集成 LicenseOS 授权系统。

---

### J.1 快速对比：一键注入 vs 手动插入

| 特性   | 一键注入（推荐）     | 手动插入        |
| ---- | ------------ | ----------- |
| 难度   | ⭐ 简单（平台自动完成） | ⭐⭐⭐ 需理解代码逻辑 |
| 安全性  | ⭐⭐⭐⭐⭐ 精确无误   | ⭐⭐⭐ 依赖开发者技能 |
| 灵活性  | ⭐⭐⭐ 标准化结构    | ⭐⭐⭐⭐⭐ 完全自定义 |
| 复原功能 | ✅ 支持一键复原     | ❌ 需手动清理     |
| 适用场景 | 95% 项目       | 特殊定制需求      |

**强烈建议优先使用一键注入！**

---

### J.2 WordPress 插件接入指南

#### Step 1: 创建产品与获取配置

1. **登录开发者后台** → 创建产品：
   
   ```
   - 名称：Move Bottom Menu
   - Slug: move-bottom-menu（唯一英文标识）
   - 类型：WordPress 插件
   - 勾选"启用授权保护"
   - 上传 ZIP 文件（暂不自动注入）
   ```

2. **获取注入配置**（在产品详情页）：
   
   ```
   API URL:    https://sz.djkz.top/api/v2
   API Key:    losk_l9Jlf8KStyiENOe92qxZhWuvgnVGy2FfNhKEfy19
   产品 Slug:  move-bottom-menu
   ```

#### Step 2: 平台一键注入（推荐方式）

1. **编辑产品页面** → 滚动到"授权保护管理"区域
2. **勾选所有保护模块**（强烈建议全选）：
   - ✅ Integrity Guard 防篡改自检
   - ✅ 二级交叉校验（Cross-Check）
   - ✅ 许可证门控（License Gate）
   - ✅ 自动守护（Auto Guardian - MU-Plugin）
3. **点击"一键注入"按钮** → 等待约 5 秒成功提示
4. **下载更新后的 ZIP** → 验证 SHA256 哈希值是否正确

#### Step 3: 手动插入（备用方案）

如果无法使用一键注入，参考以下标准代码片段：

**位置 1：在主文件顶部引用 SDK**（通常在 `define('ABSPATH', ...)` 之后）：

```php
// === BEGIN LICENSEOS SDK REFERENCE ===
sdk_reference: file_exists(__DIR__.'/licenseos-sdk.php') ? 
    require_once __DIR__.'/licenseos-sdk.php' : false;
// === END LICENSEOS SDK REFERENCE ===
```

**位置 2：初始化 SDK**（推荐使用 `plugins_loaded` 钩子）：

```php
add_action('plugins_loaded', function() {
    // 仅当 SDK 存在时初始化
    if (!class_exists('LicenseOS')) return;

    LicenseOS::init(
        'https://sz.djkz.top/api/v2',     // API URL
        'losk_l9Jlf8KStyiENOe92qxZhWuvgnVGy2FfNhKEfy19', // API Key
        __DIR__                          // Base Directory
    );
});
```

**位置 3：许可证门控配置**（在您的设置函数中）：

```php
add_action('admin_init', function() {
    // 仅在已激活许可证时才启用 License Gate
    $status = get_option('mbm_license_status', '');
    if ($status !== 'valid') return;

    LicenseOS::enableLicenseGate([
        'slug'         => 'mbm-admin',           // 必须与 add_menu_page() 第 4 参数一致
        'name'         => 'Move Bottom Menu',
        'option_key'   => 'mbm_license_key',     // Option 名称前缀
        'status_key'   => 'mbm_license_status',  // 状态存储键名
        'parent_slug'  => 'admin.php',           // 顶级菜单用 admin.php
        'page_title'   => 'Move Bottom Menu Settings'
    ]);
});
```

**位置 4：自动更新集成**（在激活函数中调用）：

```php
function mbm_activate_license($license_key) {
    $result = LicenseOS::activate($license_key, $_SERVER['HTTP_HOST'], 'move-bottom-menu');

    if ($result['status'] === 'success') {
        update_option('mbm_license_key', $license_key);
        update_option('mbm_license_status', 'valid');

        // 启用自动更新
        LicenseOS::enableAutoUpdate('move-bottom-menu', $license_key);
        return true;
    }
    return false;
}
```

**位置 5：停用许可证**：

```php
function mbm_deactivate_license($license_key) {
    LicenseOS::deactivate($license_key, $_SERVER['HTTP_HOST'], 'move-bottom-menu');
    delete_option('mbm_license_key');
    delete_option('mbm_license_status');
}
```

#### 测试检查清单

✅ **未激活状态**：进入设置页时只显示激活表单，无法查看实际内容  
✅ **激活成功后**：显示绿色授权信息条，包含域名、到期时间、续费按钮  
✅ **篡改测试**：删除主文件中任意 LicenseOS 相关代码后刷新，应弹出全屏警告并禁用菜单  

---

### J.3 WordPress 主题接入指南

#### Step 1: 创建产品时的差异

**关键区别**：类型为 "WordPress 主题" 而非 "插件"

```
- 类型：WordPress 主题
- Slug: my-custom-theme
- ZIP 内容：style.css (含 Theme Name header) + functions.php
```

#### Step 2: 平台一键注入

操作流程与插件完全相同，但会自动识别：

- **主文件**：从 `functions.php` 查找 `Theme Name:` header
- **钩子优先级**：使用 `after_setup_theme` 而非 `plugins_loaded`
- **UI 展示位置**：通过 `themes.php` 渲染激活表单（而非独立菜单）

#### Step 3: 手动插入代码要点

**与插件的主要差异点**：

1. **初始化时机**：
   
   ```php
   // 插件用：plugins_loaded
   // 主题用：after_setup_theme
   add_action('after_setup_theme', function() {
       if (!class_exists('LicenseOS')) return;
   
       LicenseOS::init(...);
   });
   ```

2. **自动更新方法**：
   
   ```php
   // 插件用：LicenseOS::enableAutoUpdate()
   // 主题用：LicenseOS::enableThemeAutoUpdate()
   LicenseOS::enableThemeAutoUpdate('my-custom-theme', $license_key);
   ```

3. **授权门控方法**：
   
   ```php
   // 插件用：LicenseOS::enableLicenseGate()
   // 主题用：LicenseOS::enableThemeLicenseGate()
   LicenseOS::enableThemeLicenseGate([
       'slug'             => 'my-custom-theme',
       'name'             => 'My Custom Theme',
       'option_key'       => 'mytheme_license_key',
       'status_key'       => 'mytheme_license_status',
       'menu_title'       => 'License Activation',
       'page_title'       => 'My Theme License',
       'protection_enabled' => true
   ]);
   ```

4. **MU-Plugin 路径配置**：
   
   ```php
   // 主题使用：$theme_dir . '/functions.php'
   // 插件使用：$plugin_file (通常主文件)
   ```

#### 主题特有的 UI 表现

- **无独立菜单系统**：激活表单直接在 `themes.php` 页面顶部显示
- **授权信息条**：同样显示在 themes.php 底部
- **详情弹窗**：通过 Appearance → Theme Details 触发（复用 WordPress 原生 UI）

---

### J.4 五层防篡改机制详解

#### Layer 1: E.2 代码完整性检查

**原理**：SHA256 哈希比对注入的代码块

**检查时机**：每 6 小时一次（通过 transient 缓存）

**触发条件**：检测到 Hash 不一致 → 标记篡改并禁用管理菜单

#### Layer 2: 二级交叉校验

**原理**：子目录 PHP 文件反向校验主文件

**防御场景**：攻击者删除主文件的注入代码，但子文件仍保留校验逻辑

**检测效果**：主文件被整段删除也能发现

#### Layer 3: 远程 API 验证

**原理**：实时查询平台验证 API Key 有效性

**检查内容**：

- API Key 长度是否合法（≥20 字符）
- License Key 是否在有效期内
- 平台是否有篡改标记

**缓存策略**：1 小时缓存

#### Layer 4: MU-Plugin 守护进程

**位置**：`wp-content/mu-plugins/wp-system-health-monitor.php`

**优先级**：极高（优先级 5，最早执行）

**工作内容**：

- 每 24 小时验证 License Key 有效性
- 独立监控代码完整性（即使主插件失效也能工作）
- 过期超过 3 天自动停用插件/主题

**优势**：独立于主插件之外，无法被常规手段绕过

#### Layer 5: Inline Guard 自检

**原理**：SDK 初始化时立即检查关键标记

**检查项**：

- licenseos-sdk.php 文件是否存在
- 主文件中是否包含 API Key
- 是否包含 `LicenseOS::init` 调用
- 是否包含 `require_once` 引用

**作用**：本地快速检查，减少不必要的网络延迟

---

### J.5 常见问题排查（FAQ）

#### Q1: 为什么看不到授权信息条？

**可能原因**：

1. API Key 无效或被删除
2. 产品 Slug 与实际 menu_slug 不一致
3. 未正确调用 `enableLicenseGate()`
4. `.env` 中的 `APP_URL` 配置错误

**解决方法**：

- 检查 `get_option('your_slug_license_status')` 是否为 `valid`
- 确认 add_menu_page 的第 4 个参数与 `slug` 配置一致
- 查看 WordPress 日志是否有 500 错误

#### Q2: 篡改警告一直弹窗如何处理？

**步骤**：

1. 检查 `debug.log` 查看具体错误信息
2. 确认文件编码为"UTF-8 without BOM"
3. 验证 main-file.php 中是否缺少任何 LicenseOS 标记行
4. 清除所有 transient：`wp transient flush`
5. 如误标可手动重置：`wp option update your_slug_tampered false`

#### Q3: 一键注入失败的可能原因？

**常见原因**：

- ZIP 文件损坏或不兼容
- Platform API Key 无效或删除
- 目标目录权限不足（Linux 下需 chmod 755）
- 产品 Slug 包含非法字符（只能用字母、数字、连字符）
- 同时存在多个同名 product_slug

**解决方法**：

- 重新上传原始 ZIP
- 验证平台 API 配置是否有效
- 检查宝塔面板的文件权限设置
- 联系管理员核实 API Key

#### Q4: MU-Plugin 是否安装？

**检查命令**：

```bash
ls -la wp-content/mu-plugins/wp-system-health-monitor.php
```

**如未安装**：

1. 确认是否已完成许可证激活
2. 手动创建 mu-plugin 目录（如无）
3. 重启 WordPress 或运行缓存清理

---

### J.6 性能优化建议

1. **避免频繁 API 调用**：使用 12 小时缓存检查结果
2. **延迟执行敏感操作**：使用优先级 999 确保最后加载
3. **利用单例缓存**：SDK 内部的 `$update_cache` 变量可重复使用
4. **合理使用 transient**：减少数据库查询压力
5. **前端脚本按需加载**：仅在管理后台加载 LicenseOS 资源

---

### J.7 调试工具清单

**必备插件**：

- Debug Bar：查看 query log 和内存使用情况
- Query Monitor：检查 HTTP requests 和 SQL queries
- WP-CLI：批量操作 option 数据和 transient

**常用命令**：

```bash
# 查看所有 licenseos 相关的 option
wp option list --search=licenseos

# 手动激活许可证
wp option update your_slug_license_status valid

# 清除所有 transient
wp transient flush

# 查看最新的错误日志
tail -f /path/to/wp-content/debug.log

# 评估代码片段
wp eval 'echo get_option("your_slug_license_key");'
```

---

### J.8 最佳实践总结

✅ **命名规范**：Option 名称使用 `{product_slug}_license_key` 格式  
✅ **Slug 一致性**：统一使用产品 slug，禁止拼写错误  
✅ **类存在检查**：始终先检查 `class_exists('LicenseOS')`  再调用方法  
✅ **父子菜单区分**：顶级菜单填 `admin.php`，子菜单填父级实际 slug  
✅ **BOM 处理**：确保所有 PHP 文件保存为"UTF-8 without BOM"  
✅ **充分测试**：至少测试三个场景（未激活、已激活、篡改状态）  
✅ **定期备份**：备份原文件和 option 数据，便于回滚  

---

## 附录 I：WordPress 插件"查看详情"弹窗配置教程

### I.1 功能概述

"查看详情"弹窗是 WordPress 原生 UI 组件，与 WordPress.org 官方插件详情弹窗完全一致。通过 LicenseOS SDK 集成，第三方插件可在 WP 仪表盘内展示完整的产品信息弹窗。

**弹窗触发位置：**

- **插件列表页**：WP 仪表盘 → 插件 → 已安装插件，每个通过 LicenseOS 注册的插件下方都有"查看详情"链接
- **更新列表页**：WP 仪表盘 → 更新，有"查看版本 x.x.x 详情"链接

**弹窗包含三大区域：**

1. **顶部横幅区**：插件横幅图片（Banner）和头像图标（Icon）
2. **左侧菜单 + 内容区**：描述、安装、常见问题、修订历史、截图、评价（多标签页切换）
3. **右侧信息栏**：版本号、作者、最近更新、兼容性、安装数、评级等元信息

> 弹窗 UI 框架由 WordPress 核心提供，LicenseOS SDK 负责填充数据。

---

### I.2 数据流完整链路图

下图展示了弹窗中每个数据元素从源头到最终展示的完整路径：

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        数据源（4 种来源）                                      │
├──────────────────┬──────────────────┬──────────────────┬────────────────────┤
│ ① LicenseOS 后台  │ ② API 响应        │ ③ SDK 默认值       │ ④ WP 原生机制       │
│ (Product 模型)    │ (UpdateService)  │ (硬编码 fallback)  │ (ps.w.org 等)      │
└────────┬─────────┴────────┬─────────┴────────┬─────────┴──────────┬─────────┘
         │                  │                  │                   │
         ▼                  ▼                  ▼                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    LicenseOS API: GET /api/v2/update/check                  │
│                                                                             │
│  返回字段：                                                                   │
│  product_name ← Product.name                                               │
│  product_url  ← url('/products/{slug}')                                    │
│  short_description ← Product.short_description（或截取 description 前200字） │
│  latest_version ← UpdatePackage.version（最新版本记录）                       │
│  changelog      ← UpdatePackage.changelog（最新版本记录）                     │
│  download_url   ← 带 license_key 鉴权的下载链接                               │
│  up_to_date     ← 版本比较结果                                                │
│  current_version ← 客户端传入的当前版本                                        │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                 SDK Hook 2: plugins_api filter                              │
│          (licenseos-sdk.php 第 122-150 行)                                   │
│                                                                             │
│  映射为 WP 标准 plugin_information 对象：                                      │
│  ┌─────────────────┬──────────────────────────────────────┐                │
│  │ WP 字段          │ 取值逻辑                               │                │
│  ├─────────────────┼──────────────────────────────────────┤                │
│  │ name            │ API → product_name                   │                │
│  │ slug            │ SDK 传入的 plugin_slug               │                │
│  │ version         │ API → latest_version                 │                │
│  │ requires        │ API → requires_wp ✅ 从 ZIP 自动提取  │                │
│  │ tested          │ API → tested_up_to ✅ 从 ZIP 自动提取 │                │
│  │ requires_php    │ API → requires_php ✅ 从 ZIP 自动提取 │                │
│  │ sections[]      │ ↓ 见下方详细说明                        │                │
│  │ download_link   │ API → download_url                   │                │
│  │ homepage        │ API → plugin_homepage ✅ 从 ZIP 自动提取 │                │
│  │ author          │ API → author ✅ 从 ZIP 自动提取        │                │
│  │ author_profile  │ API → author_profile ✅ 从 ZIP 自动提取│                │
│  └─────────────────┴──────────────────────────────────────┘                │
│                                                                             │
│  sections 映射：                                                             │
│  ┌─────────────────┬──────────────────────────────────────┐                │
│  │ description     │ API → short_description              │ ✅ 已支持      │
│  │ changelog       │ API → changelog                      │ ✅ 已支持      │
│  │ installation    │ ❌ 未传递（SDK 未实现）                  │ ❌ 不支持      │
│  │ faq             │ ❌ 未传递（SDK 未实现）                  │ ❌ 不支持      │
│  │ screenshots     │ ❌ 未传递（SDK 未实现）                  │ ❌ 不支持      │
│  │ reviews         │ ❌ 未传递（SDK 未实现）                  │ ❌ 不支持      │
│  └─────────────────┴──────────────────────────────────────┘                │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                     WP 核心渲染弹窗 UI                                       │
│                                                                             │
│  横幅区：SDK 返回 banners/icons 属性 → WP 直接使用管理员上传的横幅和图标图片 → ✅ 正常显示  │
│  菜单区：根据 sections 数组动态生成标签页（仅有 description + changelog）         │
│  右侧栏：根据对象属性填充（作者、兼容性等字段已从 ZIP 自动提取并传递）                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### I.3 横幅区数据来源详解

#### WordPress 官方机制

在 WordPress.org 官方插件库中，横幅图片（Banner）和插件图标（Icon）的存放位置为：

```
https://ps.w.org/{plugin-slug}/assets/
├── banner-772x250.png      # 标准横幅（必须）
├── banner-1544x500.png     # 高分屏横幅（可选）
├── icon-128x128.png        # 标准图标（可选）
└── icon-256x256.png        # 高分屏图标（可选）
```

WordPress 核心在渲染弹窗时，会自动向 `ps.w.org/{slug}/assets/` 发起请求加载这些图片。

#### LicenseOS 第三方插件场景下的现状

> **✅ 已支持横幅和图标显示**

管理员可在 LicenseOS 后台产品编辑页面中上传横幅图片（Banner）和插件图标（Icon）。SDK Hook 2 在返回对象中包含 `banners` 和 `icons` 属性，WordPress 会优先使用这些属性渲染弹窗顶部横幅和图标，而不去请求 `ps.w.org`。

**实现原理：**

- Product 模型包含 `banner_url` 和 `icon_url` 字段，管理员在后台产品编辑中上传
- **UpdateService** 在 API 响应中返回 `banner_url`、`banner_url_high`、`icon_url`、`icon_url_high` 字段
- **SDK Hook 2**（`plugins_api` filter）在返回对象中包含 `banners` 和 `icons` 属性：

```php
return (object) [
    // ...其他字段...
    'banners' => [
        'low'  => $update['banner_url'] ?? '',   // 772x250
        'high' => $update['banner_url_high'] ?? '', // 1544x500
    ],
    'icons' => [
        '1x' => $update['icon_url'] ?? '',       // 128x128
        '2x' => $update['icon_url_high'] ?? '',  // 256x256
    ],
];
```

**横幅图片推荐规格：**

| 类型    | 文件名                 | 推荐尺寸          | 格式      | 用途          |
| ----- | ------------------- | ------------- | ------- | ----------- |
| 标准横幅  | banner-772x250.png  | 772 × 250 px  | PNG/JPG | 弹窗顶部横幅      |
| 高分屏横幅 | banner-1544x500.png | 1544 × 500 px | PNG/JPG | Retina 屏幕横幅 |
| 标准图标  | icon-128x128.png    | 128 × 128 px  | PNG/SVG | 插件列表图标      |
| 高分屏图标 | icon-256x256.png    | 256 × 256 px  | PNG/SVG | Retina 屏幕图标 |

---

### I.4 菜单标签页内容来源详解

#### 当前已支持的标签页

SDK Hook 2 在构建 `sections` 数组时，**当前仅传递了 2 个标签页**：`description`（描述）和 `changelog`（修订历史）。WordPress 会根据 `sections` 数组中存在的键动态生成标签页菜单——**只有传递了的标签页才会在弹窗中显示**。

| 标签页      | sections 键名    | 支持状态  | 数据来源                                | 取值逻辑                                                                                |
| -------- | -------------- | ----- | ----------------------------------- | ----------------------------------------------------------------------------------- |
| **描述**   | `description`  | ✅ 已支持 | LicenseOS API → `short_description` | `UpdateService` 返回 `Product.short_description`；若为空则截取 `Product.description` 前 200 字 |
| **修订历史** | `changelog`    | ✅ 已支持 | LicenseOS API → `changelog`         | `UpdateService` 返回最新 `UpdatePackage.changelog`；若为空显示 `'No changelog available.'`    |
| **安装**   | `installation` | ❌ 未实现 | —                                   | SDK 未传递此键，弹窗中不显示该标签页                                                                |
| **常见问题** | `faq`          | ❌ 未实现 | —                                   | SDK 未传递此键，弹窗中不显示该标签页                                                                |
| **截图**   | `screenshots`  | ❌ 未实现 | —                                   | SDK 未传递此键，弹窗中不显示该标签页                                                                |
| **评价**   | `reviews`      | ❌ 未实现 | —                                   | SDK 未传递此键，弹窗中不显示该标签页                                                                |

#### 各标签页详细数据来源说明

**1. 描述（Description）— ✅ 已支持**

- **数据来源**：LicenseOS API 响应的 `short_description` 字段

- **后台配置**：产品编辑页 → **短描述**（`short_description`）字段

- **回退逻辑**：若 `short_description` 为空，`UpdateService` 自动截取 `description`（完整描述）前 200 个字符

- **源码位置**：`UpdateService.php` 第 37 行：
  
  ```php
  'short_description' => $product->short_description 
      ?? ($product->description ? mb_substr($product->description, 0, 200) : ''),
  ```

- **SDK 映射**：`licenseos-sdk.php` 第 144 行：
  
  ```php
  'description' => $update['short_description'] ?? '',
  ```

- **内容格式**：支持 HTML 标签，WordPress 会自动渲染

**2. 修订历史（Changelog）— ✅ 已支持**

- **数据来源**：LicenseOS API 响应的 `changelog` 字段

- **后台配置**：更新包管理 → 创建/编辑更新包 → **更新日志**（`changelog`）字段

- **取值逻辑**：始终取**最新版本**（`UpdatePackage` 按 `created_at` 降序排列的第一条记录）的 `changelog`

- **源码位置**：`UpdateService.php` 第 70 行：
  
  ```php
  'changelog' => $latest->changelog ?? '',
  ```

- **SDK 映射**：`licenseos-sdk.php` 第 145 行：
  
  ```php
  'changelog' => $update['changelog'] ?? 'No changelog available.',
  ```

- **内容格式**：支持 HTML，建议每个版本用 `<h4>` 标签标注版本号

**3. 安装（Installation）— ❌ 未实现**

- **当前状态**：SDK 未传递 `installation` 键，弹窗中**不显示此标签页**
- **后续方案**：SDK 计划支持从 API 获取安装说明，或从插件 ZIP 包内的 `readme.txt` 中解析 `== Installation ==` 区块

**4. 常见问题（FAQ）— ❌ 未实现**

- **当前状态**：SDK 未传递 `faq` 键，弹窗中**不显示此标签页**
- **后续方案**：计划从 API 或 `readme.txt` 的 `== Frequently Asked Questions ==` 区块解析

**5. 截图（Screenshots）— ❌ 未实现**

- **当前状态**：SDK 未传递 `screenshots` 键，弹窗中**不显示此标签页**
- **已有基础**：Product 模型已有 `screenshots` 字段（`array` 类型 cast，JSON 数组格式），但未在 `UpdateService` 和 SDK 中使用
- **后续方案**：将 `Product.screenshots` 传入 API 响应，SDK 将其映射为 `sections['screenshots']`

**6. 评价（Reviews）— ❌ 未实现**

- **当前状态**：SDK 未传递 `reviews` 键，弹窗中**不显示此标签页**
- **已有基础**：Product 模型已有 `reviews()` 关联（`Review` 模型），但未在 `UpdateService` 和 SDK 中使用

#### 关于 readme.txt 的说明

> **readme.txt 当前不参与弹窗数据构建。**

WordPress.org 官方插件库使用插件目录下的 `readme.txt` 文件作为弹窗数据的主要来源。该文件遵循标准格式：

```
=== Plugin Name ===
Contributors: username
Tags: tag1, tag2
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4

Short description here.

== Description ==
详细描述...

== Installation ==
安装步骤...

== Frequently Asked Questions ==
= 问题1？ =
回答1

== Screenshots ==
1. 截图描述
2. 截图描述

== Changelog ==
= 1.0.0 =
* 初始发布
```

**在 LicenseOS 体系中：**

- LicenseOS SDK **不从 `readme.txt` 读取数据**，弹窗所有内容均来自 LicenseOS API 响应
- 插件 ZIP 包中可以包含 `readme.txt`（如示例插件 `move-bottom-menu/readme.txt`），但它仅作为 WordPress.org 上架时的标准文件，不影响 LicenseOS 弹窗
- 管理员应在 LicenseOS 后台的产品编辑页面中填写各项内容，而非依赖 `readme.txt`

---

### I.5 右侧信息栏数据来源详解

右侧信息栏由 WordPress 核心根据 SDK 返回的对象属性自动渲染。以下是每个信息字段的详细来源和当前状态：

| 信息项           | WP 对象属性           | 数据来源                    | 当前状态  | 详细说明                                                                                          |
| ------------- | ----------------- | ----------------------- | ----- | --------------------------------------------------------------------------------------------- |
| **版本号**       | `version`         | API → `latest_version`  | ✅ 已支持 | 取自 `UpdatePackage.version`（最新版本记录），在后台「更新包」管理中填写                                              |
| **作者**        | `author`          | API → `author`          | ✅ 已支持 | 从 ZIP 自动提取（Author 头部）+ 后台可编辑；SDK Hook 2 传递到弹窗右侧信息栏                                            |
| **作者主页**      | `author_profile`  | API → `author_profile`  | ✅ 已支持 | 从 ZIP 自动提取（Author URI 头部）+ 后台可编辑；链接到开发者主页                                                     |
| **最近更新**      | `last_updated`    | 未传递                     | ❌ 不显示 | SDK 未设置此属性；后续可从 `UpdatePackage.created_at` 获取时间戳                                              |
| **需要 WP 版本**  | `requires`        | API → `requires_wp`     | ✅ 已支持 | 从 ZIP 自动提取（Requires at least 头部）；API 返回精确值，SDK 回退默认值 `'5.0'` 仅在字段缺失时生效                        |
| **兼容至**       | `tested`          | API → `tested_up_to`    | ✅ 已支持 | 从 ZIP 自动提取（Tested up to 头部）；API 返回精确值，SDK 回退默认值 `''` 仅在字段缺失时生效                                |
| **要求 PHP 版本** | `requires_php`    | API → `requires_php`    | ✅ 已支持 | 从 ZIP 自动提取（Requires PHP 头部）；API 返回精确值，SDK 回退默认值 `'7.4'` 仅在字段缺失时生效                             |
| **已启用安装数**    | `active_installs` | 未传递                     | ❌ 不显示 | SDK 未设置此属性；后续可从 License 模型统计激活数                                                               |
| **插件主页**      | `homepage`        | API → `plugin_homepage` | ✅ 已支持 | 从 ZIP 自动提取（Plugin URI 头部）+ 后台可编辑；SDK Hook 2 传递到弹窗右侧信息栏                                        |
| **平均评级**      | `rating`          | 未传递                     | ❌ 不显示 | SDK 未设置此属性；Product 模型已有 `rating` 字段但未在 API 中返回                                                |
| **评价数**       | `num_ratings`     | 未传递                     | ❌ 不显示 | SDK 未设置此属性；后续可从 `Review` 模型统计                                                                 |
| **下载链接**      | `download_link`   | API → `download_url`    | ✅ 已支持 | 带 `license_key` 鉴权参数的下载 URL：`/api/v2/download?product={slug}&version={ver}&license_key={key}` |

#### 各项支持状态汇总

| 状态    | 数量  | 字段                                           |
| ----- | --- | -------------------------------------------- |
| ✅ 已支持 | 8   | 版本号、作者、作者主页、需要 WP 版本、兼容至、要求 PHP 版本、插件主页、下载链接 |
| ❌ 未实现 | 4   | 最近更新、已启用安装数、平均评级、评价数                         |

---

### I.6 弹窗字段与后台配置完整对照表

| 弹窗区域 | 弹窗元素      | API 响应字段            | 后台数据模型                                      | 后台配置位置           | 状态  |
| ---- | --------- | ------------------- | ------------------------------------------- | ---------------- | --- |
| 横幅区  | 横幅图片      | `banner_url`        | `Product.banner_url`                        | 产品 → 编辑 → 横幅图片   | ✅   |
| 横幅区  | 插件图标      | `icon_url`          | `Product.icon_url`                          | 产品 → 编辑 → 插件图标   | ✅   |
| 菜单区  | 描述标签页     | `short_description` | `Product.short_description`                 | 产品 → 编辑 → 短描述    | ✅   |
| 菜单区  | 修订历史标签页   | `changelog`         | `UpdatePackage.changelog`                   | 更新包 → 编辑 → 更新日志  | ✅   |
| 菜单区  | 安装标签页     | —                   | —                                           | —                | ❌   |
| 菜单区  | 常见问题标签页   | —                   | —                                           | —                | ❌   |
| 菜单区  | 截图标签页     | —                   | `Product.screenshots`（已有未用）                 | —                | ❌   |
| 菜单区  | 评价标签页     | —                   | `Product.reviews`（已有未用）                     | —                | ❌   |
| 右侧栏  | 版本号       | `latest_version`    | `UpdatePackage.version`                     | 更新包 → 版本号        | ✅   |
| 右侧栏  | 插件主页      | `product_url`       | `Product.slug`（自动生成）                        | 产品 → Slug        | ✅   |
| 右侧栏  | 下载链接      | `download_url`      | `UpdatePackage.file_url`                    | 更新包 → 文件 URL     | ✅   |
| 右侧栏  | 作者        | `author`            | `Product.author`（ZIP 自动提取 + 后台可编辑）          | 产品 → 编辑 → 作者     | ✅   |
| 右侧栏  | 作者主页      | `author_profile`    | `Product.author_profile`（ZIP 自动提取 + 后台可编辑）  | 产品 → 编辑 → 作者主页   | ✅   |
| 右侧栏  | 最近更新      | —                   | `UpdatePackage.created_at`                  | —                | ❌   |
| 右侧栏  | 需要 WP 版本  | `requires_wp`       | `Product.requires_wp`（ZIP 自动提取）             | 产品 → 编辑 → WP 兼容性 | ✅   |
| 右侧栏  | 兼容至       | `tested_up_to`      | `Product.tested_up_to`（ZIP 自动提取）            | 产品 → 编辑 → WP 兼容性 | ✅   |
| 右侧栏  | 要求 PHP 版本 | `requires_php`      | `Product.requires_php`（ZIP 自动提取）            | 产品 → 编辑 → WP 兼容性 | ✅   |
| 右侧栏  | 插件主页      | `plugin_homepage`   | `Product.plugin_homepage`（ZIP 自动提取 + 后台可编辑） | 产品 → 编辑 → 插件主页   | ✅   |
| 右侧栏  | 安装数       | —                   | `License`（统计）                               | —                | ❌   |
| 右侧栏  | 评级        | —                   | `Product.rating`（已有未用）                      | —                | ❌   |

---

### I.7 ZIP 上传与自动解析

#### 功能概述

上传 WP 插件/主题 ZIP 文件后，系统自动解析 ZIP 中插件主文件的头部信息，提取元数据并回填表单字段，减少手动输入。

#### 创建产品流程

1. **选择产品类型**：选择“WordPress 插件”或“WordPress 主题”
2. **上传 ZIP 文件**：上传 WP 插件/主题的 ZIP 包（最大 100MB）
3. **自动提取信息**：系统自动解析 ZIP 中插件主文件（包含 `Plugin Name:` 头部的 PHP 文件）的头部，提取以下字段：
   - 插件名称（`Plugin Name`）→ 产品名称
   - 版本号（`Version`）→ 当前版本
   - 作者（`Author`）→ 作者名
   - 作者主页（`Author URI`）→ 作者主页 URL
   - 插件主页（`Plugin URI`）→ 插件主页 URL
   - 描述（`Description`）→ 产品描述和简短描述
   - WordPress 兼容性（`Requires at least` / `Tested up to` / `Requires PHP`）
4. **手动补充信息**：检查自动提取的结果，按需修改；手动配置横幅、图标、截图、定价等
5. **启用授权保护**（可选）：开启后保存时将自动向 ZIP 注入授权检查代码（详见 I.8）

#### 开发者前台

开发者通过 `/developer/products/create` 创建产品时，同样支持 ZIP 上传自动解析。选择 ZIP 文件后，系统通过 AJAX 调用后端解析接口，自动回填表单字段。

#### 字段来源对照表

| 字段     | 来源                                                              | 是否可手动修改 |
| ------ | --------------------------------------------------------------- | ------- |
| 产品名称   | ZIP 自动提取（`Plugin Name`）                                         | ✅       |
| 版本号    | ZIP 自动提取（`Version`）                                             | ✅       |
| 作者     | ZIP 自动提取（`Author`）                                              | ✅       |
| 作者主页   | ZIP 自动提取（`Author URI`）                                          | ✅       |
| 插件主页   | ZIP 自动提取（`Plugin URI`）                                          | ✅       |
| 描述     | ZIP 自动提取（`Description`）                                         | ✅       |
| WP 兼容性 | ZIP 自动提取（`Requires at least` / `Tested up to` / `Requires PHP`） | ✅       |
| 横幅/图标  | 手动上传                                                            | ✅       |
| 定价/分类  | 手动配置                                                            | —       |

> 💡 **注意：** ZIP 自动解析仅提取插件主文件头部信息。横幅、图标、截图、定价、分类等字段需要手动配置。

---

### I.8 授权保护配置

#### 概述

LicenseOS 支持在 WP 插件产品保存时自动注入授权保护代码。启用后，终端用户必须在 WordPress 后台激活许可证后才能访问插件的设置页面。

#### 启用方式

在创建或编辑产品时，勾选“启用授权保护”选项，点击保存。

#### 注入机制

1. 用户点击保存/提交后，后台下载已上传的 ZIP 文件
2. 系统打开 ZIP，找到插件主文件（包含 `Plugin Name:` 头部的 PHP 文件）
3. 在 `defined('ABSPATH')` 安全检查之后注入授权保护代码
4. 重新打包 ZIP 并重新上传，替换原文件存储

> 💡 **授权保护注入与 ZIP 自动解析是两个独立的步骤。** ZIP 上传时自动解析元数据（I.7），保存时才执行授权保护注入。

#### 注入的代码功能

- **许可证验证函数** `licenseos_check_license()`：
  - 从 WordPress 选项读取 `licenseos_license_key`
  - 调用 LicenseOS API 验证许可证有效性
  - 结果缓存 12 小时（通过 WordPress Transient），减少 API 调用
- **设置页面保护**：
  - 未激活时移除插件的管理菜单页面
  - 显示错误提示“请先激活许可证”
- **许可证激活页面**：
  - 在 WordPress 后台“设置”菜单下添加“LicenseOS License”页面
  - 管理员可在此输入许可证密钥并保存

#### 移除保护

如果后续需要移除授权保护（如用户要求退款），在后台编辑产品时关闭“启用授权保护”，上传新版本 ZIP 即可。

#### 注意事项

- 授权保护仅适用于 WP 插件类型
- API URL 和 API Key 从系统设置中读取，确保已正确配置
- 注入代码使用 `function_exists()` 检查，不会与 LicenseOS SDK 冲突
- 建议在生产环境测试后再启用

---

### I.9 管理员配置步骤

#### 步骤 1：创建 / 编辑产品

1. 登录后台 → 产品 → 新建或编辑已有产品
2. 填写以下关键字段：
   - **产品名称**（`name`）：显示在弹窗标题
   - **Slug**：必须与 WP 插件 SDK 中 `enableAutoUpdate('slug')` 的参数**完全一致**
   - **短描述**（`short_description`）：弹窗"描述"标签页的主要内容（支持 HTML）
   - **完整描述**（`description`）：产品详细文档（当短描述为空时，系统自动截取前 200 字作为描述标签页内容）
   - **横幅图片**（`banner_url`）：弹窗顶部横幅图片，推荐尺寸 772×250px（PNG/JPG）
   - **插件图标**（`icon_url`）：弹窗顶部插件图标，推荐尺寸 128×128px（PNG/SVG）
   - **截图**（`screenshots`）：产品截图 URL 数组，格式：`["url1", "url2", ...]`（当前 API 未使用，后续版本将支持）
   - **当前版本**（`version`）：插件当前版本号
   - **状态**（`status`）：设为 `published`

#### 步骤 2：创建更新包

1. 后台 → 更新包 → 新建

2. 选择关联产品

3. 填写版本号（**必须大于当前版本**才会触发 WP 更新提示）

4. 上传 ZIP 文件并填写文件 URL

5. 编写更新日志（`changelog`）：将显示在弹窗"修订历史"标签页，建议格式：
   
   ```html
   <h4>1.2.0</h4>
   <ul>
     <li>新增功能 A</li>
     <li>修复问题 B</li>
   </ul>
   ```

#### 步骤 3：确认 SDK 集成

确保 WP 插件的主文件中已正确调用：

```php
// 引入 SDK
require_once __DIR__ . '/licenseos-sdk.php';

// 初始化 SDK（在 plugins_loaded 或插件加载时调用）
LicenseOS::init('https://your-domain.com/api/v2', 'your-api-key');

// 启用自动更新（slug 必须与后台产品 slug 一致）
$license_key = get_option('your_license_key_option', '');
if ($license_key) {
    LicenseOS::enableAutoUpdate('your-plugin-slug', $license_key);
}
```

> **注意：** `enableAutoUpdate()` 的第一个参数 slug 必须与后台产品的 slug 字段完全一致，否则 API 无法匹配产品数据。

#### 步骤 4：验证弹窗

1. 在 WP 后台 → 插件列表，找到对应插件
2. 点击"查看详情"链接
3. 确认弹窗正常弹出，描述和修订历史内容正确
4. 在 WP 后台 → 更新，确认"查看版本 x.x.x 详情"链接可用
5. 确认右侧信息栏显示版本号和插件主页链接

---

### I.10 SDK“查看详情”弹窗工作原理

#### 触发流程

```
WP 用户点击"查看详情"或"查看版本 x.x.x 详情"
        ↓
WP 核心触发 plugins_api filter（action = plugin_information, slug = plugin_slug）
        ↓
LicenseOS SDK Hook 2 拦截（licenseos-sdk.php 第 123 行）
        ↓
校验 action === 'plugin_information' 且 slug 匹配，否则放行给 WP 默认处理
        ↓
SDK 调用 LicenseOS API：GET /api/v2/update/check?product={slug}
        ↓
UpdateService.checkForUpdate() 执行：
  1. 查询 Product（按 slug 匹配，状态 = published）
  2. 查询最新 UpdatePackage（按 created_at 降序取第一条）
  3. 构建 JSON 响应
        ↓
SDK 将 API 响应映射为 WP 标准 stdClass 对象（字段名与 WP.org API 一致）
        ↓
WP 核心接管渲染：
  - 根据 sections 数组生成左侧标签页菜单
  - 根据对象属性填充右侧信息栏
  - 尝试加载横幅图片（第三方插件场景下失败，显示空白）
```

#### 关键源码位置

| 组件               | 文件                                    | 行号      | 功能                                      |
| ---------------- | ------------------------------------- | ------- | --------------------------------------- |
| SDK Hook 2       | `app/SDK/WordPress/licenseos-sdk.php` | 122-150 | 拦截 `plugins_api`，构建弹窗数据对象               |
| SDK Hook 1       | `app/SDK/WordPress/licenseos-sdk.php` | 84-120  | 注入更新信息到 WP 插件列表                         |
| SDK Hook 3       | `app/SDK/WordPress/licenseos-sdk.php` | 153-159 | 插件行自定义元信息                               |
| UpdateService    | `app/Services/UpdateService.php`      | 20-72   | `checkForUpdate()` 方法，查询产品+版本并构建 API 响应 |
| Product 模型       | `app/Models/Product.php`              | —       | 产品数据模型，包含名称、描述、截图等字段                    |
| UpdatePackage 模型 | `app/Models/UpdatePackage.php`        | —       | 更新包模型，包含版本号、文件 URL、更新日志                 |

#### 关键技术点

- WordPress 通过 `plugins_api` filter 允许第三方拦截插件信息查询请求
- SDK 的 Hook 2 监听该 filter，当 `action=plugin_information` 且 `slug` 匹配时接管请求
- API 返回的数据被映射为 WordPress 标准的 `stdClass` 对象，字段名与 WP.org 插件 API 一致
- WordPress 核心自动渲染弹窗 UI，无需额外 CSS/JS
- **弹窗中只显示 `sections` 数组中存在的标签页**——当前仅有 `description` 和 `changelog`

---

### I.11 常见问题排查

| 问题                               | 可能原因                                      | 解决方案                                                       |
| -------------------------------- | ----------------------------------------- | ---------------------------------------------------------- |
| "查看详情"点击无反应                      | SDK 未集成或 `enableAutoUpdate()` 未调用         | 检查插件主文件是否正确引入 SDK 并调用方法                                    |
| 弹窗内容为空                           | API Key 无效或产品 slug 不匹配                    | 确认 `init()` 的 API Key 正确，`enableAutoUpdate()` 的 slug 与后台一致 |
| 描述区显示不完整                         | `short_description` 为空且 `description` 过短  | 在后台产品编辑中填写短描述                                              |
| 修订历史显示 "No changelog available." | 最新更新包未填写 `changelog`                      | 在更新包管理中补充更新日志                                              |
| 弹窗只有"描述"和"修订历史"两个标签页             | SDK 当前仅传递这两个标签页                           | 这是当前版本的正常行为，其他标签页将在后续版本支持                                  |
| 弹窗顶部横幅区域空白                       | 产品未上传横幅图片，`banner_url` 为空                 | 在后台产品编辑中上传横幅图片（推荐 772×250px）                               |
| 右侧信息栏大部分字段为空                     | SDK 未传递作者、评级等属性                           | 这是当前版本的已知限制，后续版本将逐步补全                                      |
| 弹窗显示旧版本信息                        | WP 瞬态缓存未更新                                | 在 WP 后台清除瞬态缓存：`delete_site_transient('update_plugins')`    |
| 弹窗完全空白                           | 插件 slug 在 LicenseOS 后台不存在或产品状态非 published | 确认后台产品的 slug 字段与 SDK 调用参数完全一致，且状态为 `published`             |
| 下载链接 403                         | `license_key` 未传递或无效                      | 确认 `enableAutoUpdate()` 的第二个参数传入了有效的 license key           |

---

### I.12 配置检查清单

管理员可对照以下清单逐项检查，确保弹窗能正确展示所有已支持的内容：

#### 后台产品配置

- [ ] 产品 **Slug** 与 WP 插件中 `enableAutoUpdate('xxx')` 的参数完全一致
- [ ] 产品 **状态** 设为 `published`
- [ ] 产品 **短描述**（`short_description`）已填写（弹窗"描述"标签页内容）
- [ ] 产品 **完整描述**（`description`）已填写（短描述为空时的回退内容）
- [ ] 产品 **横幅图片**（`banner_url`）已上传（弹窗顶部横幅，推荐 772×250px）
- [ ] 产品 **插件图标**（`icon_url`）已上传（弹窗顶部图标，推荐 128×128px）
- [ ] 产品 **当前版本**（`version`）已填写

#### 更新包配置

- [ ] 至少创建了一个更新包记录
- [ ] 更新包 **版本号** 大于产品当前版本（否则不会触发更新提示）
- [ ] 更新包 **文件 URL** 已填写且可访问
- [ ] 更新包 **更新日志**（`changelog`）已填写（弹窗"修订历史"标签页内容）

#### WP 插件端集成

- [ ] 插件主文件中引入了 `licenseos-sdk.php`
- [ ] 调用了 `LicenseOS::init($api_url, $api_key)`，API URL 和 Key 正确
- [ ] 调用了 `LicenseOS::enableAutoUpdate($slug, $license_key)`，slug 与后台一致
- [ ] `$license_key` 参数传入了已激活的有效许可证密钥

#### 验证检查

- [ ] WP 后台 → 插件列表，对应插件下方有“查看详情”链接
- [ ] 点击“查看详情”弹窗正常弹出
- [ ] 弹窗“描述”标签页内容正确显示
- [ ] 弹窗“修订历史”标签页内容正确显示
- [ ] 弹窗顶部横幅图片和插件图标正常显示
- [ ] 右侧信息栏显示正确的版本号、作者、兼容性、插件主页链接
- [ ] WP 后台 → 更新页面，有更新提示时“查看版本 x.x.x 详情”链接可用

#### ZIP 上传与授权保护

- [ ] ZIP 上传测试：上传插件 ZIP 确认自动解析正常（产品名称、版本、作者、描述等字段自动回填）
- [ ] 授权保护测试：启用保护后保存，确认 ZIP 注入成功（下载 ZIP 检查是否包含授权检查代码）
- [ ] 许可证激活测试：在 WP 后台测试许可证激活流程（设置 → LicenseOS License）
- [ ] API 字段验证：调用 `/api/v2/update/check` 确认返回所有新字段（`author`、`author_profile`、`requires_wp`、`tested_up_to`、`requires_php`、`plugin_homepage`）

---

### I.13 后续版本规划

以下功能将在后续版本中逐步支持：

- [x] ~~Product 模型添加 `requires_wp`、`tested_up_to`、`requires_php` 字段，API 返回精确兼容性信息~~ ✅ V3.4 已实现（ZIP 自动提取）
- [x] ~~SDK Hook 2 传递 `banners` 和 `icons` 属性，支持横幅和图标显示~~ ✅ 已实现
- [x] ~~SDK Hook 2 传递 `author`、`author_profile` 到右侧信息栏~~ ✅ V3.4 已实现（ZIP 自动提取）
- [x] ~~API 返回 `requires_wp`、`tested_up_to`、`requires_php`、`plugin_homepage` 字段~~ ✅ V3.4 已实现
- [ ] SDK Hook 2 传递 `installation`、`faq` 标签页内容
- [ ] 将 `Product.screenshots` 映射到 `sections['screenshots']`
- [ ] 将 `Product.reviews` 映射到 `sections['reviews']`
- [ ] 传递 `last_updated`、`active_installs`、`rating`、`num_ratings` 到右侧信息栏
- [ ] “安装”和“常见问题”标签页内容独立字段或 `readme.txt` 解析

> V3.4 已实现的字段（author、author_profile、requires_wp、tested_up_to、requires_php、plugin_homepage）均通过 ZIP 上传自动解析 + API 返回 + SDK Hook 2 传递的完整链路支持。SDK 代码中保留了 fallback 逻辑（如 `$update['requires_wp'] ?? '5.0'`），确保未填写时不会报错。
