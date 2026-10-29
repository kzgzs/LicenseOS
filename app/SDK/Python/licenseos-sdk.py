"""
LicenseOS Python SDK v1.0

适用于：Python Script、Python Source Code、Python API Service

使用方法：
    from licenseos_sdk import LicenseOS
    
    client = LicenseOS(api_url="https://your-api.com", api_key="your-key")
    client.activate("LICENSE-KEY")
    if client.is_valid():
        # 允许使用
        pass
"""

import hashlib
import json
import os
import time
import urllib.request
import urllib.error
import tempfile


class LicenseOS:
    def __init__(
        self,
        api_url: str,
        api_key: str,
        storage_path: str = "",
        cache_ttl: int = 43200,  # 12 hours
    ):
        self.api_url = api_url.rstrip("/")
        self.api_key = api_key
        self.cache_ttl = cache_ttl
        self.storage_path = storage_path or tempfile.gettempdir()
        self.license_key = ""
        self.last_check = None
        self.domain = os.environ.get("HTTP_HOST", os.environ.get("SERVER_NAME", "localhost"))
        
        self._license_file = os.path.join(self.storage_path, ".licenseos_data.json")
        self._cache_file = os.path.join(self.storage_path, ".licenseos_cache")
        
        self._load_data()

    # === Core Methods ===

    def activate(self, license_key: str) -> dict:
        """激活许可证"""
        self.license_key = license_key
        result = self._api_post("/api/v2/license/activate", {
            "license_key": license_key,
            "domain": self.domain,
        })
        if result.get("status") in ("valid", "active"):
            self._save_data()
        return result

    def is_valid(self) -> bool:
        """检查许可证是否有效"""
        if not self.license_key:
            return False
        
        cached = self._get_cached()
        if cached is not None:
            return cached
        
        result = self._api_post("/api/v2/license/check", {
            "license_key": self.license_key,
        })
        valid = result.get("status") == "valid"
        self.last_check = result
        self._set_cached(valid)
        
        self.report_integrity()
        return valid

    def deactivate(self) -> dict:
        """停用许可证"""
        if not self.license_key:
            return {"status": "error", "message": "No license key"}
        
        result = self._api_post("/api/v2/license/deactivate", {
            "license_key": self.license_key,
            "domain": self.domain,
        })
        self.license_key = ""
        self.last_check = None
        self._clear_cache()
        self._save_data()
        return result

    def get_status(self) -> dict:
        """获取许可证状态"""
        if not self.license_key:
            return {"status": "inactive"}
        return self.last_check or {"status": "unknown"}

    def check_for_update(self, product_slug: str, current_version: str) -> dict | None:
        """检查更新"""
        result = self._api_post("/api/v2/update/check", {
            "product_slug": product_slug,
            "current_version": current_version,
            "license_key": self.license_key,
        })
        if result.get("download_url"):
            return result
        return None

    # === Integrity Check ===

    def report_integrity(self):
        """报告代码完整性"""
        sdk_file = __file__
        if not os.path.exists(sdk_file):
            return
        
        with open(sdk_file, "rb") as f:
            code_hash = hashlib.sha256(f.read()).hexdigest()
        
        api_key_hash = hashlib.sha256(self.api_key.encode()).hexdigest()
        
        try:
            self._api_post("/api/v2/integrity/report", {
                "domain": self.domain,
                "code_hash": code_hash,
                "api_key_hash": api_key_hash,
                "license_key": self.license_key,
                "api_key": self.api_key,
            })
        except Exception:
            pass  # 静默失败

    # === Internal Methods ===

    def _api_post(self, endpoint: str, data: dict) -> dict:
        url = self.api_url + endpoint
        payload = json.dumps(data).encode("utf-8")
        
        req = urllib.request.Request(
            url,
            data=payload,
            headers={
                "Content-Type": "application/json",
                "X-API-KEY": self.api_key,
            },
            method="POST",
        )
        
        try:
            with urllib.request.urlopen(req, timeout=10) as resp:
                return json.loads(resp.read().decode())
        except (urllib.error.URLError, json.JSONDecodeError):
            return {"status": "error", "message": "Network error"}

    # Data persistence
    def _load_data(self):
        if os.path.exists(self._license_file):
            try:
                with open(self._license_file) as f:
                    data = json.load(f)
                self.license_key = data.get("license_key", "")
            except (json.JSONDecodeError, IOError):
                pass

    def _save_data(self):
        os.makedirs(self.storage_path, exist_ok=True)
        with open(self._license_file, "w") as f:
            json.dump({"license_key": self.license_key, "domain": self.domain}, f)

    # Cache
    def _get_cached(self) -> bool | None:
        if not os.path.exists(self._cache_file):
            return None
        if time.time() - os.path.getmtime(self._cache_file) > self.cache_ttl:
            return None
        return open(self._cache_file).read().strip() == "1"

    def _set_cached(self, valid: bool):
        os.makedirs(self.storage_path, exist_ok=True)
        with open(self._cache_file, "w") as f:
            f.write("1" if valid else "0")

    def _clear_cache(self):
        try:
            os.unlink(self._cache_file)
        except OSError:
            pass
