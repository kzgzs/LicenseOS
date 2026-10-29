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
  module.exports = { LicenseOS };
}
