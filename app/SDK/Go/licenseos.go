/**
 * LicenseOS Go SDK v1.0
 *
 * 适用于：Go Script、Go Source Code
 *
 * 使用方法：
 *   import "github.com/yourorg/licenseos-sdk-go"
 *
 *   client := licenseos.New("https://your-api.com", "your-api-key")
 *   result, _ := client.Activate("LICENSE-KEY", "my-domain.com")
 *   if client.IsValid() { /* 允许使用 */ }
 */
package licenseos

import (
	"bytes"
	"crypto/sha256"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"strings"
	"time"
)

type Client struct {
	APIUrl     string
	APIKey     string
	LicenseKey string
	CacheTTL   time.Duration
	lastCheck  map[string]interface{}
	cacheFile  string
	dataFile   string
}

func New(apiUrl, apiKey string) *Client {
	c := &Client{
		APIUrl:    strings.TrimRight(apiUrl, "/"),
		APIKey:    apiKey,
		CacheTTL:  12 * time.Hour,
		dataFile:  os.TempDir() + "/.licenseos_data.json",
		cacheFile: os.TempDir() + "/.licenseos_cache",
	}
	c.loadData()
	return c
}

func (c *Client) Activate(licenseKey, domain string) (map[string]interface{}, error) {
	c.LicenseKey = licenseKey
	result, err := c.apiPost("/api/v2/license/activate", map[string]interface{}{
		"license_key": licenseKey,
		"domain":      domain,
	})
	if err != nil {
		return nil, err
	}
	if status, ok := result["status"].(string); ok && (status == "valid" || status == "active") {
		c.saveData()
	}
	return result, nil
}

func (c *Client) IsValid() bool {
	if c.LicenseKey == "" {
		return false
	}
	result, err := c.apiPost("/api/v2/license/check", map[string]interface{}{
		"license_key": c.LicenseKey,
	})
	if err != nil {
		return false
	}
	c.lastCheck = result
	status, _ := result["status"].(string)
	return status == "valid"
}

func (c *Client) Deactivate(domain string) (map[string]interface{}, error) {
	result, err := c.apiPost("/api/v2/license/deactivate", map[string]interface{}{
		"license_key": c.LicenseKey,
		"domain":      domain,
	})
	c.LicenseKey = ""
	c.lastCheck = nil
	return result, err
}

func (c *Client) GetStatus() map[string]interface{} {
	if c.LicenseKey == "" {
		return map[string]interface{}{"status": "inactive"}
	}
	if c.lastCheck != nil {
		return c.lastCheck
	}
	return map[string]interface{}{"status": "unknown"}
}

// Integrity report
func (c *Client) ReportIntegrity(sdkFilePath string) {
	data, err := os.ReadFile(sdkFilePath)
	if err != nil {
		return
	}
	codeHash := fmt.Sprintf("%x", sha256.Sum256(data))
	apiKeyHash := fmt.Sprintf("%x", sha256.Sum256([]byte(c.APIKey)))

	hostname, _ := os.Hostname()
	go c.apiPost("/api/v2/integrity/report", map[string]interface{}{
		"domain":       hostname,
		"code_hash":    codeHash,
		"api_key_hash": apiKeyHash,
		"license_key":  c.LicenseKey,
		"api_key":      c.APIKey,
	})
}

// Internal
func (c *Client) apiPost(endpoint string, data map[string]interface{}) (map[string]interface{}, error) {
	body, _ := json.Marshal(data)
	req, _ := http.NewRequest("POST", c.APIUrl+endpoint, bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-API-KEY", c.APIKey)

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return map[string]interface{}{"status": "error", "message": err.Error()}, nil
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)
	var result map[string]interface{}
	json.Unmarshal(respBody, &result)
	return result, nil
}

func (c *Client) loadData() {
	data, err := os.ReadFile(c.dataFile)
	if err != nil {
		return
	}
	var m map[string]string
	json.Unmarshal(data, &m)
	c.LicenseKey = m["license_key"]
}

func (c *Client) saveData() {
	m := map[string]string{"license_key": c.LicenseKey}
	data, _ := json.Marshal(m)
	os.WriteFile(c.dataFile, data, 0644)
}

// SHA256 helper
func SHA256(input string) string {
	return fmt.Sprintf("%x", sha256.Sum256([]byte(input)))
}
