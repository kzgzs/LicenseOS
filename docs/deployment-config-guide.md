# LicenseOS 部署后配置手册

部署完成后，需要在后台配置以下内容才能使网站正常运行。

---

## 一、系统设置 (System Settings)

### General 标签页

| 字段               | 值                            |
| ---------------- | ---------------------------- |
| Site Name        | `LicenseOS`                  |
| Default Language | `中文 (Chinese)` 或 `English`   |
| Timezone         | `Asia/Shanghai (CST, UTC+8)` |

### SEO 标签页

| 字段               | 值（填翻译键，自动多语言）     |
| ---------------- | ----------------- |
| SEO Title        | `seo.title`       |
| Meta Description | `seo.description` |
| Meta Keywords    | `seo.keywords`    |

### Footer 标签页

| 字段             | 值（填翻译键，自动多语言）      |
| -------------- | ------------------ |
| Copyright Text | `footer.copyright` |

---

## 二、导航菜单 (Navigation Items)

### 顶部导航 (Header)

| 排序  | Label（填翻译键）         | URL            | 目标      |
| --- | ------------------- | -------------- | ------- |
| 1   | `nav.home`          | `/`            | `_self` |
| 2   | `nav.marketplace`   | `/marketplace` | `_self` |
| 3   | `nav.faq`           | `/faq`         | `_self` |
| 4   | `nav.contact`       | `/contact`     | `_self` |
| 5   | `nav.documentation` | `/docs`        | `_self` |

### 底部导航 (Footer)

> **可选**：服务条款和隐私政策链接仅在注册页出现，如需在页脚也显示可添加以下项。

| 排序  | Label（填翻译键）   | URL             | 目标      |
| --- | ------------- | --------------- | ------- |
| 1   | `nav.terms`   | `/page/terms`   | `_self` |
| 2   | `nav.privacy` | `/page/privacy` | `_self` |
| 3   | `nav.contact` | `/contact`      | `_self` |

---

## 三、CMS 页面 (CMS Pages)

访问 `/page/{slug}` 会自动根据当前语言查找 `{slug}-{语言}` 页面：

- 中文用户访问 `/page/terms` → 加载 `terms-zh`
- 英文用户访问 `/page/terms` → 加载 `terms-en`
- 找不到语言版本 → 回退到 `terms`

### 需要创建的页面

| Slug         | 标题               | 语言      |
| ------------ | ---------------- | ------- |
| `terms-zh`   | 服务条款             | 中文      |
| `terms-en`   | Terms of Service | English |
| `privacy-zh` | 隐私政策             | 中文      |
| `privacy-en` | Privacy Policy   | English |

**HTML 内容见下方独立文件：**

- `cms-terms-zh.html` — 服务条款（中文）
- `cms-terms-en.html` — Terms of Service (English)
- `cms-privacy-zh.html` — 隐私政策（中文）
- `cms-privacy-en.html` — Privacy Policy (English)

### 自定义 CMS 页面多语言方案

如需发布其他自定义页面（如“关于我们”、“帮助中心”），只需遵循以下 slug 命名规则：

| 步骤  | 操作           | 示例                               |
| --- | ------------ | -------------------------------- |
| 1   | 创建中文版页面      | slug: `about-zh`，标题: 关于我们        |
| 2   | 创建英文版页面      | slug: `about-en`，标题: About Us    |
| 3   | 链接统一用基础 slug | `<a href="/page/about">关于我们</a>` |

**自动路由规则：**

- 中文用户访问 `/page/about` → 系统自动查找 `about-zh` → 找到则显示中文版
- 英文用户访问 `/page/about` → 系统自动查找 `about-en` → 找到则显示英文版
- 找不到对应语言版本 → 回退显示 `about`（如存在）
- 都没有 → 404

**链接写法：** 始终使用基础 slug（不带语言后缀），如 `/page/about`。系统会自动根据用户当前语言匹配正确版本。

---

## 四、多语言翻译键参考

所有翻译存储在 `lang/zh_CN.json` 和 `lang/en.json`。

### 导航翻译键

| 键名                  | 中文   | English          |
| ------------------- | ---- | ---------------- |
| `nav.home`          | 首页   | Home             |
| `nav.marketplace`   | 市场   | Marketplace      |
| `nav.faq`           | 常见问题 | FAQ              |
| `nav.contact`       | 联系我们 | Contact          |
| `nav.documentation` | 开发文档 | Documentation    |
| `nav.terms`         | 服务条款 | Terms of Service |
| `nav.privacy`       | 隐私政策 | Privacy Policy   |

### SEO 翻译键

| 键名                | 中文                           | English                                  |
| ----------------- | ---------------------------- | ---------------------------------------- |
| `seo.title`       | LicenseOS - WordPress 插件授权市场 | LicenseOS - WordPress Plugin Marketplace |
| `seo.description` | 购买和销售 WordPress 插件...        | Buy and sell WordPress plugins...        |
| `seo.keywords`    | WordPress插件,插件授权...          | WordPress plugins,plugin licensing...    |

### 页脚翻译键

| 键名                 | 中文                          | English                                    |
| ------------------ | --------------------------- | ------------------------------------------ |
| `footer.copyright` | © {year} {site_name}. 版权所有。 | © {year} {site_name}. All rights reserved. |

---

## 五、部署后检查清单

- [ ] 系统设置 → General → 填写 Site Name 和 Default Language
- [ ] 系统设置 → SEO → 填写翻译键 (`seo.title` 等)
- [ ] 系统设置 → Footer → 填写 `footer.copyright`
- [ ] Navigation Items → 创建顶部导航（5项）
- [ ] Navigation Items → 创建底部导航（3项）
- [ ] CMS Pages → 创建 terms-zh、terms-en
- [ ] CMS Pages → 创建 privacy-zh、privacy-en
- [ ] 测试语言切换功能
- [ ] 测试所有页面中英文显示正确
