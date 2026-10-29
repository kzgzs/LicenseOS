# LicenseOS — 项目架构与服务器配置文档

> **版本**: V7 (终局) | **最后更新**: 2026-06  
> **定位**: 自进化数字经济生命体平台 (Self-Evolving Digital Marketplace Organism)

---

## 一、项目定位与目标

LicenseOS 是一个面向 **开发者/数字产品创作者** 的全生命周期 SaaS 市场平台，核心能力覆盖：

| 能力层            | 描述                               |
| -------------- | -------------------------------- |
| **License 管理** | 许可证生成、激活、域名绑定、过期检查               |
| **数字市场**       | 产品展示、分类、搜索、评价、购买、退款              |
| **开发者生态**      | 入驻申请、API Key、收入分成、提现             |
| **全球企业级**      | 多区域/多币种/KYC/税务/白标/GDPR           |
| **AI 智能平台**    | AI 生成插件、智能定价、SEO 内容、A/B 测试、AI 客服 |
| **自治生态系统**     | 治理规则引擎、AI Agent 编排、进化循环、价值分配     |
| **数字生命体**      | 感知层→决策层→生长层→变异层，自进化经济模型          |

---

## 二、版本演进路线

```
V1  ─ 核心 License 管理 + 基础市场
V2  ─ 商业版：Stripe/微信/支付宝支付、Webhook、SDK
V3  ─ 市场扩展：分类/搜索/评价/优惠券/订阅/退款
V3.1 ─ 生产加固：平台事件、失败重试、佣金追踪
V3.3 ─ 增长引擎：广告系统、企业账户、数据分析
V3.4 ─ 商业扩展：开发者等级、API 配额、CDN、产品翻译
V4  ─ 全球企业：多区域/多币种/KYC/税务/白标/GDPR/多币种账本
V5  ─ AI 平台：AI 生成插件/智能定价/SEO 内容/A-B 测试/AI 客服
V6  ─ 自治生态：治理规则/AI Agent/进化引擎/价值分配/生态克隆
V7  ─ 数字生命体：感知层/决策引擎/生长系统/变异引擎/DNA 遗传
```

---

## 三、技术栈

| 层级          | 技术选型                                          |
| ----------- | --------------------------------------------- |
| **框架**      | Laravel 11 (PHP 8.2+)                         |
| **后台面板**    | Filament v3.2 (自动发现 Resources/Pages/Widgets)  |
| **数据库**     | MySQL 8.0 (utf8mb4, InnoDB)                   |
| **缓存/队列**   | Redis 7 (队列 + 缓存 + 会话)                        |
| **搜索引擎**    | Meilisearch v1.6 (可选，降级到 DB LIKE)             |
| **Web 服务器** | Nginx 1.25 (反向代理 + 静态缓存 + 限流)                 |
| **容器化**     | Docker Compose (多阶段构建)                        |
| **支付**      | Stripe (主) / WeChat Pay / Alipay (网关接口模式)     |
| **认证**      | Laravel Sanctum (API Token)                   |
| **AI**      | OpenAI GPT-4 / GPT-3.5-turbo (通过 Services 封装) |
| **SSL**     | Let's Encrypt (certbot 自动续签)                  |

---

## 四、系统架构图

```
                        ┌─────────────────────────────┐
                        │       Nginx (80/443)        │
                        │  限流 · SSL · 静态缓存 · 路由 │
                        └──────────┬──────────────────┘
                                   │
                   ┌───────────────┼───────────────┐
                   ▼               ▼               ▼
           ┌──────────────┐ ┌───────────┐ ┌──────────────┐
           │  PHP-FPM App │ │  Queue Wkr│ │  Scheduler   │
           │  (Port 9000) │ │ (Supervisord)│ │  (Cron 1min)│
           └──────┬───────┘ └─────┬─────┘ └──────┬───────┘
                  │               │               │
          ┌───────┴───────────────┴───────────────┘
          ▼               ▼               ▼
   ┌────────────┐  ┌────────────┐  ┌──────────────┐
   │  MySQL 8   │  │   Redis 7  │  │ Meilisearch  │
   │ (Port 3306)│  │ (Port 6379)│  │ (Port 7700)  │
   └────────────┘  └────────────┘  └──────────────┘
```

---

## 五、Docker 容器编排

### 5.1 服务清单

| 容器                    | 镜像                      | 端口        | 职责                           |
| --------------------- | ----------------------- | --------- | ---------------------------- |
| `licenseos_app`       | 自建 PHP 8.3-FPM          | 9000 (内部) | 主应用 (PHP-FPM)                |
| `licenseos_queue`     | 同 app (queue stage)     | -         | Supervisor 管理队列 Worker       |
| `licenseos_scheduler` | 同 app (scheduler stage) | -         | Cron 每分钟触发 Laravel Scheduler |
| `licenseos_nginx`     | nginx:1.25-alpine       | 80, 443   | 反向代理、SSL、限流、静态缓存             |
| `licenseos_db`        | mysql:8.0               | 3306 (本地) | 持久化存储                        |
| `licenseos_redis`     | redis:7-alpine          | 6379 (本地) | 缓存 + 队列 + 会话                 |
| `licenseos_search`    | meilisearch:v1.6        | 7700 (本地) | 全文搜索引擎                       |

### 5.2 数据卷

| Volume         | 用途                         |
| -------------- | -------------------------- |
| `db_data`      | MySQL 数据持久化                |
| `redis_data`   | Redis AOF 持久化              |
| `search_data`  | Meilisearch 索引数据           |
| `storage_data` | Laravel storage/app (上传文件) |
| `log_data`     | PHP 日志                     |

### 5.3 网络

- 单一 bridge 网络：`licenseos_net`
- 容器间通过服务名通信（`db`, `redis`, `meilisearch`, `app`）
- 仅 Nginx 暴露 80/443 到公网
- MySQL/Redis/Meilisearch 仅绑定 127.0.0.1

---

## 六、服务器配置详解

### 6.1 PHP 配置 (`docker/php/php.ini`)

```ini
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 64M
post_max_size = 64M
max_input_vars = 5000

; OPcache (生产环境优化)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0    ; 生产环境禁用时间戳检查

; 安全 Session
session.cookie_secure = On
session.cookie_httponly = On
session.cookie_samesite = "Lax"
```

### 6.2 PHP-FPM 配置 (`docker/php/php-fpm.conf`)

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10
pm.max_requests = 500
slowlog = /var/log/php/slow.log
request_slowlog_timeout = 10s
```

### 6.3 Nginx 配置 (`docker/nginx/default.conf`)

| 功能             | 配置                                                                         |
| -------------- | -------------------------------------------------------------------------- |
| **API 限流**     | `60r/m`，burst=20，nodelay                                                   |
| **Webhook 限流** | `10r/m`，burst=5，nodelay                                                    |
| **静态资源缓存**     | 30 天，`Cache-Control: public, immutable`                                    |
| **上传大小**       | `client_max_body_size 64M`                                                 |
| **安全头**        | X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy |
| **隐藏敏感路径**     | `.env`, `.git`, `storage/logs/` 全部 deny                                    |
| **健康检查**       | `/health` 端点，返回 200 OK                                                     |
| **SSL**        | TLSv1.2/1.3，HSTS，Let's Encrypt 自动续签                                        |

### 6.4 队列 Worker (`docker/supervisor/supervisord.conf`)

| Worker          | 队列            | 进程数 | 超时  | 内存   |
| --------------- | ------------- | --- | --- | ---- |
| `queue-default` | default       | 2   | 60s | 128M |
| `queue-high`    | high, default | 2   | 90s | 256M |

### 6.5 MySQL 配置

```
--innodb-buffer-pool-size=256M
--max-connections=200
--character-set-server=utf8mb4
--collation-server=utf8mb4_unicode_ci
```

### 6.6 Redis 配置

```
--appendonly yes           # AOF 持久化
--maxmemory 128mb          # 最大内存
--maxmemory-policy allkeys-lru  # LRU 淘汰策略
```

---

## 七、数据库架构

### 7.1 迁移文件 (21 个)

| 序号  | 迁移文件                                         | 版本   | 内容                              |
| --- | -------------------------------------------- | ---- | ------------------------------- |
| 1   | `000001_create_tenants_table`                | V1   | 开发者租户                           |
| 2   | `000002_create_users_table`                  | V1   | 用户表                             |
| 3   | `000003_create_products_table`               | V1   | 产品表                             |
| 4   | `000005_create_licenses_table`               | V1   | 许可证表                            |
| 5   | `000006_create_activations_table`            | V1   | 激活记录                            |
| 6   | `000011_create_orders_table`                 | V1   | 订单表                             |
| 7   | `000012_create_commissions_table`            | V1   | 佣金表                             |
| 8   | `000020_create_developer_applications_table` | V2   | 入驻申请                            |
| 9   | `000021_create_api_keys_table`               | V2   | API 密钥                          |
| 10  | `000022_create_payouts_table`                | V2   | 提现表                             |
| 11  | `000023_create_events_table`                 | V2   | 平台事件                            |
| 12  | `000024_create_update_packages_table`        | V2   | 版本包                             |
| 13  | `000025_add_v21_fields`                      | V2.1 | 支付网关扩展                          |
| 14  | `000030_add_v3_marketplace_fields`           | V3   | 市场扩展字段                          |
| 15  | `000031_add_v3_1_production_tables`          | V3.1 | 生产表（优惠券/订阅/退款/产品浏览）             |
| 16  | `000032_add_v3_3_growth_tables`              | V3.3 | 增长表（广告/企业账户/收入记录/开发者等级）         |
| 17  | `000033_add_v3_4_commercial_tables`          | V3.4 | 商业扩展（API配额/CDN/产品翻译/白标）         |
| 18  | `000034_add_v4_global_enterprise_tables`     | V4   | 全球企业（区域/税务/KYC/GDPR/多币种账本）      |
| 19  | `000035_add_v5_ai_platform_tables`           | V5   | AI平台（任务/产品/内容/定价/AB测试/客服）       |
| 20  | `000036_add_v6_autonomous_ecosystem_tables`  | V6   | 自治生态（治理/Agent/进化/价值分配/克隆）       |
| 21  | `000037_add_v7_lifeform_tables`              | V7   | 数字生命体（信号流/决策/DNA/变异/生命周期/有机体状态） |

### 7.2 核心数据模型 (61 个 Model)

```
├── 基础业务 (V1-V2)
│   ├── Tenant         # 开发者租户
│   ├── User           # 用户 (admin/developer/customer)
│   ├── Product        # 数字产品 (wp_plugin/theme/saas/api)
│   ├── License        # 许可证
│   ├── Activation     # 域名激活
│   ├── Order          # 订单
│   ├── Commission     # 佣金
│   ├── ApiKey         # API密钥
│   ├── Payout         # 提现
│   ├── PlatformEvent  # 平台事件
│   └── UpdatePackage  # 版本更新包
│
├── 市场扩展 (V3-V3.4)
│   ├── Category       # 分类
│   ├── Review         # 评价
│   ├── Subscription   # 订阅
│   ├── Refund         # 退款
│   ├── Coupon         # 优惠券
│   ├── ProductView    # 产品浏览
│   ├── Ad             # 广告
│   ├── EnterpriseAccount  # 企业账户
│   ├── EnterpriseSeat     # 企业席位
│   ├── RevenueRecord  # 收入记录
│   ├── PlatformTier   # 开发者/用户等级
│   ├── ApiAccessTier  # API 访问层级
│   ├── ApiUsageLog    # API 使用日志
│   ├── CdnNode        # CDN 节点
│   ├── ProductTranslation # 产品翻译
│   └── WhiteLabel     # 白标定制
│
├── 全球企业级 (V4)
│   ├── Region         # 区域
│   ├── TaxRule        # 税务规则
│   ├── ExchangeRate   # 汇率
│   ├── KycVerification # KYC 认证
│   ├── Invoice        # 发票
│   ├── LedgerEntry    # 多币种账本
│   ├── PrivacyConsent # GDPR 隐私同意
│   └── DataRequest    # GDPR 数据请求
│
├── AI 智能平台 (V5)
│   ├── AiTask         # AI 任务
│   ├── AiProduct      # AI 生成产品
│   ├── AiContent      # AI 生成内容
│   ├── AiPricingLog   # AI 定价日志
│   ├── AiAbTest       # AI A/B 测试
│   ├── AiRecommendation # AI 推荐
│   ├── AiSupportSession # AI 客服会话
│   ├── AiSupportMessage # AI 客服消息
│   ├── AiMarketInsight  # AI 市场洞察
│   └── AiAutomationRule # AI 自动化规则
│
├── 自治生态系统 (V6)
│   ├── GovernanceRule  # 治理规则
│   ├── AiAgent         # AI Agent
│   ├── AgentAction     # Agent 动作
│   ├── ValueDistribution # 价值分配
│   ├── EvolutionCycle  # 进化周期
│   ├── EcosystemClone  # 生态克隆
│   ├── EcosystemMetric # 生态指标
│   └── EcosystemEvent  # 生态事件
│
└── 数字生命体 (V7)
    ├── SignalStream    # 环境感知信号流
    ├── Decision        # 神经决策
    ├── DnaProfile      # DNA 遗传档案
    ├── Mutation        # 变异实验
    ├── LifeCycleEvent  # 生命周期事件
    └── OrganismState   # 有机体状态快照
```

---

## 八、API 路由架构

### 8.1 公开 API (无需认证)

| 前缀                        | 端点数 | 功能                           |
| ------------------------- | --- | ---------------------------- |
| `/api/v2/webhook/*`       | 4   | Stripe/微信/支付宝 Webhook + 模拟支付 |
| `/api/v2/checkout/create` | 1   | 结账会话创建                       |
| `/api/v3/categories`      | 1   | 分类列表                         |
| `/api/v3/products`        | 2   | 产品列表 + 精选产品                  |
| `/api/v3/products/{slug}` | 2   | 产品详情 + 评价                    |
| `/api/v3/search`          | 1   | 全文搜索                         |
| `/api/v3/coupons/*`       | 2   | 优惠券验证/可用列表                   |
| `/api/v3/ads/*`           | 3   | 广告展示/点击/展示追踪                 |
| `/api/v3/enterprise/*`    | 2   | 企业计划公开信息                     |
| `/api/v3/public/*`        | 4   | 公开统计/热门/新品/分类                |

### 8.2 认证 API (Sanctum Token)

| 前缀                      | 端点数 | 功能                               |
| ----------------------- | --- | -------------------------------- |
| `/api/v2/license/*`     | 2   | 许可证激活/检查                         |
| `/api/v2/update/check`  | 1   | 版本更新检查                           |
| `/api/v3/purchase`      | 2   | 购买/购买历史                          |
| `/api/v3/refunds`       | 1   | 退款申请                             |
| `/api/v3/reviews`       | 2   | 评价提交/我的评价                        |
| `/api/v3/subscriptions` | 3   | 订阅管理                             |
| `/api/v4/tax/*`         | 2   | 税费计算                             |
| `/api/v4/regions/*`     | 3   | 区域管理                             |
| `/api/v4/kyc/*`         | 2   | KYC 提交/状态                        |
| `/api/v4/gdpr/*`        | 2   | GDPR 导出/删除                       |
| `/api/v4/ledger/*`      | 2   | 多币种账本                            |
| `/api/v4/white-label/*` | 4   | 白标管理                             |
| `/api/v5/ai/*`          | 18  | AI 全功能 (生成/定价/SEO/AB测试/推荐/市场/客服) |
| `/api/v6/ecosystem/*`   | 16  | 生态全功能 (治理/Agent/进化/价值/事件)        |
| `/api/v7/organism/*`    | 17  | 生命体全功能 (感知/决策/生长/DNA/变异/完整周期)    |

### 8.3 API 中间件

| 中间件                              | 功能                |
| -------------------------------- | ----------------- |
| `api.key` (ValidateApiKey)       | API Key 验证 + 配额检查 |
| `auth:sanctum`                   | Sanctum Token 认证  |
| `role` (CheckRole)               | 角色权限检查            |
| `api.quota` (ApiQuotaMiddleware) | API 调用配额限制        |
| `resolve.tenant` (ResolveTenant) | 租户自动解析            |

---

## 九、服务层架构

### 9.1 核心业务服务 (14个)

| 服务                      | 职责                             |
| ----------------------- | ------------------------------ |
| `LicenseService`        | 许可证生成/验证/激活/过期检查               |
| `PaymentService`        | 支付编排 (路由到具体网关)                 |
| `StripeService`         | Stripe 支付集成                    |
| `PaymentGatewayService` | 支付网关工厂 (Stripe/Adyen/Razorpay) |
| `CommissionService`     | 佣金计算                           |
| `PayoutService`         | 提现处理                           |
| `RefundService`         | 退款处理                           |
| `CouponService`         | 优惠券验证/折扣计算                     |
| `AnalyticsService`      | 销售数据分析                         |
| `RecommendationService` | 产品推荐                           |
| `SeoService`            | SEO 优化 (Meta/Sitemap/Schema)   |
| `UpdateService`         | 版本检查/更新分发                      |
| `EventService`          | 平台事件记录                         |
| `ApiQuotaService`       | API 配额管理                       |

### 9.2 企业级服务 (7个)

| 服务                  | 职责                        |
| ------------------- | ------------------------- |
| `RegionService`     | 多区域解析/路由                  |
| `TaxService`        | 多税种计算 (VAT/GST/Sales Tax) |
| `LedgerService`     | 多币种复式记账                   |
| `KycService`        | KYC 身份认证                  |
| `GdprService`       | GDPR 数据导出/删除              |
| `WhiteLabelService` | 白标定制管理                    |
| `EnterpriseService` | 企业账户/席位管理                 |

### 9.3 AI 智能服务 (5个)

| 服务                        | 职责                              |
| ------------------------- | ------------------------------- |
| `AI\AIBrainService`       | AI 核心 (OpenAI 封装，Token/Cost 追踪) |
| `AI\PluginFactoryService` | AI 插件生成工厂                       |
| `AI\PricingAIService`     | 智能定价优化                          |
| `AI\GrowthAIService`      | 增长引擎 (SEO/AB测试/推荐)              |
| `AI\SupportAIService`     | AI 智能客服                         |
| `AI\MarketAIService`      | 市场情报分析                          |

### 9.4 V6 自治生态服务 (5个)

| 服务                            | 职责                          |
| ----------------------------- | --------------------------- |
| `Ecosystem\GovernanceEngine`  | 自治规则引擎 (条件评估→自动执行)          |
| `Ecosystem\AgentOrchestrator` | AI Agent 编排 (5种类型Agent自动调度) |
| `Ecosystem\ValueEngine`       | 动态价值分配 (开发者/平台/Agent/推荐)    |
| `Ecosystem\EvolutionEngine`   | 进化循环 (适应度→复制→淘汰)            |
| `Ecosystem\EcosystemHealth`   | 生态健康仪表盘                     |

### 9.5 V7 数字生命体服务 (4个)

| 服务                        | 职责                           |
| ------------------------- | ---------------------------- |
| `LifeForm\SenseLayer`     | 感知层 — 实时采集 5 类信号源            |
| `LifeForm\DecisionEngine` | 决策引擎 — 6 种决策类型，优先级+人工审批      |
| `LifeForm\GrowthOrgan`    | 生长系统 — 自动创建产品/DNA 复制/市场扩展    |
| `LifeForm\MutationEngine` | 变异引擎 — 14天A/B测试，自动判定成功/中性/失败 |

---

## 十、Filament 后台管理面板

**路径**: `/admin`  
**框架**: Filament v3.2  
**认证**: Laravel Auth (super_admin)  
**资源自动发现**: `app/Filament/Resources/`

### 10.1 资源清单 (34 个 Resource)

| 分组     | 资源                           | 说明                         |
| ------ | ---------------------------- | -------------------------- |
| **核心** | TenantResource               | 租户管理                       |
|        | ProductResource              | 产品管理 (CRUD)                |
|        | OrderResource                | 订单管理                       |
|        | LicenseResource              | 许可证管理 (CRUD)               |
|        | CommissionResource           | 佣金记录                       |
| **市场** | CategoryResource             | 分类管理 (CRUD)                |
|        | ReviewResource               | 评价审核                       |
|        | SubscriptionResource         | 订阅管理                       |
|        | RefundResource               | 退款管理                       |
| **商业** | AdResource                   | 广告管理 (CRUD)                |
|        | EnterpriseAccountResource    | 企业账户 (CRUD)                |
|        | DeveloperApplicationResource | 入驻审核                       |
|        | ApiKeyResource               | API Key 管理 (CRUD)          |
|        | PayoutResource               | 提现审核                       |
| **全球** | RegionResource               | 区域管理                       |
|        | TaxRuleResource              | 税务规则                       |
|        | KycVerificationResource      | KYC 审核                     |
|        | WhiteLabelResource           | 白标管理                       |
|        | LedgerEntryResource          | 多币种账本                      |
| **AI** | AiTaskResource               | AI 任务                      |
|        | AiProductResource            | AI 产品                      |
|        | AiContentResource            | AI 内容                      |
| **V6** | GovernanceRuleResource       | 治理规则                       |
|        | AiAgentResource              | AI Agent                   |
|        | EvolutionCycleResource       | 进化周期                       |
| **V7** | SignalStreamResource         | 信号流                        |
|        | DecisionResource             | 决策管理 (含 Approve/Reject 操作) |
|        | DnaProfileResource           | DNA 档案                     |
|        | MutationResource             | 变异实验                       |
|        | LifeCycleEventResource       | 生命周期事件                     |
| **运维** | FailedJobResource            | 失败任务                       |
|        | PlatformEventResource        | 平台事件                       |
|        | UpdatePackageResource        | 更新包管理 (CRUD)               |

---

## 十一、定时任务 (Scheduler)

| 命令                           | 频率         | 功能                  |
| ---------------------------- | ---------- | ------------------- |
| `platform:process-events`    | 每分钟        | 处理未处理的平台事件          |
| `license:expire-check`       | 每天         | 检查过期许可证             |
| `ads:expire`                 | 每天 00:05   | 过期广告下线              |
| `api:reset-quotas`           | 每月1号 00:00 | 重置 API 配额           |
| `enterprise:billing`         | 每月1号 01:00 | 企业月度计费              |
| `tiers:evaluate`             | 每周一 02:00  | 开发者等级评估             |
| `exchange:rates-update`      | 每6小时       | 汇率更新                |
| `white-label:billing`        | 每月1号 03:00 | 白标月度计费              |
| `gdpr:process-pending`       | 每天 04:00   | 处理 GDPR 请求          |
| `ai:optimize`                | 每天 05:00   | AI 优化 (定价+AB测试+SEO) |
| `ai:market-analysis --full`  | 每周一 06:00  | 完整市场分析              |
| `ecosystem:cycle --full`     | 每小时        | V6 完整生态循环           |
| `ecosystem:cycle --snapshot` | 每天 00:00   | V6 生态健康快照           |
| `lifeform:cycle --full`      | 每30分钟      | V7 完整生命周期           |
| `lifeform:cycle --snapshot`  | 每小时        | V7 有机体状态快照          |

---

## 十二、事件驱动架构

### 12.1 事件 → 监听器映射

| 事件                     | 监听器                                                                               | 触发场景                       |
| ---------------------- | --------------------------------------------------------------------------------- | -------------------------- |
| `OrderPaid`            | GenerateLicense, CalculateCommission, UpdateProductStats, RecordCommissionRevenue | 订单支付完成                     |
| `OrderRefunded`        | ReverseCommission                                                                 | 订单退款                       |
| `PayoutRequested`      | ProcessPayout                                                                     | 提现申请                       |
| `AdClicked`            | RecordAdRevenue                                                                   | 广告被点击                      |
| `EnterpriseSubscribed` | RecordEnterpriseRevenue                                                           | 企业订阅                       |
| `OrderProcessedV4`     | ProcessOrderV4                                                                    | V4订单处理 (税+发票+账本)           |
| `KycSubmitted`         | ProcessKycSubmission                                                              | KYC 提交 → 风险评估              |
| `GdprRequested`        | ProcessGdprRequest                                                                | GDPR 导出/删除                 |
| `WhiteLabelActivated`  | RecordWhiteLabelRevenue                                                           | 白标激活                       |
| `AiProductGenerated`   | ProcessAiProduct                                                                  | AI 产品生成 → 自动 SEO + 定价      |
| `EvolutionCompleted`   | ProcessEvolution                                                                  | V6 进化完成 → 自动 SEO + 描述 + AB |
| `SignalDetected`       | ProcessSignal                                                                     | V7 信号检测 → 记录 + 高优标记        |
| `DecisionExecuted`     | ProcessDecision                                                                   | V7 决策执行 → 记录生命周期           |

### 12.2 队列 Jobs

| Job                       | 队列      | 功能             |
| ------------------------- | ------- | -------------- |
| `ProcessOrderPaidJob`     | default | 异步处理订单支付后续流程   |
| `ProcessPayoutJob`        | high    | 异步处理提现到 Stripe |
| `ProcessPlatformEventJob` | default | 异步处理平台事件       |

---

## 十三、支付网关

采用 **策略模式** (Strategy Pattern)：

```
PaymentGatewayInterface
├── StripeGateway      # Stripe (全球信用卡)
├── AdyenGateway       # Adyen (欧洲企业级)
└── RazorpayGateway    # Razorpay (印度市场)
```

加上独立的：

- **WeChat Pay** — 微信支付 (中国区)
- **Alipay** — 支付宝 (中国区)

---

## 十四、部署流程

### 14.1 首次部署

```bash
# 1. 克隆代码
git clone <repo> && cd LicenseOS

# 2. 配置环境
cp .env.production .env
# 编辑 .env 填入真实密钥

# 3. 一键部署
bash docker/scripts/deploy.sh --first-time

# 4. 配置 SSL (可选)
bash docker/scripts/setup-ssl.sh your-domain.com admin@your-domain.com
```

### 14.2 后续更新

```bash
git pull
bash docker/scripts/deploy.sh
```

### 14.3 生产优化 (自动执行)

```bash
php artisan config:cache   # 配置缓存
php artisan route:cache    # 路由缓存
php artisan view:cache     # 视图缓存
php artisan event:cache    # 事件缓存
```

---

## 十五、V7 数字生命体核心流程

```
    ┌──────────────────────────────────────────────────────────────┐
    │                    完整生命周期 (每30分钟)                     │
    │                                                              │
    │  ┌─────────┐    ┌──────────┐    ┌─────────┐    ┌──────────┐ │
    │  │  感知    │───▶│  决策    │───▶│  生长   │───▶│  变异    │ │
    │  │ Sense   │    │ Think    │    │ Grow    │    │ Mutate   │ │
    │  └─────────┘    └──────────┘    └─────────┘    └──────────┘ │
    │       │              │               │              │       │
    │  5类信号源      6种决策类型     4种生长动作     14天AB测试   │
    │  · 用户行为     · 创建产品     · 生成新产品    · 价格变异   │
    │  · 收入变化     · 调整价格     · DNA复制       · 功能变异   │
    │  · 产品趋势     · 下架产品     · 进入新市场    · 受众变异   │
    │  · 市场缺口     · 进入市场     · 优化转化      · UI变异     │
    │  · 搜索趋势     · DNA复制                                    │
    │                 · 优化转化     ┌──────────────────────┐      │
    │                                │   DNA 遗传蓝图       │      │
    │                                │ · pricing_model      │      │
    │                                │ · feature_set        │      │
    │                                │ · target_audience    │      │
    │                                │ · growth_pattern     │      │
    │                                │ · seo_strategy       │      │
    │                                │ · ui_pattern         │      │
    │                                │                      │      │
    │                                │ fitness ≥ 70 → 优势  │      │
    │                                │ 成功变异 → 新DNA菌株  │      │
    │                                └──────────────────────┘      │
    │                                                              │
    │  ⚠️ 关键决策需人工审批 (human_approval_required)              │
    └──────────────────────────────────────────────────────────────┘
```

---

## 十六、环境配置变量

| 变量                      | 说明                | 默认值                     |
| ----------------------- | ----------------- | ----------------------- |
| `APP_NAME`              | 应用名称              | LicenseOS               |
| `APP_ENV`               | 环境                | production              |
| `APP_KEY`               | 加密密钥 (自动生成)       | -                       |
| `APP_URL`               | 应用 URL            | https://your-domain.com |
| `DB_HOST`               | 数据库主机             | db                      |
| `DB_DATABASE`           | 数据库名              | licenseos               |
| `DB_USERNAME`           | 数据库用户             | licenseos               |
| `DB_PASSWORD`           | 数据库密码             | -                       |
| `CACHE_DRIVER`          | 缓存驱动              | redis                   |
| `SESSION_DRIVER`        | 会话驱动              | redis                   |
| `QUEUE_CONNECTION`      | 队列驱动              | redis                   |
| `STRIPE_KEY`            | Stripe 公钥         | -                       |
| `STRIPE_SECRET`         | Stripe 私钥         | -                       |
| `STRIPE_WEBHOOK_SECRET` | Stripe Webhook 密钥 | -                       |
| `WECHAT_APP_ID`         | 微信 AppID          | -                       |
| `WECHAT_MCH_ID`         | 微信商户号             | -                       |
| `ALIPAY_APP_ID`         | 支付宝 AppID         | -                       |
| `PLATFORM_FEE_RATE`     | 平台佣金比例            | 10 (%)                  |
| `PAYOUT_MIN_AMOUNT`     | 最低提现金额            | 10                      |
| `PAYOUT_DELAY_DAYS`     | 提现延迟天数            | 7                       |
| `MEILISEARCH_HOST`      | Meilisearch 地址    | http://meilisearch:7700 |
| `MEILISEARCH_KEY`       | Meilisearch 主密钥   | -                       |
| `REDIS_HOST`            | Redis 主机          | redis                   |
| `MAIL_MAILER`           | 邮件驱动              | smtp                    |

---

## 十七、安全设计

| 层面     | 措施                                             |
| ------ | ---------------------------------------------- |
| **网络** | Nginx 限流 (API 60r/m, Webhook 10r/m)，敏感路径屏蔽     |
| **传输** | TLS 1.2/1.3，HSTS，HTTP→HTTPS 重定向                |
| **认证** | Sanctum Token (API)，Session Cookie (Web)       |
| **会话** | Secure + HttpOnly + SameSite=Lax + Strict Mode |
| **数据** | 数据库仅本地访问，Redis 仅本地访问                           |
| **合规** | GDPR 数据导出/删除，KYC 身份认证                          |
| **风控** | 人工审批关键决策 (V7)，AI Agent 自治边界                    |
| **审计** | 多币种账本 (不可变)，平台事件日志，生态事件日志                      |

---

## 十八、项目统计

| 指标              | 数量   |
| --------------- | ---- |
| **数据库迁移**       | 21 个 |
| **数据模型**        | 61 个 |
| **服务类**         | 44 个 |
| **API 控制器**     | 36 个 |
| **Filament 资源** | 34 个 |
| **事件**          | 13 个 |
| **监听器**         | 14 个 |
| **队列 Job**      | 3 个  |
| **定时任务**        | 15 个 |
| **Artisan 命令**  | 12 个 |
| **API 端点**      | ~80+ |
| **Docker 容器**   | 7 个  |
