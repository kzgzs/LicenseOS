/**
 * LicenseOS .NET SDK v2.0（Web App / Desktop 通用）
 *
 * 一键注入时平台填实下方 LICENSEOS-CONFIG 配置块（ConfigJson/ManifestJson），
 * 不新建任何专用目录；项目既有代码引用本文件即可互通。
 *
 * 使用方法：
 *   var helper = new LicenseOS.LicenseOSHelper();          // 自动读注入配置
 *   var r = await helper.ActivateAsync("LICENSE-KEY", "example.com");
 *   var ok = await helper.IsValidAsync();
 *   await LicenseOS.IntegrityGuard.ReportAsync();          // 防篡改上报（配置块自哈希）
 *
 * 授权阶段（供项目 UI 渲染）：未激活→输入证书激活；有效→域名/激活时间/到期时间；
 * 快到期（WarningDays 内）→剩余天数+续费按钮（RenewUrl 直达平台产品详情页）；过期/无效/篡改→冻结+续费按钮。
 */

using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Threading.Tasks;

namespace LicenseOS
{
    // LICENSEOS-CONFIG-BEGIN
    public static class LicenseOSConfig
    {
        public const string ConfigJson = "";
        public const string ManifestJson = "";
    }
    // LICENSEOS-CONFIG-END

    public static class LicenseOSMeta
    {
        public static JsonObject Parse() =>
            string.IsNullOrEmpty(LicenseOSConfig.ConfigJson)
                ? new JsonObject()
                : (JsonNode.Parse(LicenseOSConfig.ConfigJson) as JsonObject ?? new JsonObject());

        public static string ApiUrl => (Parse()["api_url"]?.GetValue<string>() ?? "").TrimEnd('/');
        public static string ApiKey => Parse()["api_key"]?.GetValue<string>() ?? "";
        public static string Slug => Parse()["slug"]?.GetValue<string>() ?? "";
        public static string RenewUrl => Parse()["product_url"]?.GetValue<string>() ?? "";
        public static int WarningDays => Parse()["warning_days"]?.GetValue<int>() ?? 7;
        public static bool Injected => !string.IsNullOrEmpty(LicenseOSConfig.ConfigJson);

        /** 配置块自哈希：注入时平台存同值，运行时被改/被删即上报不一致→篡改冻结 */
        public static string ConfigHash => LicenseOSHelper.Sha256(LicenseOSConfig.ConfigJson);

        /** 剩余天数（null=无到期时间）；<=WarningDays 即快到期阶段 */
        public static int? DaysLeft(DateTimeOffset? expiresAt) =>
            expiresAt is null ? null : (int)Math.Floor((expiresAt.Value - DateTimeOffset.UtcNow).TotalDays);
    }

    public static class IntegrityGuard
    {
        private static DateTime _lastReport = DateTime.MinValue;

        /** 每日限频上报配置块哈希 + api_key 哈希；平台比对不一致即标记篡改并拉黑域名 */
        public static async Task ReportAsync(string domain, string licenseKey = "")
        {
            if (!LicenseOSMeta.Injected) return;
            if ((DateTime.UtcNow - _lastReport).TotalHours < 24) return;
            _lastReport = DateTime.UtcNow;
            var helper = new LicenseOSHelper();
            await helper.PostAsync("/api/v2/integrity/report", new Dictionary<string, object>
            {
                ["domain"] = domain,
                ["code_hash"] = LicenseOSMeta.ConfigHash,
                ["api_key_hash"] = LicenseOSHelper.Sha256(LicenseOSMeta.ApiKey),
                ["license_key"] = licenseKey,
                ["product_slug"] = LicenseOSMeta.Slug,
            });
        }
    }

    public class LicenseOSHelper
    {
        private readonly string _apiUrl;
        private readonly string _apiKey;
        private readonly HttpClient _httpClient;
        private string _licenseKey = "";
        private Dictionary<string, object> _lastCheck;

        public LicenseOSHelper(string apiUrl = "", string apiKey = "")
        {
            _apiUrl = (apiUrl != "" ? apiUrl : LicenseOSMeta.ApiUrl).TrimEnd('/');
            _apiKey = apiKey != "" ? apiKey : LicenseOSMeta.ApiKey;
            _httpClient = new HttpClient { Timeout = TimeSpan.FromSeconds(10) };
            _licenseKey = Environment.GetEnvironmentVariable("LICENSEOS_KEY") ?? "";
        }

        public string RenewUrl => LicenseOSMeta.RenewUrl;
        public int WarningDays => LicenseOSMeta.WarningDays;

        public async Task<Dictionary<string, object>> ActivateAsync(string licenseKey, string domain = "")
        {
            _licenseKey = licenseKey;
            domain = string.IsNullOrEmpty(domain) ? Environment.MachineName : domain;
            var result = await PostAsync("/api/v2/license/activate", new Dictionary<string, object>
            {
                ["license_key"] = licenseKey,
                ["domain"] = domain,
            });
            if (result.TryGetValue("status", out var status) &&
                (status.ToString() == "valid" || status.ToString() == "active"))
            {
                Environment.SetEnvironmentVariable("LICENSEOS_KEY", licenseKey);
            }
            return result;
        }

        public async Task<bool> IsValidAsync()
        {
            if (string.IsNullOrEmpty(_licenseKey)) return false;
            var result = await PostAsync("/api/v2/license/check", new Dictionary<string, object>
            {
                ["license_key"] = _licenseKey,
            });
            _lastCheck = result;
            return result.TryGetValue("status", out var s) && s.ToString() == "valid";
        }

        public async Task<Dictionary<string, object>> CheckAsync(string licenseKey, string domain = "")
        {
            var data = new Dictionary<string, object> { ["license_key"] = licenseKey };
            if (domain != "") data["domain"] = domain;
            return await PostAsync("/api/v2/license/check", data);
        }

        public async Task<Dictionary<string, object>> DeactivateAsync(string domain = "")
        {
            domain = string.IsNullOrEmpty(domain) ? Environment.MachineName : domain;
            var result = await PostAsync("/api/v2/license/deactivate", new Dictionary<string, object>
            {
                ["license_key"] = _licenseKey,
                ["domain"] = domain,
            });
            _licenseKey = "";
            _lastCheck = null;
            Environment.SetEnvironmentVariable("LICENSEOS_KEY", "");
            return result;
        }

        public Dictionary<string, object> GetStatus()
        {
            if (string.IsNullOrEmpty(_licenseKey))
                return new Dictionary<string, object> { ["status"] = "inactive" };
            return _lastCheck ?? new Dictionary<string, object> { ["status"] = "unknown" };
        }

        public async Task<Dictionary<string, object>> PostAsync(string endpoint, Dictionary<string, object> data)
        {
            try
            {
                var request = new HttpRequestMessage(HttpMethod.Post, _apiUrl + endpoint)
                {
                    Content = new StringContent(JsonSerializer.Serialize(data), Encoding.UTF8, "application/json"),
                };
                request.Headers.Add("X-API-KEY", _apiKey);
                var response = await _httpClient.SendAsync(request);
                var body = await response.Content.ReadAsStringAsync();
                return JsonSerializer.Deserialize<Dictionary<string, object>>(body)
                    ?? new Dictionary<string, object> { ["status"] = "error" };
            }
            catch (Exception ex)
            {
                return new Dictionary<string, object> { ["status"] = "error", ["message"] = ex.Message };
            }
        }

        public static string Sha256(string input)
        {
            using var sha = SHA256.Create();
            var bytes = sha.ComputeHash(Encoding.UTF8.GetBytes(input));
            var sb = new StringBuilder();
            foreach (var b in bytes) sb.Append(b.ToString("x2"));
            return sb.ToString();
        }
    }
}