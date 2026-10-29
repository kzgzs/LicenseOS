# LicenseOS

> 面向开发者与数字产品创作者的全生命周期 SaaS 市场平台（自进化数字经济生命体）
>
> Laravel 11 + Filament v3 · Docker 生产编排 · Stripe/微信/支付宝支付

---

## 项目简介

LicenseOS 覆盖从许可证发放到数字产品交易、开发者生态、全球企业级合规、AI 智能运营直至自治生态与数字生命体的完整链路，共 7 个能力层：

| 能力层 | 说明 |
|--------|------|
| **License 管理** | 许可证生成、激活、域名绑定、过期检查 |
| **数字市场** | 产品展示、分类、搜索、评价、购买、退款 |
| **开发者生态** | 入驻申请、API Key、收入分成、提现 |
| **全球企业级** | 多区域 / 多币种 / KYC / 税务 / 白标 / GDPR |
| **AI 智能平台** | AI 生成插件、智能定价、SEO 内容、A/B 测试、AI 客服 |
| **自治生态系统** | 治理规则引擎、AI Agent 编排、进化循环、价值分配 |
| **数字生命体** | 感知层 → 决策层 → 生长层 → 变异层，自进化经济模型 |

---

## 技术栈

| 层级 | 选型 |
|------|------|
| 框架 | Laravel 11 (PHP 8.2+) |
| 后台面板 | Filament v3.2 |
| 数据库 | MySQL 8.0 (utf8mb4, InnoDB) |
| 缓存 / 队列 / 会话 | Redis 7 |
| 搜索引擎 | Meilisearch v1.6（可选，降级到 DB LIKE） |
| Web 服务器 | Nginx 1.25 |
| 容器化 | Docker Compose（多阶段构建） |
| 支付 | Stripe / WeChat Pay / Alipay |
| 认证 | Laravel Sanctum |
| AI | OpenAI GPT-4 / GPT-3.5-turbo |
| SSL | Let's Encrypt（certbot 自动续签） |

---

## 项目结构

```
LicenseOS/
├── app/                  # 业务代码（Models / Http / Services / Filament / Console）
├── database/migrations/  # 54 个数据库迁移
├── routes/               # API 与 Web 路由
├── config/               # 框架与业务配置
├── docker/               # PHP / Nginx / Supervisor 配置及部署脚本
├── resources/views/      # Blade 视图
├── lang/                 # 多语言（en / zh_CN）
├── docs/                 # 补充文档
├── public/               # 入口与静态资源
├── Dockerfile            # 多阶段构建
├── docker-compose.yml    # 生产编排（7 容器）
├── .env.example          # 环境变量模板
└── ARCHITECTURE.md       # 完整架构文档
```

---

## 快速开始

### 前置要求

- PHP 8.2+ / Composer（本地开发）
- 或 Docker + Docker Compose（推荐，生产一致）

### 方式一：Docker 一键部署（推荐）

```bash
# 1. 克隆
git clone git@github.com:kzgzs/LicenseOS.git
cd LicenseOS

# 2. 配置环境（填入真实密钥）
cp .env.example .env
# 编辑 .env：APP_URL、DB_PASSWORD、STRIPE_*、微信/支付宝密钥等

# 3. 首次部署
bash docker/scripts/deploy.sh --first-time

# 4. （可选）配置 SSL
bash docker/scripts/setup-ssl.sh your-domain.com admin@your-domain.com
```

后续更新：

```bash
git pull
bash docker/scripts/deploy.sh
```

### 方式二：本地开发

```bash
composer install
cp .env.example .env
php artisan key:generate
# 配置数据库 / Redis 连接
php artisan migrate --seed
php artisan serve
```

后台管理面板：`/admin`（super_admin 账户）

---

## 生产环境

Docker Compose 编排 7 个容器：

| 容器 | 镜像 | 端口 | 职责 |
|------|------|------|------|
| `licenseos_app` | 自建 PHP-FPM | 9000（内部） | 主应用 |
| `licenseos_queue` | 同 app | - | 队列 Worker（Supervisor） |
| `licenseos_scheduler` | 同 app | - | Cron 调度 |
| `licenseos_nginx` | nginx:1.25-alpine | 80, 443 | 反向代理 / SSL / 限流 |
| `licenseos_db` | mysql:8.0 | 3306（本地） | 持久化存储 |
| `licenseos_redis` | redis:7-alpine | 6379（本地） | 缓存 + 队列 + 会话 |
| `licenseos_search` | meilisearch:v1.6 | 7700（本地） | 全文搜索 |

生产优化自动执行：`config:cache` / `route:cache` / `view:cache` / `event:cache`。

> ⚠️ `docker-compose.yml` 中数据库与 Meilisearch 含弱默认密码（`ChangeMeInProduction` / `masterKeyChangeMe`），生产环境务必在 `.env` 中覆盖。

---

## 环境变量

完整变量清单见 `.env.example`。关键项：

| 变量 | 说明 |
|------|------|
| `APP_URL` | 应用域名（含 https://） |
| `DB_PASSWORD` | 数据库密码（必填） |
| `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe 密钥 |
| `WECHAT_APP_ID` / `WECHAT_MCH_ID` / `WECHAT_API_KEY` | 微信支付 |
| `ALIPAY_APP_ID` / `ALIPAY_PRIVATE_KEY` / `ALIPAY_PUBLIC_KEY` | 支付宝 |
| `PLATFORM_FEE_RATE` | 平台佣金比例（默认 10%） |
| `MEILISEARCH_HOST` / `MEILISEARCH_KEY` | 搜索引擎（留空则降级 DB 搜索） |

---

## 文档

| 文档 | 内容 |
|------|------|
| [ARCHITECTURE.md](ARCHITECTURE.md) | 完整架构、数据库 schema、API 路由、服务层、事件驱动、V7 生命体流程 |
| [DEPLOYMENT.md](DEPLOYMENT.md) | 部署指南 |
| [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) | 部署检查清单 |
| [BT_PANEL_DEPLOY.md](BT_PANEL_DEPLOY.md) | 宝塔面板部署方案 |
| [docs/](docs/) | 补充文档 |

---

## 许可证

本项目为**专有软件（Proprietary）**，未经授权不得复制、分发或商用。详见 `composer.json` 中的 `license` 声明。