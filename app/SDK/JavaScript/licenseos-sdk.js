/**
 * LicenseOS JavaScript SDK v1.0
 *
 * 适用于：Web App、Chrome Extension、Electron Desktop App
 *
 * 使用方法：
 *
 *   // ES Module
 *   import { LicenseOS } from './licenseos-sdk.js';
 *
 *   // 或 CommonJS
 *   // const { LicenseOS } = require('./licenseos-sdk.js');
 *
 *   LicenseOS.init('https://your-api.com/api/v2', 'your-api-key');
 *
 *   // 激活许可证
 *   const result = await LicenseOS.activate('LICENSE-KEY', 'yourdomain.com', 'your-product');
 *
 *   // 验证许可证（支持试用模式 type='trial'）
 *   const check = await LicenseOS.check('LICENSE-KEY');
 *   if (check.status === 'valid' && LicenseOS.isTrialValid(check)) {
 *     // 允许使用
 *   }
 *
 *   // 检查更新
 *   const update = await LicenseOS.checkUpdate('your-product', '1.0.0');
 *
 * Public methods:
 *   LicenseOS.init(apiUrl, apiKey)              — 初始化 SDK
 *   LicenseOS.activate(key, domain, product)    — 激活许可证
 *   LicenseOS.check(key)                        — 验证许可证状态
 *   LicenseOS.isTrialValid(checkResult)         — 判断试用许可证是否有效
 *   LicenseOS.checkUpdate(slug, version, key)   — 检查产品更新
 *   LicenseOS.reportIntegrity(domain, key)      — 上报防篡改完整性校验（每日限频）
 */

// LICENSEOS-CONFIG-BEGIN（一键注入时平台填实；干净包为空对象，不启用）
const LICENSEOS_CONFIG = {
  api_url: '',
  api_key: '',
  slug: '',
  product_url: '',
  warning_days: 7,
  injected_at: '',
  manifest: {},
};
// LICENSEOS-CONFIG-END

class LicenseOS {
  /** @private */ static _api = '';
  /** @private */ static _apiKey = '';
  /** @private */ static _integrityReportedToday = false;

  /**
   * 初始化 SDK，设置 API 地址和密钥。
   *
   * @param {string} apiUrl - LicenseOS API 基础地址（如 https://your-domain.com/api/v2）
   * @param {string} [apiKey=''] - API 密钥（可选，用于标识开发者身份）
   */
  static init(apiUrl, apiKey = '') {
    LicenseOS._api = apiUrl.replace(/\/$/, '');
    LicenseOS._apiKey = apiKey;
  }

  /**
   * 激活许可证。
   *
   * @param {string} key - 许可证密钥
   * @param {string} domain - 绑定域名（如 example.com）
   * @param {string} [product=''] - 产品标识（可选，用于绑定特定产品）
   * @returns {Promise<{status: string, message?: string, type?: string, [key: string]: any}>}
   */
  static async activate(key, domain, product = '') {
    const data = { license_key: key, domain };
    if (product) data.product = product;

    return LicenseOS._request('/license/activate', data);
  }

  /**
   * 验证许可证状态（含试用模式支持）。
   *
   * 返回的 status 为 'valid' 时许可证有效。
   * 当 type='trial' 时，需配合 isTrialValid() 判断试用是否仍在有效期内。
   *
   * @param {string} key - 许可证密钥
   * @returns {Promise<{status: string, type?: string, message?: string, [key: string]: any}>}
   */
  static async check(key) {
    return LicenseOS._request('/license/check', { license_key: key });
  }

  /**
   * 判断试用许可证是否仍在有效期内。
   *
   * 仅当 checkResult.type === 'trial' 时有意义。
   * 非试用类型许可证直接返回 true。
   *
   * @param {{status: string, type?: string, trial_ends_at?: string|null}} checkResult - check() 返回的结果
   * @returns {boolean} 试用是否有效
   */
  static isTrialValid(checkResult) {
    if (!checkResult || checkResult.status !== 'valid') return false;

    // 非试用类型直接视为有效
    if (checkResult.type !== 'trial') return true;

    // 试用类型需检查过期时间
    if (!checkResult.trial_ends_at) return false;

    return new Date(checkResult.trial_ends_at).getTime() > Date.now();
  }

  /**
   * 检查产品更新（GET 请求，可被 CDN 缓存）。
   *
   * @param {string} productSlug - 产品标识
   * @param {string} [currentVersion=''] - 当前版本号（可选）
   * @param {string} [licenseKey=''] - 许可证密钥（可选，用于鉴权获取更新）
   * @returns {Promise<{up_to_date: boolean, latest_version?: string, download_url?: string, changelog?: string, [key: string]: any}|null>}
   */
  static async checkUpdate(productSlug, currentVersion = '', licenseKey = '') {
    const params = new URLSearchParams({ product: productSlug });
    if (currentVersion) params.set('version', currentVersion);
    if (licenseKey) params.set('license_key', licenseKey);

    return LicenseOS._requestGet('/update/check', params);
  }

  /**
   * 上报防篡改完整性校验（非阻塞，每日仅上报一次）。
   *
   * 建议在软件启动时调用，SDK 自动限频确保每个客户端每天最多上报一次。
   * 在 JS 环境中 code_hash 使用固定标识 'js-sdk-v1'（浏览器无法读取自身文件哈希）。
   *
   * @param {string} domain - 客户端域名
   * @param {string} [licenseKey=''] - 许可证密钥（可选）
   * @returns {Promise<void>}
   */
  static async reportIntegrity(domain, licenseKey = '') {
    if (LicenseOS._integrityReportedToday) return;
    LicenseOS._integrityReportedToday = true;

    const apiKeyHash = await LicenseOS._sha256(LicenseOS._apiKey);

    try {
      await LicenseOS._request('/integrity/report', {
        domain,
        code_hash: 'js-sdk-v1',
        api_key_hash: apiKeyHash,
        license_key: licenseKey,
        api_key: LicenseOS._apiKey,
      });
    } catch {
      // 防篡改上报失败不影响主流程
    }
  }

  // ========== Internal Methods ==========

  /**
   * 发送 POST 请求到 LicenseOS API。
   *
   * @private
   * @param {string} path - API 路径（如 /license/check）
   * @param {Record<string, any>} data - 请求体数据
   * @returns {Promise<{status: string, [key: string]: any}>}
   */
  static async _request(path, data) {
    try {
      const headers = { 'Content-Type': 'application/json' };
      if (LicenseOS._apiKey) headers['X-API-KEY'] = LicenseOS._apiKey;

      const response = await fetch(LicenseOS._api + path, {
        method: 'POST',
        headers,
        body: JSON.stringify(data),
      });
      return await response.json();
    } catch (err) {
      return { status: 'error', message: err.message || 'Network error' };
    }
  }

  /**
   * 发送 GET 请求到 LicenseOS API（可被 CDN 缓存）。
   *
   * @private
   * @param {string} path - API 路径
   * @param {URLSearchParams} [params] - 查询参数
   * @returns {Promise<any>}
   */
  static async _requestGet(path, params) {
    try {
      let url = LicenseOS._api + path;
      if (params && params.toString()) url += '?' + params.toString();

      const headers = {};
      if (LicenseOS._apiKey) headers['X-API-KEY'] = LicenseOS._apiKey;

      const response = await fetch(url, { method: 'GET', headers });
      return await response.json();
    } catch (err) {
      return { status: 'error', message: err.message || 'Network error' };
    }
  }

  /**
   * 计算字符串的 SHA-256 哈希值。
   *
   * @private
   * @param {string} message - 待哈希的字符串
   * @returns {Promise<string>} 十六进制哈希值
   */
  static async _sha256(message) {
    if (typeof crypto !== 'undefined' && crypto.subtle) {
      const msgBuffer = new TextEncoder().encode(message);
      const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
      return Array.from(new Uint8Array(hashBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    }
    // Fallback for Node.js
    if (typeof require !== 'undefined') {
      const crypto = require('crypto');
      return crypto.createHash('sha256').update(message).digest('hex');
    }
    return '';
  }
}

// Export for both ES Module and CommonJS
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { LicenseOS, LICENSEOS_CONFIG, licenseosMiddleware };
}

// ========== Node.js Web App 自动接入（注入后生效） ==========
// 拦截 /licenseos 路由渲染授权阶段界面（激活/信息/快到期/过期+续费直达产品页），
// 并每日上报自哈希+清单哈希防篡改；不新建任何专用目录。
function licenseosMiddleware(req, res, next) {
  const cfg = LICENSEOS_CONFIG;
  if (!cfg.api_url) return next ? next() : undefined;
  const url = (req.url || '/').split('?')[0].replace(/\/$/, '');
  if (url !== '/licenseos') return next ? next() : undefined;
  const fs = require('fs');
  const path = require('path');
  const statePath = path.join(__dirname, '.licenseos_state.json');
  const readState = () => { try { return JSON.parse(fs.readFileSync(statePath, 'utf8')); } catch { return {}; } };
  const api = async (p, data) => {
    try {
      const r = await fetch(cfg.api_url.replace(/\/$/, '') + p, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-KEY': cfg.api_key },
        body: JSON.stringify(data),
      });
      return await r.json();
    } catch (e) { return { status: 'error', message: e.message }; }
  };
  (async () => {
    let state = readState();
    let msg = '';
    if (req.method === 'POST') {
      let body = '';
      for await (const chunk of req) body += chunk;
      const form = Object.fromEntries(new URLSearchParams(body));
      if (form.licenseos_action === 'activate' && form.license_key) {
        const domain = (req.headers.host || 'localhost').split(':')[0];
        const r = await api('/license/activate', { license_key: form.license_key.trim(), domain, product: cfg.slug });
        if (['valid', 'active'].includes(r.status)) {
          const c = await api('/license/check', { license_key: form.license_key.trim() });
          state = { key: form.license_key.trim(), domain, activated_at: new Date().toISOString(), expires: c.expire_at || '' };
          fs.writeFileSync(statePath, JSON.stringify(state));
          msg = '激活成功';
        } else msg = '激活失败：' + (r.message || r.error || '无效密钥');
      } else if (form.licenseos_action === 'deactivate') {
        await api('/license/deactivate', { license_key: state.key || '', domain: state.domain || '' });
        state = {};
        fs.writeFileSync(statePath, '{}');
        msg = '已停用';
      }
    }
    const check = state.key ? await api('/license/check', { license_key: state.key }) : { status: 'inactive' };
    const daysLeft = state.expires ? Math.floor((new Date(state.expires) - Date.now()) / 86400000) : null;
    const renew = cfg.product_url || cfg.api_url.replace(/\/api\/v2$/, '') + '/products/' + cfg.slug;
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>授权管理</title><style>body{font-family:system-ui,sans-serif;background:#f5f6fa;padding:24px}.card{max-width:640px;margin:0 auto;background:#fff;border-radius:12px;padding:24px}.bar{padding:10px 14px;border-radius:8px;margin:12px 0;font-size:14px}.ok{background:#e8f5e9;color:#2e7d32}.warn{background:#fff3e0;color:#e65100}.bad{background:#ffebee;color:#c62828}.btn{display:inline-block;padding:8px 18px;border-radius:8px;background:#4f46e5;color:#fff;text-decoration:none;border:0;cursor:pointer}.ghost{background:#eee;color:#333}input[type=text]{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:8px}</style></head><body><div class="card"><h1>授权管理 · ' + esc(cfg.slug) + '</h1>';
    if (msg) html += '<div class="bar ok">' + esc(msg) + '</div>';
    if (['tampered', 'suspended'].includes(check.status)) html += '<div class="bar bad">检测到代码篡改或域名被暂停，授权已冻结。</div>';
    else if (!state.key || ['invalid', 'inactive', 'error'].includes(check.status)) html += '<div class="bar warn">尚未激活或授权无效：请输入授权证书激活。</div><form method="post"><input type="hidden" name="licenseos_action" value="activate"><p><input type="text" name="license_key" placeholder="例如 SP03-XXXX-XXXX-XXXX" required></p><p><button class="btn">激活授权</button> <a class="btn ghost" href="' + esc(renew) + '" target="_blank">去购买/续费</a></p></form>';
    else if (check.status === 'expired' || (daysLeft !== null && daysLeft < 0)) html += '<div class="bar bad">授权已过期，新增写入已冻结。</div><p><a class="btn" href="' + esc(renew) + '" target="_blank">去续费/购买</a></p>';
    else {
      html += '<div class="bar ok">授权有效 · 域名 ' + esc(state.domain) + ' · 激活于 ' + esc((state.activated_at || '').slice(0, 10)) + (state.expires ? ' · ' + esc(state.expires.slice(0, 10)) + ' 到期' : ' · 永久授权') + '</div>';
      if (daysLeft !== null && daysLeft <= (cfg.warning_days || 7)) html += '<div class="bar warn">授权即将到期：仅剩 ' + daysLeft + ' 天，请及时续费更换证书。</div><p><a class="btn" href="' + esc(renew) + '" target="_blank">立即续费</a></p>';
      html += '<form method="post" onsubmit="return confirm(\'确定停用许可证？\')"><input type="hidden" name="licenseos_action" value="deactivate"><button class="btn ghost">停用</button></form>';
    }
    html += '</div></body></html>';
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(html);
  })();
}

if (typeof process !== 'undefined' && process.versions && process.versions.node && LICENSEOS_CONFIG.api_url) {
  const http = require('http');
  const origCreate = http.createServer;
  http.createServer = function (opts, listener) {
    const wrapped = (req, res) => licenseosMiddleware(req, res, () => (listener || opts)(req, res));
    return origCreate.call(http, typeof opts === 'function' ? wrapped : opts, typeof opts === 'function' ? undefined : wrapped);
  };
  // 防篡改：每日上报自哈希 + 项目清单哈希
  const fs = require('fs');
  const crypto = require('crypto');
  setInterval(() => {
    let codeHash = crypto.createHash('sha256').update(fs.readFileSync(__filename)).digest('hex');
    for (const [rel, h] of Object.entries(LICENSEOS_CONFIG.manifest || {})) {
      try {
        if (crypto.createHash('sha256').update(fs.readFileSync(require('path').join(__dirname, rel))).digest('hex') !== h) { codeHash = 'manifest-mismatch'; break; }
      } catch { codeHash = 'manifest-mismatch'; break; }
    }
    fetch(LICENSEOS_CONFIG.api_url.replace(/\/$/, '') + '/integrity/report', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-API-KEY': LICENSEOS_CONFIG.api_key },
      body: JSON.stringify({ domain: require('os').hostname(), code_hash: codeHash, api_key_hash: crypto.createHash('sha256').update(LICENSEOS_CONFIG.api_key).digest('hex'), product_slug: LICENSEOS_CONFIG.slug }),
    }).catch(() => {});
  }, 86400000).unref();
}
