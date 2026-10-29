/**
 * LicenseOS Figma Plugin SDK v1.0
 *
 * 适用于：Figma Plugin
 *
 * 使用方法：
 *
 *   import { LicenseOS } from './licenseos-sdk';
 *
 *   LicenseOS.init('https://your-api.com/api/v2', 'your-api-key');
 *
 *   // 激活许可证
 *   const result = await LicenseOS.activate('LICENSE-KEY');
 *
 *   // 验证许可证（支持试用模式 type='trial'）
 *   const check = await LicenseOS.check('LICENSE-KEY');
 *   if (check.status === 'valid' && LicenseOS.isTrialValid(check)) {
 *     // 允许使用插件功能
 *   }
 *
 *   // 检查更新
 *   const update = await LicenseOS.checkUpdate('your-product', '1.0.0');
 *
 *   // 保存密钥到 Figma clientStorage
 *   await LicenseOS.saveKey('LICENSE-KEY');
 *   const savedKey = await LicenseOS.loadKey();
 *
 * Public methods:
 *   LicenseOS.init(apiUrl, apiKey)              — 初始化 SDK
 *   LicenseOS.activate(key)                     — 激活许可证
 *   LicenseOS.check(key)                        — 验证许可证状态
 *   LicenseOS.isTrialValid(checkResult)         — 判断试用许可证是否有效
 *   LicenseOS.checkUpdate(slug, version, key)   — 检查产品更新
 *   LicenseOS.reportIntegrity(key)              — 上报防篡改完整性校验（每日限频）
 *   LicenseOS.saveKey(key)                      — 保存密钥到 Figma clientStorage
 *   LicenseOS.loadKey()                         — 从 Figma clientStorage 读取密钥
 */

interface InitOptions {
  apiUrl: string;
  apiKey: string;
}

interface ApiResult {
  status: string;
  message?: string;
  type?: string;
  trial_ends_at?: string | null;
  [key: string]: any;
}

interface UpdateResult {
  up_to_date?: boolean;
  latest_version?: string;
  download_url?: string;
  changelog?: string;
  [key: string]: any;
}

class LicenseOS {
  private static _api: string = '';
  private static _apiKey: string = '';
  private static _integrityReportedToday: boolean = false;

  /**
   * 初始化 SDK，设置 API 地址和密钥。
   *
   * @param apiUrl - LicenseOS API 基础地址（如 https://your-domain.com/api/v2）
   * @param apiKey - API 密钥（用于标识开发者身份）
   */
  static init(apiUrl: string, apiKey: string = ''): void {
    LicenseOS._api = apiUrl.replace(/\/$/, '');
    LicenseOS._apiKey = apiKey;
  }

  /**
   * 激活许可证。
   *
   * @param key - 许可证密钥
   * @returns 激活结果，status 为 'valid' 或 'active' 表示成功
   */
  static async activate(key: string): Promise<ApiResult> {
    return LicenseOS._request('/license/activate', {
      license_key: key,
      domain: 'figma-plugin',
    });
  }

  /**
   * 验证许可证状态（含试用模式支持）。
   *
   * 返回的 status 为 'valid' 时许可证有效。
   * 当 type='trial' 时，需配合 isTrialValid() 判断试用是否仍在有效期内。
   *
   * @param key - 许可证密钥
   * @returns 验证结果，包含 status、type、trial_ends_at 等字段
   */
  static async check(key: string): Promise<ApiResult> {
    return LicenseOS._request('/license/check', { license_key: key });
  }

  /**
   * 判断试用许可证是否仍在有效期内。
   *
   * 仅当 checkResult.type === 'trial' 时有意义。
   * 非试用类型许可证直接返回 true。
   *
   * @param checkResult - check() 返回的结果
   * @returns 试用是否有效
   */
  static isTrialValid(checkResult: ApiResult): boolean {
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
   * @param productSlug - 产品标识
   * @param currentVersion - 当前版本号（可选）
   * @param licenseKey - 许可证密钥（可选，用于鉴权获取更新）
   * @returns 更新信息，包含 up_to_date、latest_version、download_url 等字段
   */
  static async checkUpdate(
    productSlug: string,
    currentVersion: string = '',
    licenseKey: string = ''
  ): Promise<UpdateResult | null> {
    const params = new URLSearchParams({ product: productSlug });
    if (currentVersion) params.set('version', currentVersion);
    if (licenseKey) params.set('license_key', licenseKey);

    return LicenseOS._requestGet('/update/check', params);
  }

  /**
   * 上报防篡改完整性校验（非阻塞，每日仅上报一次）。
   *
   * 建议在插件启动时调用，SDK 自动限频确保每个客户端每天最多上报一次。
   *
   * @param licenseKey - 许可证密钥（可选）
   */
  static async reportIntegrity(licenseKey: string = ''): Promise<void> {
    if (LicenseOS._integrityReportedToday) return;
    LicenseOS._integrityReportedToday = true;

    const apiKeyHash = await LicenseOS._sha256(LicenseOS._apiKey);

    try {
      await LicenseOS._request('/integrity/report', {
        domain: 'figma-plugin',
        code_hash: 'figma-sdk-v1',
        api_key_hash: apiKeyHash,
        license_key: licenseKey,
        api_key: LicenseOS._apiKey,
      });
    } catch {
      // 防篡改上报失败不影响主流程
    }
  }

  /**
   * 保存许可证密钥到 Figma clientStorage。
   *
   * @param key - 许可证密钥
   */
  static async saveKey(key: string): Promise<void> {
    await figma.clientStorage.setAsync('licenseos_key', key);
  }

  /**
   * 从 Figma clientStorage 读取已保存的许可证密钥。
   *
   * @returns 已保存的密钥，未找到时返回空字符串
   */
  static async loadKey(): Promise<string> {
    return (await figma.clientStorage.getAsync('licenseos_key')) || '';
  }

  // ========== Internal Methods ==========

  /**
   * 发送 POST 请求到 LicenseOS API。
   *
   * @private
   * @param path - API 路径（如 /license/check）
   * @param data - 请求体数据
   * @returns API 响应结果
   */
  private static async _request(
    path: string,
    data: Record<string, any>
  ): Promise<ApiResult> {
    try {
      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
      };
      if (LicenseOS._apiKey) headers['X-API-KEY'] = LicenseOS._apiKey;

      const response = await fetch(LicenseOS._api + path, {
        method: 'POST',
        headers,
        body: JSON.stringify(data),
      });
      return await response.json();
    } catch (err: any) {
      return { status: 'error', message: err?.message || 'Network error' };
    }
  }

  /**
   * 发送 GET 请求到 LicenseOS API（可被 CDN 缓存）。
   *
   * @private
   * @param path - API 路径
   * @param params - 查询参数
   * @returns API 响应结果
   */
  private static async _requestGet(
    path: string,
    params?: URLSearchParams
  ): Promise<any> {
    try {
      let url = LicenseOS._api + path;
      if (params && params.toString()) url += '?' + params.toString();

      const headers: Record<string, string> = {};
      if (LicenseOS._apiKey) headers['X-API-KEY'] = LicenseOS._apiKey;

      const response = await fetch(url, { method: 'GET', headers });
      return await response.json();
    } catch (err: any) {
      return { status: 'error', message: err?.message || 'Network error' };
    }
  }

  /**
   * 计算字符串的 SHA-256 哈希值。
   *
   * @private
   * @param message - 待哈希的字符串
   * @returns 十六进制哈希值
   */
  private static async _sha256(message: string): Promise<string> {
    if (typeof crypto !== 'undefined' && crypto.subtle) {
      const msgBuffer = new TextEncoder().encode(message);
      const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
      return Array.from(new Uint8Array(hashBuffer))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
    }
    return '';
  }
}

export { LicenseOS };
export type { ApiResult, UpdateResult };
