<?php

namespace App\Console\Commands;

use App\Models\UpdatePackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncFilesToR2 extends Command
{
    protected $signature = 'files:sync-to-r2
                            {--dry-run : Show what would be synced without actually uploading}
                            {--delete-local : Delete local files after successful R2 upload}';

    protected $description = 'Migrate local product files to Cloudflare R2 storage';

    public function handle(): int
    {
        $storageService = app(\App\Services\ProductStorageService::class);

        if (!$storageService->isCloudDiskConfigured('r2')) {
            $this->error('R2 is not configured. Set R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, and R2_BUCKET in .env');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $deleteLocal = $this->option('delete-local');

        // Find all packages with local file URLs
        $packages = UpdatePackage::whereNotNull('file_url')
            ->where('file_url', 'like', '/storage/%')
            ->get();

        if ($packages->isEmpty()) {
            $this->info('No local files to sync.');
            return self::SUCCESS;
        }

        $this->info("Found {$packages->count()} files to sync to R2.");
        if ($dryRun) {
            $this->warn('[DRY RUN] No files will actually be uploaded.');
        }

        $synced = 0;
        $failed = 0;

        foreach ($packages as $package) {
            $relativePath = ltrim($package->file_url, '/storage/');
            $localPath = 'app/public/' . $relativePath;

            if (!Storage::disk('local')->exists($localPath)) {
                $this->warn("  SKIP: {$relativePath} (file not found on local disk)");
                $failed++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would sync: {$relativePath}");
                $synced++;
                continue;
            }

            try {
                $content = Storage::disk('local')->get($localPath);
                Storage::disk('r2')->put($relativePath, $content);

                // Update DB to point to R2 URL
                $r2Url = $this->buildR2Url($relativePath);
                $package->update(['file_url' => $r2Url]);

                // Also update the product's download_url if it matches
                if ($package->product && $package->product->download_url === $package->getOriginal('file_url')) {
                    $package->product->update(['download_url' => $r2Url]);
                }

                $this->info("  OK: {$relativePath} → R2");
                $synced++;

                // Optionally delete local file
                if ($deleteLocal) {
                    Storage::disk('local')->delete($localPath);
                    $this->line("  DELETED local: {$relativePath}");
                }

            } catch (\Exception $e) {
                $this->error("  FAIL: {$relativePath} — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Synced: {$synced}, Failed: {$failed}");

        if ($synced > 0 && !$dryRun) {
            $this->newLine();
            $this->warn('Remember to update PRODUCT_STORAGE_DISK=r2 in .env to use R2 for new uploads.');
        }

        return self::SUCCESS;
    }

    protected function buildR2Url(string $path): string
    {
        $customDomain = config('filesystems.disks.r2.url');
        if ($customDomain) {
            return rtrim($customDomain, '/') . '/' . ltrim($path, '/');
        }

        $endpoint = config('filesystems.disks.r2.endpoint');
        $bucket = config('filesystems.disks.r2.bucket');
        return rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($path, '/');
    }
}
