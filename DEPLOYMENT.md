# LicenseOS 服务器发布教程

> 从零到上线的完整指南，适用于任何 Linux 云服务器

---

## 目录

1. [服务器要求](#一服务器要求)
2. [购买与初始化服务器](#二购买与初始化服务器)
3. [安装 Docker 环境](#三安装-docker-环境)
4. [上传代码到服务器](#四上传代码到服务器)
5. [配置环境变量](#五配置环境变量)
6. [首次部署](#六首次部署)
7. [配置域名与 SSL](#七配置域名与-ssl)
8. [配置支付 Webhook](#八配置支付-webhook)
9. [验证部署](#九验证部署)
10. [日常运维](#十日常运维)
11. [版本更新部署](#十一版本更新部署)
12. [常见问题排查](#十二常见问题排查)
13. [性能优化建议](#十三性能优化建议)

---

## 一、服务器要求

| 项目      | 最低要求             | 推荐配置             |
| ------- | ---------------- | ---------------- |
| **CPU** | 2 核              | 4 核              |
| **内存**  | 4 GB             | 8 GB             |
| **硬盘**  | 40 GB SSD        | 80 GB SSD        |
| **系统**  | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| **网络**  | 1 Mbps           | 5 Mbps+          |
| **端口**  | 22, 80, 443      | 22, 80, 443      |

**推荐云服务商**：

- 国际：AWS EC2 / DigitalOcean / Vultr / Linode
- 国内：阿里云 ECS / 腾讯云 CVM / 华为云 ECS

---

## 二、购买与初始化服务器

### 2.1 SSH 连接服务器

```bash
# 用你购买的云服务商提供的 IP 和密钥连接
ssh root@你的服务器IP

# 或使用密钥文件连接
ssh -i ~/.ssh/your_key.pem root@你的服务器IP
```

### 2.2 系统初始化

```bash
# 更新系统
apt update && apt upgrade -y

# 安装基础工具
apt install -y git curl wget unzip htop ufw fail2ban

# 配置防火墙
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# 配置 fail2ban（防暴力破解）
cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
systemctl enable fail2ban
systemctl start fail2ban
```

### 2.3 创建部署用户（推荐，不用 root）

```bash
# 创建 deploy 用户
adduser deploy
usermod -aG sudo deploy

# 配置 SSH 密钥登录（在你本地电脑执行）
ssh-copy-id deploy@你的服务器IP

# 测试免密登录
ssh deploy@你的服务器IP
```

---

## 三、安装 Docker 环境

### 3.1 安装 Docker

```bash
# 一键安装 Docker（官方脚本）
curl -fsSL https://get.docker.com | sh

# 将 deploy 用户加入 docker 组（免 sudo）
usermod -aG docker deploy

# 重新登录使 docker 组生效
su - deploy

# 验证安装
docker --version
docker compose version
```

### 3.2 配置 Docker 开机启动

```bash
systemctl enable docker
systemctl start docker
```

---

## 四、上传代码到服务器

### 方式 A：Git 克隆（推荐）

```bash
# 切换到 deploy 用户
su - deploy

# 克隆代码到 /home/deploy 目录
cd /home/deploy
git clone https://github.com/你的用户名/LicenseOS.git
cd LicenseOS
```

### 方式 B：SCP 上传

```bash
# 在你本地电脑执行（将 e:\LicenseOS 上传到服务器）
scp -r e:\LicenseOS deploy@你的服务器IP:/home/deploy/LicenseOS
```

### 方式 C：rsync 同步（增量更新最快）

```bash
# 在你本地电脑执行
rsync -avz --exclude='vendor' --exclude='node_modules' \
  e:/LicenseOS/ deploy@你的服务器IP:/home/deploy/LicenseOS/
```

---

## 五、配置环境变量

### 5.1 复制生产环境模板

```bash
cd /home/deploy/LicenseOS
cp .env.production .env
```

### 5.2 编辑 .env 文件

```bash
nano .env
```

**必须修改的配置项：**

```ini
# ← 改为你的域名
APP_URL=https://your-domain.com

# ← 数据库密码（生成强密码：openssl rand -base64 32）
DB_PASSWORD=这里填强密码
DB_ROOT_PASSWORD=这里填另一个强密码

# ← Stripe 密钥（从 dashboard.stripe.com 获取）
STRIPE_KEY=pk_live_xxxxxxxxxxxxxxxxxxxx
STRIPE_SECRET=sk_live_xxxxxxxxxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxx

# ← Meilisearch 主密钥（生成强密码）
MEILISEARCH_KEY=这里填强密码

# ← 邮件配置（可选，用于通知）
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@your-domain.com
MAIL_PASSWORD=你的SMTP密码
```

### 5.3 生成密钥的工具命令

```bash
# 生成数据库密码
openssl rand -base64 32
# 输出示例: K7gNU3sdo+OL0wNhqoVWhr3g6s1xYv72ol/pe/Unols=

# 生成 Meilisearch 密钥
openssl rand -hex 16
# 输出示例: a3f1c9e2b7d456789abcdef012345678
```

---

## 六、首次部署

### 6.1 运行部署脚本

```bash
cd /home/deploy/LicenseOS

# 赋予脚本执行权限
chmod +x docker/scripts/*.sh

# 首次部署（包含数据库初始化 + 演示数据）
bash docker/scripts/deploy.sh --first-time
```

**脚本自动完成 8 个步骤：**

```
[1/8] Pre-flight checks...         ← 检查 Docker 环境
[2/8] Environment setup...         ← 配置 .env
[3/8] Checking APP_KEY...          ← 生成 Laravel 加密密钥
[4/8] Building containers...       ← 构建 Docker 镜像（约3-5分钟）
[5/8] Starting services...         ← 启动 7 个容器
[6/8] Running migrations...        ← 执行 21 个数据库迁移 + 种子数据
[7/8] Optimizing for production... ← 缓存配置/路由/视图/事件
[8/8] Health check...              ← 验证服务健康状态
```

### 6.2 验证容器状态

```bash
docker compose ps
```

预期输出（7 个容器全部 running）：

```
NAME                STATUS          PORTS
licenseos_app       Up (healthy)    9000/tcp
licenseos_queue     Up              -
licenseos_scheduler Up              -
licenseos_nginx     Up              0.0.0.0:80->80/tcp, 443->443/tcp
licenseos_db        Up (healthy)    127.0.0.1:3306->3306/tcp
licenseos_redis     Up (healthy)    127.0.0.1:6379->6379/tcp
licenseos_search    Up              127.0.0.1:7700->7700/tcp
```

### 6.3 访问验证

此时在浏览器打开 `http://你的服务器IP` 应该能看到 LicenseOS 首页。

---

## 七、配置域名与 SSL

### 7.1 DNS 配置

在你的域名注册商后台添加 DNS 记录：

| 类型  | 名称  | 值       | TTL |
| --- | --- | ------- | --- |
| A   | @   | 你的服务器IP | 600 |
| A   | www | 你的服务器IP | 600 |

等待 DNS 生效（通常 5-30 分钟），可用 `ping your-domain.com` 验证。

### 7.2 一键配置 SSL

```bash
# 确保域名已解析到服务器
bash docker/scripts/setup-ssl.sh your-domain.com admin@your-domain.com
```

**脚本自动完成：**

1. 安装 certbot
2. 停止 Nginx（释放 80 端口）
3. 通过 Let's Encrypt 申请证书
4. 复制证书到 Nginx SSL 目录
5. 自动修改 Nginx 配置启用 HTTPS
6. 启用 HTTP → HTTPS 自动跳转
7. 设置每日 3AM 自动续签证书
8. 重启 Nginx

### 7.3 手动 SSL 配置（如果脚本不适用）

```bash
# 安装 certbot
apt install -y certbot

# 停止 nginx
docker compose stop nginx

# 申请证书
certbot certonly --standalone -d your-domain.com -d www.your-domain.com

# 复制证书
mkdir -p docker/nginx/ssl
cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/
cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/

# 编辑 nginx 配置
nano docker/nginx/default.conf
# 取消 SSL 相关行的注释，修改 server_name

# 重启 nginx
docker compose start nginx
```

### 7.4 更新 APP_URL

```bash
# 确保 .env 中的 URL 已改为 HTTPS
nano .env
# APP_URL=https://your-domain.com

# 刷新配置缓存
docker compose exec app php artisan config:cache
```

---

## 八、配置支付 Webhook

### 8.1 Stripe Webhook

1. 登录 [Stripe Dashboard](https://dashboard.stripe.com/webhooks)
2. 添加 Endpoint：`https://your-domain.com/api/v2/webhook/stripe`
3. 选择监听事件：
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `charge.refunded`
4. 复制 Webhook Secret → 填入 `.env` 的 `STRIPE_WEBHOOK_SECRET`

```bash
nano .env
# STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxx
docker compose exec app php artisan config:cache
```

### 8.2 微信支付回调

```bash
nano .env
WECHAT_APP_ID=你的AppID
WECHAT_MCH_ID=你的商户号
WECHAT_API_KEY=你的API密钥
WECHAT_CERT_PATH=/var/www/storage/certs/wechat_cert.pem
```

将证书文件放到 `storage/certs/` 目录：

```bash
docker compose exec app mkdir -p storage/certs
# 上传证书文件到 storage/certs/wechat_cert.pem
```

在微信商户后台设置回调 URL：

```
https://your-domain.com/api/v2/webhook/wechat
```

### 8.3 支付宝回调

```bash
nano .env
ALIPAY_APP_ID=你的AppID
ALIPAY_PRIVATE_KEY=你的私钥
ALIPAY_PUBLIC_KEY=支付宝公钥
```

在支付宝开放平台设置回调 URL：

```
https://your-domain.com/api/v2/webhook/alipay
```

---

## 九、验证部署

### 9.1 健康检查

```bash
# 命令行检查
curl http://localhost/health

# 预期返回
# {"status":"healthy","checks":{"app":true,"database":true,"redis":true},...}
```

### 9.2 完整功能检查清单

| 检查项      | URL                                              | 预期结果                   |
| -------- | ------------------------------------------------ | ---------------------- |
| 首页       | `https://your-domain.com`                        | 正常加载                   |
| 管理后台     | `https://your-domain.com/admin`                  | Filament 登录页           |
| API 产品列表 | `https://your-domain.com/api/v3/products`        | JSON 产品列表              |
| 健康检查     | `https://your-domain.com/health`                 | `{"status":"healthy"}` |
| SSL 证书   | 浏览器地址栏                                           | 绿色锁头                   |
| 队列状态     | `docker compose exec queue supervisorctl status` | 4 个 RUNNING            |

### 9.3 登录管理后台

```
URL:      https://your-domain.com/admin
账号:     admin@licenseos.com
密码:     password  (首次部署的演示密码，生产环境请立即修改！)
```

> ⚠️ **重要**：生产环境请立即修改默认密码，或重新 seed 不带演示数据。

### 9.4 查看日志

```bash
# 查看应用日志
docker compose logs -f app

# 查看队列日志
docker compose logs -f queue

# 查看 Nginx 访问日志
docker compose logs -f nginx

# 查看数据库日志
docker compose logs -f db
```

---

## 十、日常运维

### 10.1 常用命令速查

```bash
# ── 容器管理 ──
docker compose ps                          # 查看所有容器状态
docker compose restart                     # 重启所有服务
docker compose restart app                 # 只重启应用
docker compose stop                        # 停止所有服务
docker compose down                        # 停止并移除容器
docker compose up -d                       # 后台启动所有服务

# ── 进入容器 ──
docker compose exec app bash               # 进入应用容器
docker compose exec db bash                # 进入数据库容器
docker compose exec redis redis-cli        # 进入 Redis CLI

# ── Artisan 命令 ──
docker compose exec app php artisan list   # 查看所有命令
docker compose exec app php artisan tinker # 进入 Tinker REPL
docker compose exec app php artisan db:seed --class=DemoSeeder --force  # 重新填充演示数据

# ── 数据库操作 ──
docker compose exec db mysql -ulicenseos -p licenseos   # 进入 MySQL
docker compose exec app php artisan migrate:status       # 迁移状态

# ── 缓存管理 ──
docker compose exec app php artisan config:cache    # 刷新配置缓存
docker compose exec app php artisan cache:clear     # 清除应用缓存
docker compose exec app php artisan route:cache     # 刷新路由缓存
docker compose exec app php artisan queue:restart   # 重启队列 Worker

# ── 日志 ──
docker compose logs --tail=100 app             # 最近 100 行应用日志
docker compose logs --since=1h app             # 最近 1 小时日志
docker compose exec app tail -f storage/logs/laravel.log  # 实时日志
```

### 10.2 定时任务状态

```bash
# 查看 Scheduler 日志
docker compose logs scheduler

# 手动触发某个定时任务
docker compose exec app php artisan schedule:run

# 手动执行生态循环
docker compose exec app php artisan ecosystem:cycle --full

# 手动执行生命体周期
docker compose exec app php artisan lifeform:cycle --full
```

### 10.3 数据库备份

```bash
# 导出数据库
docker compose exec db mysqldump -ulicenseos -p licenseos > backup_$(date +%Y%m%d).sql

# 导入数据库
cat backup_20260617.sql | docker compose exec -T db mysql -ulicenseos -p licenseos
```

**自动每日备份（推荐）：**

```bash
# 创建备份脚本
cat > /home/deploy/backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR=/home/deploy/backups
mkdir -p $BACKUP_DIR
DATE=$(date +%Y%m%d_%H%M%S)
docker compose exec -T db mysqldump -ulicenseos -pYOUR_DB_PASSWORD licenseos | gzip > $BACKUP_DIR/db_$DATE.sql.gz
# 保留最近 30 天的备份
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete
echo "Backup complete: db_$DATE.sql.gz"
EOF

chmod +x /home/deploy/backup.sh

# 添加 crontab（每天凌晨 2 点备份）
(crontab -l 2>/dev/null; echo "0 2 * * * /home/deploy/backup.sh >> /home/deploy/backup.log 2>&1") | crontab -
```

### 10.4 监控

```bash
# 查看资源使用
docker stats

# 查看磁盘使用
df -h

# 查看 PHP-FPM 状态（内部）
curl http://localhost/fpm-status
```

---

## 十一、版本更新部署

### 11.1 标准更新流程

```bash
cd /home/deploy/LicenseOS

# 1. 拉取最新代码
git pull origin main

# 2. 运行部署脚本（自动重建 + 迁移 + 优化）
bash docker/scripts/deploy.sh
```

### 11.2 零停机更新（推荐）

```bash
cd /home/deploy/LicenseOS

# 1. 拉取代码
git pull origin main

# 2. 构建新镜像（不影响当前运行）
docker compose build

# 3. 滚动重启（逐个替换容器）
docker compose up -d --no-deps app
docker compose up -d --no-deps queue
docker compose up -d --no-deps scheduler

# 4. 执行迁移
docker compose exec app php artisan migrate --force

# 5. 刷新缓存
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

# 6. 重启队列（使新代码生效）
docker compose exec app php artisan queue:restart
```

---

## 十二、常见问题排查

### Q1: 容器启动失败

```bash
# 查看详细错误
docker compose logs app

# 常见原因：
# - .env 文件配置错误
# - 数据库连接失败（DB_HOST 应为 db，不是 127.0.0.1）
# - APP_KEY 为空

# 重新生成 APP_KEY
docker compose run --rm app php artisan key:generate
```

### Q2: 502 Bad Gateway

```bash
# PHP-FPM 未就绪，等待几秒后重试
sleep 10 && curl http://localhost/health

# 如果持续 502，检查 PHP-FPM 状态
docker compose exec app php-fpm -t  # 测试配置
docker compose restart app           # 重启 PHP-FPM
```

### Q3: 数据库迁移失败

```bash
# 查看迁移状态
docker compose exec app php artisan migrate:status

# 回滚最后一次迁移
docker compose exec app php artisan migrate:rollback

# 完全重置（⚠️ 会删除所有数据）
docker compose exec app php artisan migrate:fresh --seed --force
```

### Q4: 队列任务不执行

```bash
# 检查 Supervisor 状态
docker compose exec queue supervisorctl status

# 重启队列
docker compose exec queue supervisorctl restart all

# 或重启容器
docker compose restart queue

# 检查失败任务
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

### Q5: SSL 证书过期

```bash
# 手动续签
certbot renew --force-renewal

# 复制新证书
cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/
cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/

# 重载 Nginx
docker compose exec nginx nginx -s reload
```

### Q6: 存储空间不足

```bash
# 查看磁盘使用
df -h

# 清理 Docker 无用镜像和容器
docker system prune -f

# 清理旧日志
docker compose exec app php artisan log:clear  # 如果有这个命令
# 或手动清理
docker compose exec app find storage/logs -name "*.log" -mtime +7 -delete
```

---

## 十三、性能优化建议

### 13.1 MySQL 优化（4GB 内存服务器）

编辑 `docker-compose.yml` 中 db 的 command 参数：

```yaml
command: >
  --innodb-buffer-pool-size=512M
  --innodb-log-file-size=128M
  --innodb-flush-log-at-trx-commit=2
  --max-connections=300
  --query-cache-type=0
  --character-set-server=utf8mb4
  --collation-server=utf8mb4_unicode_ci
```

### 13.2 PHP-FPM 优化（4GB 内存服务器）

编辑 `docker/php/php-fpm.conf`：

```ini
pm = dynamic
pm.max_children = 30       # 4GB 内存建议 30
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10
pm.max_requests = 1000     # 增加复用次数
```

### 13.3 Redis 优化

编辑 `docker-compose.yml` 中 redis 的 command：

```yaml
command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
```

### 13.4 Nginx 优化

在 `docker/nginx/default.conf` 的 server 块中添加：

```nginx
# Gzip 压缩
gzip on;
gzip_types text/plain text/css application/json application/javascript;
gzip_min_length 1000;

# 浏览器缓存
location ~* \.(jpg|jpeg|png|gif|ico|svg|css|js|woff2)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
}
```

### 13.5 使用 CDN（可选）

将静态资源（CSS/JS/图片）通过 CDN 分发：

- 国际：Cloudflare（免费）/ AWS CloudFront
- 国内：阿里云 CDN / 腾讯云 CDN

在 `.env` 中配置：

```ini
ASSET_URL=https://cdn.your-domain.com
```

---

## 附录：生产环境安全检查清单

- [ ] 修改默认管理员密码
- [ ] 修改数据库默认密码
- [ ] 配置 SSL (HTTPS)
- [ ] 启用防火墙 (UFW)
- [ ] 配置 fail2ban
- [ ] 设置自动数据库备份
- [ ] Stripe Webhook Secret 已配置
- [ ] APP_DEBUG 设为 false
- [ ] LOG_LEVEL 设为 warning
- [ ] 服务器 SSH 禁用密码登录（仅密钥）
- [ ] 定期检查 `docker compose logs` 无异常
- [ ] Meilisearch 密钥已更换默认值
