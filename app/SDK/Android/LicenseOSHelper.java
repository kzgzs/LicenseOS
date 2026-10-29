/**
 * LicenseOS Android SDK v1.0
 * 
 * 适用于：Android App
 * 
 * 使用方法：
 *   LicenseOSHelper licenseos = new LicenseOSHelper(context, "https://your-api.com", "your-api-key");
 *   licenseos.activate("LICENSE-KEY", (result) -> {
 *       if (result.isValid()) { /* 允许使用 */ }
 *   });
 */
package com.licenseos.sdk;

import android.content.Context;
import android.content.SharedPreferences;
import android.os.AsyncTask;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;

public class LicenseOSHelper {
    private final String apiUrl;
    private final String apiKey;
    private final Context context;
    private final SharedPreferences prefs;
    private String licenseKey = "";
    private JSONObject lastCheck = null;

    public interface Callback {
        void onResult(JSONObject result);
    }

    public LicenseOSHelper(Context context, String apiUrl, String apiKey) {
        this.context = context;
        this.apiUrl = apiUrl.replaceAll("/$", "");
        this.apiKey = apiKey;
        this.prefs = context.getSharedPreferences("licenseos", Context.MODE_PRIVATE);
        this.licenseKey = prefs.getString("license_key", "");
    }

    public void activate(String licenseKey, Callback callback) {
        this.licenseKey = licenseKey;
        new AsyncTask<Void, Void, JSONObject>() {
            @Override
            protected JSONObject doInBackground(Void... voids) {
                JSONObject data = new JSONObject();
                try {
                    data.put("license_key", licenseKey);
                    data.put("domain", "android-" + context.getPackageName());
                    return apiPost("/api/v2/license/activate", data);
                } catch (Exception e) {
                    return errorResult(e.getMessage());
                }
            }
            @Override
            protected void onPostExecute(JSONObject result) {
                try {
                    String status = result.getString("status");
                    if ("valid".equals(status) || "active".equals(status)) {
                        prefs.edit().putString("license_key", licenseKey).apply();
                    }
                } catch (Exception ignored) {}
                if (callback != null) callback.onResult(result);
            }
        }.execute();
    }

    public void isValid(Callback callback) {
        if (licenseKey.isEmpty()) {
            if (callback != null) callback.onResult(resultJson("invalid", ""));
            return;
        }
        new AsyncTask<Void, Void, JSONObject>() {
            @Override
            protected JSONObject doInBackground(Void... voids) {
                try {
                    JSONObject data = new JSONObject();
                    data.put("license_key", licenseKey);
                    return apiPost("/api/v2/license/check", data);
                } catch (Exception e) {
                    return errorResult(e.getMessage());
                }
            }
            @Override
            protected void onPostExecute(JSONObject result) {
                lastCheck = result;
                if (callback != null) callback.onResult(result);
            }
        }.execute();
    }

    public void deactivate(Callback callback) {
        new AsyncTask<Void, Void, JSONObject>() {
            @Override
            protected JSONObject doInBackground(Void... voids) {
                try {
                    JSONObject data = new JSONObject();
                    data.put("license_key", licenseKey);
                    data.put("domain", "android-" + context.getPackageName());
                    return apiPost("/api/v2/license/deactivate", data);
                } catch (Exception e) {
                    return errorResult(e.getMessage());
                }
            }
            @Override
            protected void onPostExecute(JSONObject result) {
                licenseKey = "";
                lastCheck = null;
                prefs.edit().remove("license_key").apply();
                if (callback != null) callback.onResult(result);
            }
        }.execute();
    }

    // === Internal ===

    private JSONObject apiPost(String endpoint, JSONObject data) {
        try {
            URL url = new URL(apiUrl + endpoint);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("X-API-KEY", apiKey);
            conn.setDoOutput(true);
            conn.setConnectTimeout(5000);
            conn.setReadTimeout(10000);

            try (OutputStream os = conn.getOutputStream()) {
                os.write(data.toString().getBytes("UTF-8"));
            }

            BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) sb.append(line);
            reader.close();

            return new JSONObject(sb.toString());
        } catch (Exception e) {
            return errorResult(e.getMessage());
        }
    }

    private JSONObject errorResult(String msg) {
        try {
            JSONObject r = new JSONObject();
            r.put("status", "error");
            r.put("message", msg);
            return r;
        } catch (Exception e) {
            return new JSONObject();
        }
    }

    private JSONObject resultJson(String status, String msg) {
        try {
            JSONObject r = new JSONObject();
            r.put("status", status);
            r.put("message", msg);
            return r;
        } catch (Exception e) {
            return new JSONObject();
        }
    }

    static String sha256(String input) {
        try {
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] hash = md.digest(input.getBytes("UTF-8"));
            StringBuilder hex = new StringBuilder();
            for (byte b : hash) hex.append(String.format("%02x", b));
            return hex.toString();
        } catch (Exception e) {
            return "";
        }
    }
}
