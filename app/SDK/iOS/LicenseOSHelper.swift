/**
 * LicenseOS iOS SDK v1.0
 *
 * 适用于：iOS App
 *
 * 使用方法：
 *   let licenseos = LicenseOSHelper(apiUrl: "https://your-api.com", apiKey: "your-key")
 *   licenseos.activate("LICENSE-KEY") { result in
 *       if result["status"] as? String == "valid" { /* 允许使用 */ }
 *   }
 */

import Foundation
import CommonCrypto

public class LicenseOSHelper {
    private let apiUrl: String
    private let apiKey: String
    private let defaults = UserDefaults.standard
    private var licenseKey: String = ""
    private var lastCheck: [String: Any]? = nil

    public typealias Callback = ([String: Any]) -> Void

    public init(apiUrl: String, apiKey: String) {
        self.apiUrl = apiUrl.hasSuffix("/") ? String(apiUrl.dropLast()) : apiUrl
        self.apiKey = apiKey
        self.licenseKey = defaults.string(forKey: "licenseos_key") ?? ""
    }

    public func activate(_ licenseKey: String, completion: @escaping Callback) {
        self.licenseKey = licenseKey
        let domain = Bundle.main.bundleIdentifier ?? "ios-app"

        apiPost("/api/v2/license/activate", data: [
            "license_key": licenseKey,
            "domain": domain,
        ]) { result in
            if let status = result["status"] as? String,
               ["valid", "active"].contains(status) {
                self.defaults.set(licenseKey, forKey: "licenseos_key")
            }
            completion(result)
        }
    }

    public func isValid(completion: @escaping Callback) {
        guard !licenseKey.isEmpty else {
            completion(["status": "inactive"])
            return
        }

        apiPost("/api/v2/license/check", data: [
            "license_key": licenseKey,
        ]) { result in
            self.lastCheck = result
            completion(result)
        }
    }

    public func deactivate(completion: @escaping Callback) {
        let domain = Bundle.main.bundleIdentifier ?? "ios-app"
        apiPost("/api/v2/license/deactivate", data: [
            "license_key": licenseKey,
            "domain": domain,
        ]) { result in
            self.licenseKey = ""
            self.lastCheck = nil
            self.defaults.removeObject(forKey: "licenseos_key")
            completion(result)
        }
    }

    public func getStatus() -> [String: Any] {
        guard !licenseKey.isEmpty else { return ["status": "inactive"] }
        return lastCheck ?? ["status": "unknown"]
    }

    // MARK: - Internal

    private func apiPost(_ endpoint: String, data: [String: Any], completion: @escaping Callback) {
        guard let url = URL(string: apiUrl + endpoint) else {
            completion(["status": "error", "message": "Invalid URL"])
            return
        }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue(apiKey, forHTTPHeaderField: "X-API-KEY")
        request.timeoutInterval = 10

        do {
            request.httpBody = try JSONSerialization.data(withJSONObject: data)
        } catch {
            completion(["status": "error", "message": "JSON encode error"])
            return
        }

        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                completion(["status": "error", "message": error.localizedDescription])
                return
            }
            guard let data = data,
                  let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
                completion(["status": "error", "message": "Invalid response"])
                return
            }
            DispatchQueue.main.async { completion(json) }
        }.resume()
    }

    static func sha256(_ input: String) -> String {
        let data = Data(input.utf8)
        var hash = [UInt8](repeating: 0, count: Int(CC_SHA256_DIGEST_LENGTH))
        data.withUnsafeBytes { CC_SHA256($0.baseAddress, CC_LONG(data.count), &hash) }
        return hash.map { String(format: "%02x", $0) }.joined()
    }
}
