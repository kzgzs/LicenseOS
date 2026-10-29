# LicenseOS Bug 修复 - 部署检查清单

## ✅ 已完成修复的文件列表

### PHP 文件修改
1. `app/Http/Controllers/Developer/ProductController.php` - 删除创建时的自动注入 + 增强 injectNow() 验证
2. `app/Services/LicenseProtectionInjector.php` - 新增 verifyInjection() 方法

### JSON 文件修改
3. `lang/zh_CN.json` - 添加友好提示文案

### Blade 模板修改
4. `resources/views/developer/products/show.blade.php` - 添加黄色引导横幅
5. `resources/views/developer/products/create.blade.php` - 更新复选框提示文案

---

## 📋 重新部署步骤

### Step 1: 备份当前数据（如果需要）
```bash
# 导出数据库
mysqldump -u 用户名 -p 数据库名 > backup_before_fix.sql

# 备份重要文件
tar czf important_files.tar.gz uploads/ wp-content/plugins/my-plugin/
```

### Step 2: 清理旧文件
```bash
cd /path/to/site

# 警告：这会删除所有项目文件！请确保已备份重要数据
rm -rf *

# 或者选择性删除（推荐）
rm -rf app/ http/ Controllers/ Services/ lang/ resources/views/
```

### Step 3: 上传新版本文件
```bash
# 方式 A: 直接上传整个项目包
tar xzf licenseos_fixed.tar.gz -C /path/to/site/

# 方式 B: Git 更新（如果用了版本控制）
git pull origin main
```

### Step 4: 配置环境
```bash
cd /path/to/site

# 复制.env 文件
cp .env.example .env

# 编辑配置文件
nano .env

# 设置关键配置
DB_HOST=localhost
DB_DATABASE=licenseos
DB_USERNAME=root
DB_PASSWORD=your_password

# 生成 APP_KEY
php artisan key:generate

# 安装依赖
composer install --no-dev --optimize-autoloader

# 运行迁移
php artisan migrate --force

# 缓存配置
php artisan config:cache
php artisan route:cache
```

### Step 5: 设置权限
```bash
# 目录所有权（根据服务器用户调整）
chown -R www-data:www-data storage bootstrap/cache

# 目录权限
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/framework/cache data storage/framework/sessions storage/framework/views storage/logs

# Laravel 存储链接
php artisan storage:link
```

### Step 6: 验证部署
```bash
# 清除缓存
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# 检查应用状态
php artisan about

# 访问网站
# 浏览器打开：http://yourdomain.com
# 登录开发者后台测试功能
```

---

## 🧪 测试流程（必须全部通过！）

### Test 1: 创建产品不勾选授权保护
- [ ] 登录开发者后台
- [ ] 创建新产品 → WordPress Plugin → **不勾选** 授权保护
- [ ] 上传 ZIP → 提交
- [ ] ✅ 预期：创建成功，无错误

### Test 2: 创建产品勾选授权保护（核心 Bug #1）
- [ ] 创建新产品 → WordPress Plugin → **勾选** 授权保护  
- [ ] 上传 ZIP → 提交
- [ ] ✅ 预期：**NO 500 错误！**
- [ ] ✅ 预期：页面显示黄色警告："授权保护待激活"
- [ ] ✅ 预期：可以点击进入详情页看到"一键注入"按钮

### Test 3: 一键注入并验证（核心 Bug #2）
- [ ] 在产品详情页点击绿色 **"一键注入"** 按钮
- [ ] 勾选所有模块 → 提交
- [ ] ✅ 预期：成功提示 "✓ 一键注入成功！..."
- [ ] ✅ 预期：UpdatePackage 表有 injection_modules 记录
- [ ] 下载注入后的 ZIP
- [ ] 解压并上传到 WordPress `/wp-content/plugins/`
- [ ] 在 WP 后台启用插件
- [ ] 进入插件设置页
- [ ] ✅ **关键：必须看到 License 激活表单！**

### Test 4: 一键复原测试
- [ ] 对产品点击红色 **"一键复原"** 按钮
- [ ] 确认对话框
- [ ] ✅ 预期：复原成功提示
- [ ] 下载复原后的 ZIP
- [ ] 安装到 WordPress
- [ ] 进入插件设置页
- [ ] ✅ 预期：**无 License 表单**（完全干净）

### Test 5: 重新注入测试
- [ ] 再次点击 **"一键注入"**
- [ ] 选择不同模块组合 → 提交
- [ ] ✅ 预期：成功注入，无代码块重复
- [ ] 下载安装到 WordPress
- [ ] ✅ 预期：只有本次选择的模块生效

---

## 🔍 常见问题排查

### Q1: 仍然出现 500 错误？
```bash
# 查看 Laravel 日志
tail -f storage/logs/laravel.log

# 常见原因:
# - storage 目录权限不足
# - .env 配置错误
# - Composer 依赖未安装
```

### Q2: WordPress 端看不到 License 表单？
```bash
# 检查 ZIP 是否包含 SDK
unzip -l injected.zip | grep licenseos-sdk.php

# 检查 mu-plugins
ls -la /wp-content/mu-plugins/licenseos*.php

# 清理 WordPress 缓存
wp plugin delete my-plugin --force
wp plugin install my-plugin.zip --activate
```

### Q3: 注入验证失败？
```bash
# 查看详细错误日志
grep "inject_verification" storage/logs/laravel.log

# 可能是 API URL 或 API Key 配置问题
# 检查 SiteSetting 表中的 site_url 配置
mysql -u root -p -e "SELECT * FROM site_settings WHERE name LIKE '%site_url%';"
```

---

## 📊 性能检查

### 页面加载速度
- [ ] 首页 < 2 秒
- [ ] 产品详情页 < 3 秒
- [ ] 上传 ZIP (< 5MB) < 10 秒

### 存储使用
- [ ] storage/ 目录大小合理
- [ ] 临时 ZIP 文件已清理（应该都在 finally 中删除）

### 数据库查询
- [ ] 产品列表查询 < 100ms
- [ ] 注入操作总耗时 < 30 秒

---

## ✨ 最终验收标准

✅ **Bug #1 已修复**: 勾选授权保护创建产品不再出现 500 错误  
✅ **Bug #2 已修复**: 一键注入后 WordPress 能正确显示 License 表单  
✅ **操作简单**: 开发者上传原版 ZIP 即可接入  
✅ **结果可靠**: 注入必成功，复原必彻底  
✅ **走时放心**: 一键复原立即恢复为干净代码  

---

## 🎉 恭喜您！

如果您的站点完成了以上所有检查和测试，那么说明 Bug 修复已经完美完成！👏

**现在您可以**:
1. 向开发者推广这个更稳定的授权保护系统
2. 开始接受新产品的授权接入请求
3. 为用户提供完整的授权管理体验

祝您的业务蒸蒸日上！🚀
