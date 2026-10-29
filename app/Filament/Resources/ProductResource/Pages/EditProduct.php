<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Prepare record data before filling the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // FileUpload expects relative disk paths; DB may store public URLs.
        // Legacy rows may hold a plain JSON string or an array of raw URL
        // strings instead of [{url, caption}] — normalize defensively so the
        // form never crashes on old data.
        //
        // IMPORTANT: Filament v3 FileUpload fetches file metadata (size/url)
        // from disk during afterStateHydrated, so any state pointing at a
        // missing file (or a full URL) throws FileNotFoundException => 500.
        // Every value is therefore converted to a path and verified to exist;
        // dead values are dropped instead of being fed to the component.

        // ── Cover image (FileUpload uses the default Filament disk) ──
        $coverDisk = config('filament.default_filesystem_disk', 'public');
        $cover = $data['cover_image'] ?? null;
        if (is_string($cover) && trim($cover) !== '') {
            try {
                $coverPath = ProductResource::screenshotUrlToPath(trim($cover));
                $data['cover_image'] = ($coverPath !== null && $coverPath !== '' && Storage::disk($coverDisk)->exists($coverPath))
                    ? $coverPath
                    : null;
            } catch (\Throwable $e) {
                $data['cover_image'] = null;
            }
        } else {
            $data['cover_image'] = null;
        }

        // ── Screenshots (Repeater FileUpload uses the 'public' disk) ──
        try {
            $screenshots = $data['screenshots'] ?? [];
            if (is_string($screenshots)) {
                $screenshots = json_decode($screenshots, true);
            }
            $normalized = [];
            foreach ((array) $screenshots as $shot) {
                if (is_string($shot)) {
                    // Legacy format: plain URL string per entry
                    $shot = ['url' => $shot, 'caption' => ''];
                }
                if (!is_array($shot)) {
                    continue;
                }
                $path = ProductResource::screenshotUrlToPath((string) ($shot['url'] ?? ''));
                // Skip dead entries — a missing file would crash FileUpload hydrate
                if ($path === null || $path === '' || !Storage::disk('public')->exists($path)) {
                    continue;
                }
                $shot['url'] = $path;
                $shot['caption'] = (string) ($shot['caption'] ?? '');
                $normalized[] = $shot;
            }
            $data['screenshots'] = $normalized;
        } catch (\Throwable $e) {
            // Never let storage errors break the edit form
            $data['screenshots'] = [];
        }

        // Derive the free-product toggle from stored pricing tiers
        try {
            $tiers = $data['pricing_tiers'] ?? [];
            if (is_string($tiers)) {
                $tiers = json_decode($tiers, true);
            }
            $data['is_free_product'] = !empty(array_filter(
                (array) $tiers,
                fn ($t) => is_array($t) && (($t['type'] ?? '') === 'free')
            ));
        } catch (\Throwable $e) {
            $data['is_free_product'] = false;
        }

        return $data;
    }

    /**
     * Apply shared save rules (free toggle, trial fallback, readme_sections sync).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ProductResource::applyProductSaveRules($data);
    }

    /**
     * forceFill so non-fillable-but-real columns (e.g. license_protection_enabled)
     * are persisted; plain update() would silently drop them.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data)->save();

        return $record;
    }
}
