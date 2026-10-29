<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Machine translation service (Baidu Translate API).
 *
 * Silently falls back to the original text when credentials are not
 * configured or the API call fails. Never throws exceptions.
 */
class MachineTranslateService
{
    protected const API_URL = 'https://fanyi-api.baidu.com/api/trans/vip/translate';

    /** Maximum bytes per API request chunk. */
    protected const CHUNK_MAX_BYTES = 5000;

    /**
     * Translate text between languages.
     *
     * All-or-nothing: if ANY chunk fails (API error, exception, missing
     * trans_result or empty result), the whole translation returns null.
     * A partially concatenated result is NEVER returned, so incomplete
     * translations can never be persisted.
     *
     * @param string $text Source text
     * @param string $from Source language ('en' or 'zh')
     * @param string $to   Target language ('en' or 'zh')
     * @return string|null Translated text, or null when unavailable
     */
    public function translate(string $text, string $from, string $to): ?string
    {
        $appid = config('services.translate.appid');
        $secret = config('services.translate.secret');

        // Not configured → silent fallback to original text
        if (empty($appid) || empty($secret)) {
            return null;
        }

        if (trim($text) === '') {
            return null;
        }

        // No-op when source and target are identical
        if ($from === $to) {
            return $text;
        }

        try {
            $chunks = $this->chunkText($text);
            $results = [];

            foreach ($chunks as $index => $chunk) {
                // Throttle QPS between consecutive chunks
                if ($index > 0) {
                    usleep(1000000);
                }

                $errorCode = null;
                $translated = $this->translateChunk($chunk, $from, $to, $appid, $secret, $errorCode);

                // 54003 = QPS rate limit: wait ~1.5s and retry the chunk once
                if ($translated === null && $errorCode === 54003) {
                    Log::warning('Machine translation chunk rate limited (54003), retrying', [
                        'chunk_index' => $index,
                        'retry'       => 1,
                    ]);
                    usleep(1500000);
                    $errorCode = null;
                    $translated = $this->translateChunk($chunk, $from, $to, $appid, $secret, $errorCode);
                }

                if ($translated === null) {
                    // Any single chunk failure aborts the whole translation
                    Log::warning('Machine translation aborted: chunk failed', [
                        'chunk_index'  => $index,
                        'chunk_count'  => count($chunks),
                        'error_code'   => $errorCode,
                    ]);
                    return null;
                }
                $results[] = $translated;
            }

            $result = implode("\n\n", $results);
            if (trim($result) === '') {
                return null;
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Machine translation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Detect the dominant language of a text.
     *
     * @return string 'zh' when Chinese characters exceed 30%, otherwise 'en'
     */
    public static function detectLanguage(string $text): string
    {
        $length = mb_strlen($text);
        if ($length === 0) {
            return 'en';
        }

        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text);

        return ($chineseCount / $length) > 0.3 ? 'zh' : 'en';
    }

    /**
     * Whether the translation service is configured with credentials.
     */
    public function isConfigured(): bool
    {
        return !empty(config('services.translate.appid'))
            && !empty(config('services.translate.secret'));
    }

    /**
     * Split text into paragraphs and greedily merge them into chunks
     * of at most CHUNK_MAX_BYTES bytes each.
     *
     * @return string[]
     */
    protected function chunkText(string $text): array
    {
        $paragraphs = preg_split('/\n\n+/', $text) ?: [$text];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $candidate = $current === '' ? $paragraph : $current . "\n" . $paragraph;

            if ($current !== '' && strlen($candidate) > self::CHUNK_MAX_BYTES) {
                $chunks[] = $current;
                $current = $paragraph;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks ?: [$text];
    }

    /**
     * Send a single chunk to the Baidu Translate API.
     *
     * @param int|null $errorCode Receives the API error_code when the call
     *                            fails with an API-level error, null otherwise.
     */
    protected function translateChunk(string $chunk, string $from, string $to, string $appid, string $secret, ?int &$errorCode = null): ?string
    {
        $salt = random_int(10000, 99999);
        $sign = md5($appid . $chunk . $salt . $secret);

        $response = Http::asForm()->timeout(10)->post(self::API_URL, [
            'q'     => $chunk,
            'from'  => $from,
            'to'    => $to,
            'appid' => $appid,
            'salt'  => $salt,
            'sign'  => $sign,
        ]);

        // HTTP-level failure (timeout, 5xx, connection error) → treat as failure
        if (!$response->successful()) {
            Log::warning('Machine translation HTTP error', ['status' => $response->status()]);
            return null;
        }

        $data = $response->json();

        if (!is_array($data) || isset($data['error_code']) || empty($data['trans_result']) || !is_array($data['trans_result'])) {
            $errorCode = isset($data['error_code']) ? (int) $data['error_code'] : null;
            Log::warning('Machine translation API error', [
                'error_code' => $errorCode,
                'error_msg'  => $data['error_msg'] ?? null,
            ]);
            return null;
        }

        $parts = array_map(
            static fn (array $item) => (string) ($item['dst'] ?? ''),
            array_values($data['trans_result'])
        );

        $result = implode("\n", $parts);
        if (trim($result) === '') {
            // Empty trans_result payload → treat as failure, never return partial text
            Log::warning('Machine translation returned empty trans_result');
            return null;
        }

        return $result;
    }
}
