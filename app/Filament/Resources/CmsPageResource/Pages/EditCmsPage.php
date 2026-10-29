<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Filament\Resources\CmsPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * Strip empty paragraphs before saving to avoid
     * leading blank lines that push images to second row.
     */
    protected function beforeSave(): void
    {
        $content = $this->data['content'] ?? '';
        if (!empty($content)) {
            $this->data['content'] = $this->cleanContent($content);
        }
    }

    protected function afterStateHydrated(): void
    {
        $content = $this->data['content'] ?? '';
        if (!empty($content)) {
            $this->data['content'] = $this->cleanContent($content);
        }
    }

    private function cleanContent(string $html): string
    {
        /* Remove empty <p> tags */
        $html = preg_replace('/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $html);

        /* Remove attachFiles metadata: "filename.ext 123.45 KB/MB" */
        $html = preg_replace('/<p[^>]*>\s*[\w\-\.]+\.(png|jpg|jpeg|gif|webp|svg|mp4|webm|pdf|zip)\s+[\d.]+\s*(KB|MB|GB)\s*<\/p>/i', '', $html);

        /* Remove <figcaption> with file metadata */
        $html = preg_replace('/<figcaption[^>]*>\s*[\w\-\.]+\.(png|jpg|jpeg|gif|webp|svg|mp4|webm|pdf|zip)\s+[\d.]+\s*(KB|MB|GB)\s*<\/figcaption>/i', '', $html);

        /* Remove bare text with file metadata (not in tags) */
        $html = preg_replace('/(?<=>)\s*[\w\-\.]+\.(png|jpg|jpeg|gif|webp|svg|mp4|webm|pdf|zip)\s+[\d.]+\s*(KB|MB|GB)\s*(?=<)/i', '', $html);

        /* Remove empty <figure> tags left behind */
        $html = preg_replace('/<figure[^>]*>\s*<\/figure>/i', '', $html);

        return trim($html);
    }

    /**
     * Handle file upload from TinyMCE editor.
     * Receives a base64 data URL, saves to storage, returns public URL.
     */
    public function uploadContentFile(string $dataUrl, string $filename, string $mimeType): string
    {
        // Extract base64 content
        if (preg_match('/^data:[^;]+;base64,(.+)$/s', $dataUrl, $matches)) {
            $fileData = base64_decode($matches[1]);
        } else {
            throw new \InvalidArgumentException('Invalid data URL format.');
        }

        // Generate unique filename
        $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: $this->guessExtension($mimeType);
        $path = 'cms/' . date('Y/m') . '/' . uniqid() . '.' . $ext;

        // Save to public disk
        Storage::disk('public')->put($path, $fileData);

        return Storage::disk('public')->url($path);
    }

    private function guessExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => 'bin',
        };
    }
}
