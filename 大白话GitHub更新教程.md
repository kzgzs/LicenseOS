# 大白话教程：把 GitHub 最新代码更新到 sz.djkz.top

> 适用：LicenseOS（Laravel 11 + PHP 8.3 + 宝塔面板）。
> 一句话原理：GitHub 上是"新版代码"，服务器目录里是"旧版代码"。更新 = **换代码文件 + 补数据库结构 + 清缓存重启**。
> 三样东西永远不碰：`.env`（你的配置）、`storage/`（上传的图片/安装包/用户文件）、数据库里的数据。更新只加表加字段，不会清数据。

---

## 一、动手前先备三样

1. **GitHub 令牌（PAT）**：仓库是私有的，服务器下载代码要凭它。
   打开 github.com → 右上头像 → Settings → 左下 Developer settings → Personal access tokens → Tokens (classic) → Generate new token → 勾选 `repo` → 生成 → **立刻复制存好**（只显示一次）。下文用 `你的PAT` 代替。
2. **宝塔终端**：宝塔面板 → 终端（或 SSH 工具连服务器）。
3. **确认站点目录**：宝塔 → 网站 → 点 sz.djkz.top → 看根目录，一般是 `/www/wwwroot/sz.djkz.top`。下文所有命令里的这个路径，换成你实际的。

**先备份（必做）**：宝塔 → 文件 → 进站点目录 → 全选压缩成一个 zip 放别处；再 宝塔 → 数据库 → 对应库点"备份"。万一更新坏了，解压回去就恢复。

---

## 二、把新代码弄到服务器（两条路，选一条）

### 路 A：服务器目录当初是 git clone 下来的（目录里有 `.git` 隐藏文件夹）

```bash
cd /www/wwwroot/sz.djkz.top
git pull https://你的PAT@github.com/kzgzs/LicenseOS.git main
```

- 提示冲突或拉不动：说明服务器上有人改过代码文件。备份做好的前提下，用下面这条强制对齐 GitHub（服务器上的改动会被丢弃，`.env` 和 `storage` 不受影响，它们不在 git 管理里）：

```bash
git fetch https://你的PAT@github.com/kzgzs/LicenseOS.git main
git reset --hard FETCH_HEAD
```

### 路 B：服务器目录当初是上传 zip 解压的（没有 `.git`，最常见）

```bash
cd /tmp
curl -L -H "Authorization: token 你的PAT" -o los.zip https://codeload.github.com/kzgzs/LicenseOS/zip/refs/heads/main
unzip -o los.zip -d /tmp/losnew
cp -rf /tmp/losnew/LicenseOS-main/. /www/wwwroot/sz.djkz.top/
```

大白话解释最后一条：把新代码**同名覆盖**进站点目录。它不会删你的东西——zip 里本来就没有 `.env`、没有 `storage` 里的上传文件、没有 `vendor`，所以这些原样保留。

> 想"精确镜像"（连旧版多余文件也清掉）且服务器装了 rsync，可改用：
> `rsync -a --exclude='.env' --exclude='storage' --exclude='vendor' --exclude='bootstrap/cache' --exclude='.git' /tmp/losnew/LicenseOS-main/ /www/wwwroot/sz.djkz.top/`
> 没装 rsync 就用上面的 cp，足够。

---

## 三、换完代码后的固定四步（两条路都要做）

在宝塔终端依次整段复制执行：

**第 1 步：装依赖（composer）**

```bash
cd /www/wwwroot/sz.djkz.top
COMPOSER_ALLOW_SUPERUSER=1 /www/server/php/83/bin/php -d memory_limit=512M -d opcache.jit=disable composer.phar install --no-dev --optimize-autoloader --no-interaction --no-scripts
```

- 提示 `composer.phar` 不存在：先执行 `wget https://mirrors.aliyun.com/composer/composer.phar -O composer.phar && chmod +x composer.phar` 再重来。
- `--no-scripts` 别删：避开 Laravel 11 已知的安装脚本报错坑。

**第 2 步：补数据库结构（只加不删，重复跑也安全）**

```bash
/www/server/php/83/bin/php artisan migrate --force
```

**第 3 步：发布后台界面文件 + 清缓存重建**

```bash
/www/server/php/83/bin/php artisan filament:assets
/www/server/php/83/bin/php artisan storage:link
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan route:cache
/www/server/php/83/bin/php artisan view:cache
```

- `storage:link` 提示链接已存在：正常，忽略。
- 万一后台打开**没样式/登录按钮没反应**：按《BT_PANEL_DEPLOY.md》第八章 8.1→8.2→8.3 三个脚本再跑一遍（把脚本里"你的站点目录"换成实际路径），然后重跑本步后三条缓存命令。

**第 4 步：重启 PHP（让 opcache 里的旧代码失效）**

宝塔 → 软件商店 → PHP-8.3 → 服务 → 重启。或命令：

```bash
/etc/init.d/php-fpm-83 restart
```

---

## 四、怎么确认更新成功

1. 浏览器开 https://sz.djkz.top 首页和后台登录页：能开、有样式、能登录 = 基本成功。
2. 后台 → 产品 → 编辑任一产品 → 价格档：能看到"自定义年数（1-100 年）"选项 = 最新代码已生效（这是最近一批的新功能）。
3. 终端跑 `/www/server/php/83/bin/php artisan about`：不报错、能列出环境信息 = 框架健康。
4. 接口抽查：`curl -s -X POST https://sz.djkz.top/api/v2/license/check -H "Content-Type: application/json" -H "X-API-Key: 你的API密钥" -d '{"license_key":"随便一个key"}'` 返回 JSON（哪怕是"无效"）= 接口活着。

---

## 五、更新坏了怎么回退

1. 宝塔 → 文件 → 把站点目录里新覆盖的内容删掉不现实——直接用第一步的备份 zip：先停站点（宝塔 → 网站 → 停用），把备份 zip 解压覆盖回站点目录，再启用站点。
2. 数据库不用回退：新加的表字段旧代码用不到，留着无害。
3. 回退后同样重启一次 PHP。

---

## 六、几个常踩的坑

| 现象 | 原因与办法 |
| --- | --- |
| curl 下载 zip 只有几 KB、解压报错 | PAT 错了或过期 → 重新生成；私有仓库必须带 Authorization 头 |
| 500 / Permission denied | `storage/`、`bootstrap/cache/` 权限丢了 → 宝塔文件管理器设 775、所有者 www |
| 首页 404 其他页正常 | 路由缓存坏 → 重跑第 3 步缓存三条 |
| 后台纯 HTML 没样式 | 界面文件没发布 → 第 3 步 filament:assets 或第八章脚本 |
| composer 卡住不动 | 没加 `COMPOSER_ALLOW_SUPERUSER=1`，或内存不够（命令里已带 512M） |
| 更新后页面还是旧样子 | PHP opcache 没重启 → 第 4 步；浏览器再 Ctrl+F5 |
| 把 PAT 写进了 git remote | 路 A 用完执行 `git remote set-url origin https://github.com/kzgzs/LicenseOS.git` 把令牌从配置里抹掉 |

---

## 附：顺带一件事（可选）

代码更新完后，若要把站点上 EquiLedger 的安装包也换成新版：后台 → 产品 → EquiLedger → 上传新的 `EquiLedger-1.3.0.zip` 作为新版本包（版本号填 1.3.0，大于旧版才会触发更新提示）。这一步只换下载文件，与代码更新互不影响。