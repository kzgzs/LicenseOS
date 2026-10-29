# Cloudflare R2 对象存储配置教程

> LicenseOS 支持将产品文件存储迁移到 Cloudflare R2，解决服务器磁盘空间不足的问题。
> R2 优势：S3 兼容 API、出站流量免费、按用量计费、无最低消费。

---

## 一、Cloudflare 网站操作

### 1. 登录 Cloudflare

- 打开 https://dash.cloudflare.com
- 登录或注册账号

### 2. 创建 R2 Bucket

1. 左侧菜单点击 **R2 Object Storage**
2. 点击 **Create bucket**
3. 填写 Bucket 名称（如 `licenseos-files`）
4. 区域选默认，或选离服务器最近的区域
5. 点击 **Create bucket** 确认

### 3. 创建 API Token

1. 在 R2 页面点击 **Manage R2 API Tokens**（或直接访问 https://dash.cloudflare.com/profile/api-tokens）
2. 点击 **Create API Token**
3. 模板选择 **Workers R2 Storage: Edit**，或手动配置：
   - 权限：**Object Read & Write**
   - 指定 Bucket：选你刚创建的（或选 All buckets）
   - TTL：选永不过期（No expiration）
4. 点击 **Create Token**
5. **立即保存以下信息**（只显示一次）：

| 字段                | 示例值                                        |
| ----------------- | ------------------------------------------ |
| Access Key ID     | `a1b2c3d4e5f6...`                          |
| Secret Access Key | `xYz123AbC456...`                          |
| Endpoint URL      | `https://abcdefg.r2.cloudflarestorage.com` |

> 关掉页面后就看不到了，务必当场复制保存。

---

## 二、配置方式（二选一）

### 方式 A：Admin 后台面板配置（推荐）

1. 登录 Admin 后台 → **System Settings** → **Storage** Tab
2. 选择 **Storage Disk**：`Cloudflare R2 (cloud storage)`
3. 填入 **Access Key ID**、**Secret Access Key**、**Bucket Name**、**Endpoint URL**
4. 点击 **Save Settings**

后台会自动写入 `.env` 并清除配置缓存，立即生效。

### 方式 B：手动编辑 .env

在项目根目录的 `.env` 文件末尾添加以下配置：

```env
# ========== Cloudflare R2 Storage ==========
PRODUCT_STORAGE_DISK=r2
R2_ACCESS_KEY_ID=你的Access Key ID
R2_SECRET_ACCESS_KEY=你的Secret Access Key
R2_BUCKET=licenseos-files
R2_ENDPOINT=https://你的账户ID.r2.cloudflarestorage.com
R2_CUSTOM_DOMAIN=
```

**各字段说明：**

| 字段                     | 说明                                | 必填  |
| ---------------------- | --------------------------------- | --- |
| `PRODUCT_STORAGE_DISK` | 设为 `r2` 后新上传走 R2，设为 `public` 走本地  | 是   |
| `R2_ACCESS_KEY_ID`     | Cloudflare API Token 的 Access Key | 是   |
| `R2_SECRET_ACCESS_KEY` | Cloudflare API Token 的 Secret Key | 是   |
| `R2_BUCKET`            | 你创建的 Bucket 名称                    | 是   |
| `R2_ENDPOINT`          | R2 端点 URL（创建 Token 时显示）           | 是   |
| `R2_CUSTOM_DOMAIN`     | 自定义域名（如有），没有留空                    | 否   |

配置完成后清缓存使其生效：

```bash
/www/server/php/83/bin/php artisan config:clear
```

---

## 三、已有本地文件迁移到 R2

如果服务器上已有产品文件（本地存储），可以用迁移命令同步到 R2：

### 3.1 预览模式（不实际操作）

```bash
/www/server/php/83/bin/php artisan files:sync-to-r2 --dry-run
```

会列出哪些文件将被同步，不上传也不修改数据库。

### 3.2 正式迁移

```bash
/www/server/php/83/bin/php artisan files:sync-to-r2
```

执行后会：

- 将本地文件上传到 R2
- 更新数据库中 `update_packages.file_url` 和 `products.download_url` 指向 R2
- 旧的本地文件保留（安全起见）

### 3.3 迁移后删除本地文件

确认 R2 上文件全部正常后，可释放本地磁盘空间：

```bash
/www/server/php/83/bin/php artisan files:sync-to-r2 --delete-local
```

> 建议迁移后先在 R2 控制台确认文件都在，再删本地。

---

## 四、回退到本地存储

如果 R2 出问题需要回退：

```env
PRODUCT_STORAGE_DISK=public
```

```bash
/www/server/php/83/bin/php artisan config:clear
```

系统会自动检测文件位置：R2 上有的走 R2 签名链接，本地的走本地路径，两者互不影响。

---

## 五、Composer 依赖安装

R2 存储需要 S3 兼容驱动。如果执行迁移命令报错缺少驱动，需安装：

```bash
cd /www/wwwroot/sz.djkz.top && composer require league/flysystem-aws-s3-v3
```

> 如果服务器在国内，GitHub 下载不稳定，可配置腾讯云镜像：
> 
> ```bash
> composer config -g repo.packagist composer https://mirrors.cloud.tencent.com/composer/
> ```

---

## 六、部署踩坑记录：Artisan 命令 make() on null

### 问题现象

部署新代码后，所有 artisan 命令报错：

```
Error: Call to a member function make() on null
at vendor/laravel/framework/src/Illuminate/Console/Command.php:173
```

### 根因

Laravel 11 的命令懒加载机制（`ContainerCommandLoader`）从容器解析命令时，
跳过了 `setLaravel()` 调用，导致命令实例的 `$this->laravel` 为 null。

### 修复

在 `app/Providers/AppServiceProvider.php` 的 `register()` 方法中添加 resolving 回调：

```php
use Illuminate\Console\Command as ConsoleCommand;

public function register(): void
{
    // Fix: Laravel 11 lazy-loaded commands miss setLaravel() call.
    $this->app->resolving(ConsoleCommand::class, function ($command, $app) {
        if (method_exists($command, 'setLaravel')) {
            $command->setLaravel($app);
        }
    });
}
```

### 修复后操作顺序

```bash
# 1. 重建自动加载（跳过脚本钩子，避免 package:discover 干扰）
composer dump-autoload --no-scripts

# 2. 验证 artisan 恢复正常
/www/server/php/83/bin/php artisan config:clear

# 3. 完整安装依赖
composer install --no-scripts

# 4. 完整 dump（含 package:discover，此时不会再崩）
composer dump-autoload
```

### 诊断过程摘要

| 步骤             | 命令                                       | 结果                      |
| -------------- | ---------------------------------------- | ----------------------- |
| Bootstrap 测试   | PHP 内联脚本加载 app + bootstrap               | 通过                      |
| Kernel 解析      | `$kernel->bootstrap()`                   | 通过                      |
| Application 检查 | `$artisan->laravel` 反射                   | SET                     |
| 命令检查           | `$artisan->get('config:clear')->laravel` | **NULL**                |
| 根因定位           | `ContainerCommandLoader::get()`          | 从容器解析但未调 `setLaravel()` |

---

## 涉及文件

| 文件                                       | 说明                   |
| ---------------------------------------- | -------------------- |
| `app/Providers/AppServiceProvider.php`   | Artisan 懒加载修复        |
| `app/Services/ProductStorageService.php` | R2/本地双磁盘存储服务         |
| `app/Console/Commands/SyncFilesToR2.php` | 本地→R2 迁移命令           |
| `app/Filament/Pages/Settings.php`        | Admin 后台 Storage Tab |
| `config/filesystems.php`                 | R2 磁盘配置              |
| `.env`                                   | R2 环境变量              |
