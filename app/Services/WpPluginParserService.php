<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class WpPluginParserService
{
    /**
     * WP 头部标记 => 返回键名映射
     */
    protected const HEADER_MAP = [
        'Plugin Name'        => 'name',
        'Theme Name'         => 'name',
        'Version'            => 'version',
        'Author'             => 'author',
        'Author URI'         => 'author_profile',
        'Description'        => 'description',
        'Requires at least'  => 'requires_wp',
        'Tested up to'       => 'tested_up_to',
        'Requires PHP'       => 'requires_php',
        'Plugin URI'         => 'plugin_homepage',
        'Theme URI'          => 'plugin_homepage',
        'License'            => 'license',
        'License URI'        => 'license_uri',
        'Text Domain'        => 'text_domain',
        'Tags'               => 'wp_tags',
    ];

    /**
     * 解析 WP 插件/主题 ZIP 包，提取插件元数据
     *
     * @param UploadedFile $file ZIP 文件
     * @return array 提取的元数据
     * @throws \InvalidArgumentException 如果 ZIP 无效或找不到插件头
     */
    public function parse(UploadedFile $file): array
    {
        return $this->parseFromPath($file->getRealPath());
    }

    /**
     * 从 ZIP 中提取 readme.txt 原始内容（不解析，直接返回全文）
     *
     * @param UploadedFile $file ZIP 文件
     * @return string|null readme.txt 内容，未找到返回 null
     */
    public function extractReadmeContent(UploadedFile $file): ?string
    {
        return $this->extractReadmeFromPath($file->getRealPath());
    }

    /**
     * 从磁盘 ZIP 文件路径提取 readme.txt 原始内容
     *
     * @param string $filePath ZIP 文件绝对路径
     * @return string|null readme.txt 内容，未找到返回 null
     */
    public function extractReadmeFromPath(string $filePath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CHECKCONS) !== true) {
            return null;
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = $stat ? $stat['name'] : '';
                if (str_ends_with($entryName, '/')) continue;

                $basename = strtolower(basename($entryName));
                $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

                if ($basename === 'readme.txt' && $ext === 'txt') {
                    $content = $zip->getFromIndex($i);
                    return ($content !== false) ? $content : null;
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    /**
     * 从 ZIP 中解析 readme.txt，返回结构化段落数据
     *
     * @param UploadedFile $file ZIP 文件
     * @return array{readme_raw: string|null, sections: array}
     */
    public function parseAllSections(UploadedFile $file): array
    {
        $readmeRaw = $this->extractReadmeContent($file);

        if ($readmeRaw === null) {
            \Log::info('WpPluginParser parseAllSections: readme.txt not found in ZIP');
            return ['readme_raw' => null, 'readme_found' => false, 'sections' => []];
        }

        $parsed = $this->parseReadmeTxt($readmeRaw);

        \Log::info('WpPluginParser parseAllSections: installation len = ' . strlen($parsed['installation'] ?? '') . ', faq len = ' . strlen($parsed['faq'] ?? ''));

        return [
            'readme_raw' => $readmeRaw,
            'readme_found' => true,
            'sections' => [
                'description'  => $parsed['readme_description'] ?? '',
                'installation' => $parsed['installation'] ?? '',
                'faq'          => $parsed['faq'] ?? '',
                'changelog'    => $parsed['changelog'] ?? '',
                'screenshots'  => $parsed['screenshots_text'] ?? '',
            ],
        ];
    }

    /**
     * 解析已存储在磁盘上的 ZIP 文件路径
     *
     * @param string $filePath ZIP 文件绝对路径
     * @return array 提取的元数据
     * @throws \InvalidArgumentException 如果 ZIP 无效或找不到插件头
     */
    public function parseFromPath(string $filePath): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($filePath, ZipArchive::CHECKCONS);

        if ($result !== true) {
            throw new \InvalidArgumentException("无法打开 ZIP 文件，错误码：{$result}");
        }

        try {
            $meta = $this->extractHeaders($zip);
        } finally {
            $zip->close();
        }

        return $meta;
    }

    /**
     * 遍历 ZIP 内的 PHP 文件，提取第一个包含 Plugin Name 头部的元数据
     *
     * @param ZipArchive $zip
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function extractHeaders(ZipArchive $zip): array
    {
        $hasPhpFile = false;
        $readmeContent = null;
        $meta = [];
        $styleCssMeta = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = $stat ? $stat['name'] : '';

            // 跳过目录
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            $basename = strtolower(basename($entryName));

            // 查找 readme.txt（不区分大小写，根目录或一级子目录）
            if ($basename === 'readme.txt' && $ext === 'txt' && $readmeContent === null) {
                $readmeContent = $zip->getFromIndex($i);
                if ($readmeContent === false) {
                    $readmeContent = null;
                }
            }

            // 查找 style.css（主题元数据）
            if ($basename === 'style.css' && $ext === 'css' && empty($styleCssMeta)) {
                $stream = $zip->getStream($entryName);
                if ($stream !== false) {
                    $cssContent = fread($stream, 8192);
                    fclose($stream);
                    if ($cssContent !== false) {
                        $styleCssMeta = $this->parseHeaderBlock($cssContent);
                    }
                }
            }

            // 只处理 PHP 文件
            if ($ext !== 'php') {
                continue;
            }

            $hasPhpFile = true;

            // 只读取前 8KB（头部通常在文件开头）
            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                continue;
            }

            $content = fread($stream, 8192);
            fclose($stream);

            if ($content === false) {
                continue;
            }

            $parsed = $this->parseHeaderBlock($content);

            // 找到包含 Plugin Name 的文件即记录
            if (!empty($parsed['name']) && empty($meta)) {
                $meta = $parsed;
            }
        }

        if (empty($meta)) {
            // 主题类型：style.css 包含 Theme Name 时作为主数据源
            if (!empty($styleCssMeta) && !empty($styleCssMeta['name'])) {
                $meta = $styleCssMeta;
            } elseif (!$hasPhpFile) {
                throw new \InvalidArgumentException('ZIP 文件中未找到任何 PHP 文件。');
            } else {
                throw new \InvalidArgumentException('未在 ZIP 中找到包含 "Plugin Name:" 头部的 PHP 文件。');
            }
        }

        $result = $this->buildResult($meta);

        // 合并 style.css 元数据（不覆盖 PHP 头部已有的值）
        if (!empty($styleCssMeta)) {
            foreach (['license', 'license_uri', 'text_domain', 'wp_tags'] as $field) {
                if (empty($result[$field]) && !empty($styleCssMeta[$field])) {
                    $result[$field] = $styleCssMeta[$field];
                }
            }
        }

        // 解析 readme.txt 并合并（容错：解析失败不影响主流程）
        $readmeFound = ($readmeContent !== null);
        \Log::info('WpPluginParser: readme found = ' . ($readmeFound ? 'yes' : 'no'));

        $result['readme_found'] = $readmeFound;

        if ($readmeFound) {
            try {
                // 先解析再赋值，确保 readme_raw 和 sections 保持一致
                $readmeData = $this->parseReadmeTxt($readmeContent);

                \Log::info('WpPluginParser: readme sections = ' . json_encode(array_keys($readmeData)));
                \Log::info('WpPluginParser: installation len = ' . strlen($readmeData['installation'] ?? '') . ', faq len = ' . strlen($readmeData['faq'] ?? ''));

                // 存储 readme.txt 原始全文，用于前台 readme tab 展示
                $result['readme_raw'] = $readmeContent;

                // 构建 readme_sections 结构化数据（用于前台 readme tab 按段落展示）
                $result['readme_sections'] = [
                    'description'  => $readmeData['readme_description'] ?? '',
                    'installation' => $readmeData['installation'] ?? '',
                    'faq'          => $readmeData['faq'] ?? '',
                    'changelog'    => $readmeData['changelog'] ?? '',
                    'screenshots'  => $readmeData['screenshots_text'] ?? '',
                ];

                // 合并 readme 特有字段（不覆盖 PHP 头部已有的元数据）
                foreach (['installation', 'faq', 'changelog', 'screenshots_text', 'contributors'] as $field) {
                    if (isset($readmeData[$field]) && $readmeData[$field] !== '') {
                        $result[$field] = $readmeData[$field];
                    }
                }

                // readme Tags (array) -> wp_tags (comma-separated string)
                if (!empty($readmeData['tags']) && is_array($readmeData['tags']) && empty($result['wp_tags'])) {
                    $result['wp_tags'] = implode(', ', $readmeData['tags']);
                }

                // description 和 short_description 保持 PHP 头部的值（简洁，用于 AJAX 表单回填）
                // readme 的完整描述存放在 readme_sections['description']，用于前台 readme tab 展示

                // readme 头部元数据补充（仅在 PHP 头部为空时填充）
                foreach (['requires_wp', 'tested_up_to', 'requires_php'] as $field) {
                    if (empty($result[$field]) && !empty($readmeData[$field])) {
                        $result[$field] = $readmeData[$field];
                    }
                }
            } catch (\Throwable $e) {
                // readme 解析失败不阻止产品创建
                \Log::warning('WpPluginParser: readme parse failed', ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * 从 PHP 文件内容中解析 WP 头部字段
     *
     * 提取注释块中的键值对行，使用正则逐行解析，避免 parse_ini_string 对括号等特殊字符敏感。
     *
     * @param string $content
     * @return array
     */
    protected function parseHeaderBlock(string $content): array
    {
        // 提取第一个 PHP 注释块
        if (!preg_match('#/\*(.*?)\*/#s', $content, $block)) {
            return [];
        }

        // 逐行解析 "Key: Value" 格式，不依赖 parse_ini_string（括号/引号等会触发 INI 语法错误）
        $lines = explode("\n", $block[1]);
        $meta = [];

        foreach ($lines as $line) {
            // 去除行首的 * 和空白
            $line = preg_replace('/^\s*\*?\s*/', '', $line);

            // 匹配 "Key: Value" 格式（Key 中允许空格，如 "Plugin Name"）
            if (preg_match('/^([A-Za-z][A-Za-z0-9 ]*?):\s*(.*)$/s', $line, $m)) {
                $key = trim($m[1]);
                $value = trim($m[2]);
                $mappedKey = self::HEADER_MAP[$key] ?? null;
                if ($mappedKey !== null) {
                    $meta[$mappedKey] = $value;
                }
            }
        }

        return $meta;
    }

    /**
     * 构建最终返回结果，包含 short_description
     *
     * @param array $meta
     * @return array
     */
    protected function buildResult(array $meta): array
    {
        $description = $meta['description'] ?? '';

        // 生成 short_description：前 255 字符，在单词边界处截断
        if (mb_strlen($description) <= 255) {
            $shortDescription = $description;
        } else {
            $shortDescription = mb_substr($description, 0, 255);
            // 在最后一个空格处截断，避免截断单词
            $lastSpace = mb_strrpos($shortDescription, ' ');
            if ($lastSpace !== false) {
                $shortDescription = mb_substr($shortDescription, 0, $lastSpace);
            }
        }

        $result = [
            'name'              => $meta['name'] ?? '',
            'version'           => $meta['version'] ?? '',
            'author'            => $meta['author'] ?? '',
            'author_profile'    => $meta['author_profile'] ?? '',
            'description'       => $description,
            'requires_wp'       => $meta['requires_wp'] ?? '',
            'tested_up_to'      => $meta['tested_up_to'] ?? '',
            'requires_php'      => $meta['requires_php'] ?? '',
            'plugin_homepage'   => $meta['plugin_homepage'] ?? '',
            'short_description' => $shortDescription,
            'license'           => $meta['license'] ?? '',
            'license_uri'       => $meta['license_uri'] ?? '',
            'text_domain'       => $meta['text_domain'] ?? '',
            'wp_tags'           => $meta['wp_tags'] ?? '',
        ];

        return $result;
    }

    /**
     * Parse WordPress readme.txt from ZIP archive.
     *
     * @param string $readmeContent Raw readme.txt content
     * @return array Parsed sections
     */
    private function parseReadmeTxt(string $readmeContent): array
    {
        $result = [
            'readme_description' => '',
            'installation'       => '',
            'faq'                => '',
            'changelog'          => '',
            'screenshots_text'   => '',
        ];

        // Normalize line endings
        $content = str_replace("\r\n", "\n", $readmeContent);
        $content = str_replace("\r", "\n", $content);

        // Split by == Section == pattern (but not === Title === lines)
        // (?!=) negative lookahead ensures we match exactly == and not ===
        $sections = preg_split('/^==(?!=)\s*(.+?)\s*==(?!=)/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        // $sections[0] = header (before first ==), then alternating: title, content, title, content...
        for ($i = 1; $i < count($sections); $i += 2) {
            $title = strtolower(trim($sections[$i] ?? ''));
            $body = trim($sections[$i + 1] ?? '');

            switch ($title) {
                case 'description':
                    $result['readme_description'] = $body;
                    break;
                case 'installation':
                    $result['installation'] = $body;
                    break;
                case 'frequently asked questions':
                case 'faq':
                    $result['faq'] = $body;
                    break;
                case 'changelog':
                    $result['changelog'] = $body;
                    break;
                case 'screenshots':
                    $result['screenshots_text'] = $body;
                    break;
            }
        }

        // Extract header metadata (Contributors, Tags, Requires, etc.)
        $header = $sections[0] ?? '';
        if (preg_match('/^Contributors:\s*(.+)$/mi', $header, $m)) {
            $result['contributors'] = trim($m[1]);
        }
        if (preg_match('/^Tags:\s*(.+)$/mi', $header, $m)) {
            $result['tags'] = array_map('trim', explode(',', $m[1]));
        }
        if (preg_match('/^Requires at least:\s*(.+)$/mi', $header, $m)) {
            $result['requires_wp'] = trim($m[1]);
        }
        if (preg_match('/^Tested up to:\s*(.+)$/mi', $header, $m)) {
            $result['tested_up_to'] = trim($m[1]);
        }
        if (preg_match('/^Requires PHP:\s*(.+)$/mi', $header, $m)) {
            $result['requires_php'] = trim($m[1]);
        }

        return $result;
    }
}
