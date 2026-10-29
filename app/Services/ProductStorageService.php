<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductStorageService
{
    /**
     * All supported cloud storage disk names.
     */
    protected const CLOUD_DISKS = ['r2', 'aliyun-oss', 'tencent-cos', 'qiniu'];

    /**
     * Get the active disk for NEW product uploads.
     */
    protected function activeDisk(): string
    {
        return config('filesystems.product_disk', 'public');
    }

    /**
     * Check if the given disk is a cloud storage disk.
     */
    protected function isCloudDisk(string $disk): bool
    {
        return in_array($disk, self::CLOUD_DISKS, true);
    }

    /**
     * Store a product ZIP file. Returns the file URL/path for DB storage.
     *
     * @param UploadedFile $file      The uploaded ZIP file
     * @param string       $slug      Product slug
     * @param string       $version   Version string
     * @param int|null     $tenantId  Tenant/Developer ID
     * @return array{url: string, size: int, disk: string}
     */
    public function store(UploadedFile $file, string $slug, string $version, ?int $tenantId = null): array
    {
        $disk = $this->activeDisk();
        $storePath = ($tenantId ?? 'default') . "_{$slug}/{$version}.zip";

        if ($this->isCloudDisk($disk)) {
            // Upload to cloud storage
            $path = $file->storeAs('products', $storePath, $disk);
            $url = $this->cloudUrl($disk, $path);

            return [
                'url'  => $url,
                'size' => $file->getSize(),
                'disk' => $disk,
                'path' => $path,
            ];
        }

        // Default: local public disk
        $path = $file->storeAs('products', $storePath, 'public');
        $url = '/storage/' . $path;

        return [
            'url'  => $url,
            'size' => $file->getSize(),
            'disk' => 'public',
            'path' => $path,
        ];
    }

    /**
     * Store the original (pre-injection) ZIP file alongside the injected version.
     *
     * @param UploadedFile $file      The original uploaded ZIP file
     * @param string       $slug      Product slug
     * @param string       $version   Version string
     * @param int|null     $tenantId  Tenant/developer ID
     * @return array{url: string, size: int, disk: string}
     */
    public function storeOriginal(UploadedFile $file, string $slug, string $version, ?int $tenantId = null): array
    {
        $disk = $this->activeDisk();
        $storePath = ($tenantId ?? 'default') . "_{$slug}/{$version}_original.zip";

        if ($this->isCloudDisk($disk)) {
            $path = $file->storeAs('products', $storePath, $disk);
            $url = $this->cloudUrl($disk, $path);

            return [
                'url'  => $url,
                'size' => $file->getSize(),
                'disk' => $disk,
                'path' => $path,
            ];
        }

        // Default: local public disk
        $path = $file->storeAs('products', $storePath, 'public');
        $url = '/storage/' . $path;

        return [
            'url'  => $url,
            'size' => $file->getSize(),
            'disk' => 'public',
            'path' => $path,
        ];
    }

    /**
     * Download a stored file to a local path.
     *
     * @param string $url       The stored file URL (local /storage/... or cloud URL)
     * @param string $localPath Absolute local path to write the file to
     */
    public function downloadTo(string $url, string $localPath): void
    {
        $cloudDisk = $this->detectCloudDiskFromUrl($url);

        if ($cloudDisk) {
            // Cloud storage: download via signed URL
            $path = $this->pathFromCloudUrl($cloudDisk, $url);
            $signedUrl = Storage::disk($cloudDisk)->temporaryUrl($path, now()->addMinutes(10));
            $content = file_get_contents($signedUrl);
            file_put_contents($localPath, $content);
            return;
        }

        // Local storage: resolve path and copy
        $relativePath = ltrim($url, '/storage/');
        $sourcePath = storage_path('app/public/' . $relativePath);

        if (!file_exists($sourcePath)) {
            // Fallback: try public_path
            $sourcePath = public_path($relativePath);
        }

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Source file not found: {$url}");
        }

        copy($sourcePath, $localPath);
    }

    /**
     * Resolve a downloadable file path for a given file_url.
     * Supports R2 (signed URL), local path, and fallback from R2→local.
     *
     * @return string|null  Absolute file path (local) or signed URL (R2)
     */
    public function resolveDownload(string $fileUrl): ?string
    {
        // Case 1: Cloud storage URL — detect which cloud disk and return signed URL
        $cloudDisk = $this->detectCloudDiskFromUrl($fileUrl);
        if ($cloudDisk) {
            $path = $this->pathFromCloudUrl($cloudDisk, $fileUrl);
            if (Storage::disk($cloudDisk)->exists($path)) {
                return Storage::disk($cloudDisk)->temporaryUrl($path, now()->addMinutes(10));
            }
            return null;
        }

        // Case 2: Local file
        $relativePath = ltrim($fileUrl, '/storage/');
        $fullPath = storage_path('app/public/' . $relativePath);

        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // Case 3: Fallback — file might have been moved to any cloud disk
        foreach (self::CLOUD_DISKS as $disk) {
            if ($this->isCloudDiskConfigured($disk)) {
                if (Storage::disk($disk)->exists($relativePath)) {
                    return Storage::disk($disk)->temporaryUrl($relativePath, now()->addMinutes(10));
                }
            }
        }

        return null;
    }

    /**
     * Delete a product file from all disks.
     */
    public function delete(string $fileUrl): void
    {
        // Delete from cloud storage if detected
        $cloudDisk = $this->detectCloudDiskFromUrl($fileUrl);
        if ($cloudDisk) {
            $path = $this->pathFromCloudUrl($cloudDisk, $fileUrl);
            Storage::disk($cloudDisk)->delete($path);
            return;
        }

        // Delete from local
        $relativePath = ltrim($fileUrl, '/storage/');
        Storage::disk('public')->delete($relativePath);

        // Also try all cloud disks in case it was synced
        foreach (self::CLOUD_DISKS as $disk) {
            if ($this->isCloudDiskConfigured($disk)) {
                Storage::disk($disk)->delete($relativePath);
            }
        }
    }

    /**
     * Check if a file exists on any disk.
     */
    public function exists(string $fileUrl): bool
    {
        return $this->resolveDownload($fileUrl) !== null;
    }

    /**
     * Check if any cloud storage is configured and available.
     */
    public function isAnyCloudConfigured(): bool
    {
        foreach (self::CLOUD_DISKS as $disk) {
            if ($this->isCloudDiskConfigured($disk)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a specific cloud disk is configured.
     */
    public function isCloudDiskConfigured(string $disk): bool
    {
        $diskConfig = config("filesystems.disks.{$disk}");
        if (!$diskConfig) return false;

        return !empty($diskConfig['key'])
            && !empty($diskConfig['secret'])
            && !empty($diskConfig['bucket']);
    }

    /**
     * Build cloud URL for a stored path on any disk.
     */
    protected function cloudUrl(string $disk, string $path): string
    {
        $customDomain = config("filesystems.disks.{$disk}.url");
        if ($customDomain) {
            return rtrim($customDomain, '/') . '/' . ltrim($path, '/');
        }
        // Fallback: use bucket endpoint URL
        $endpoint = config("filesystems.disks.{$disk}.endpoint");
        $bucket = config("filesystems.disks.{$disk}.bucket");
        return rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($path, '/');
    }

    /**
     * Detect which cloud disk a URL belongs to based on its content.
     */
    protected function detectCloudDiskFromUrl(string $url): ?string
    {
        foreach (self::CLOUD_DISKS as $disk) {
            $customDomain = config("filesystems.disks.{$disk}.url");
            $endpoint = config("filesystems.disks.{$disk}.endpoint");

            if ($customDomain && str_starts_with($url, $customDomain)) {
                return $disk;
            }
            if ($endpoint && str_contains($url, parse_url($endpoint, PHP_URL_HOST) ?: '')) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Extract storage path from a cloud URL.
     */
    protected function pathFromCloudUrl(string $disk, string $url): string
    {
        $customDomain = config("filesystems.disks.{$disk}.url");
        if ($customDomain) {
            return ltrim(str_replace($customDomain, '', $url), '/');
        }

        // Generic: take path after bucket name
        $bucket = config("filesystems.disks.{$disk}.bucket");
        $pos = strpos($url, $bucket . '/');
        if ($pos !== false) {
            return substr($url, $pos + strlen($bucket) + 1);
        }

        return ltrim(parse_url($url, PHP_URL_PATH), '/');
    }
}
