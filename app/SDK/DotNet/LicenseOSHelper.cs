/**
 * LicenseOS .NET SDK v1.0
 *
 * 适用于：Desktop App (.NET/WPF/WinForms/MAUI)
 *
 * 使用方法：
 *   var licenseos = new LicenseOSHelper("https://your-api.com", "your-api-key");
 *   var result = await licenseos.ActivateAsync("LICENSE-KEY");
 *   if (await licenseos.IsValidAsync()) { /* 允许使用 */ }
 */

using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace LicenseOS
{
    public class LicenseOSHelper
    {
        private readonly string _apiUrl;
        private readonly string _apiKey;
        private readonly HttpClient _httpClient;
        private string _licenseKey = "";
        private Dictionary<string, object> _lastCheck;

        public LicenseOSHelper(string apiUrl, string apiKey)
        {
            _apiUrl = apiUrl.TrimEnd('/');
            _apiKey = apiKey;
            _httpClient = new HttpClient { Timeout = TimeSpan.FromSeconds(10) };
            _licenseKey = Environment.GetEnvironmentVariable("LICENSEOS_KEY") ?? "";
        }

        public async Task<Dictionary<string, object>> ActivateAsync(string licenseKey, string domain = "")
        {
            _licenseKey = licenseKey;
            domain = string.IsNullOrEmpty(domain) ? Environment.MachineName : domain;

            var result = await ApiPostAsync("/api/v2/license/activate", new Dictionary<string, object>
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

            var result = await ApiPostAsync("/api/v2/license/check", new Dictionary<string, object>
            {
                ["license_key"] = _licenseKey,
            });

            _lastCheck = result;
            return result.TryGetValue("status", out var s) && s.ToString() == "valid";
        }

        public async Task<Dictionary<string, object>> DeactivateAsync(string domain = "")
        {
            domain = string.IsNullOrEmpty(domain) ? Environment.MachineName : domain;
            var result = await ApiPostAsync("/api/v2/license/deactivate", new Dictionary<string, object>
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

        // === Internal ===

        private async Task<Dictionary<string, object>> ApiPostAsync(string endpoint, Dictionary<string, object> data)
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
