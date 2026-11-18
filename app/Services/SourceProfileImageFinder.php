<?php

namespace App\Services;

use App\Models\Source;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use DOMDocument;
use DOMXPath;

class SourceProfileImageFinder
{
    public function findAndSave(Source $source): ?string
    {
        $pageUrl = $this->pageUrl($source);
        $response = Http::connectTimeout(5)
            ->timeout(12)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; RasedSourceIconFinder/1.0)',
                'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8',
            ])
            ->get($pageUrl);

        if (!$response->successful()) {
            throw new RuntimeException('صفحه منبع با موفقیت دریافت نشد.');
        }

        $html = substr($response->body(), 0, 2 * 1024 * 1024);
        $imageUrl = $source->type === 'eitaa'
            ? $this->eitaaProfileImage($html, $pageUrl)
            : $this->favicon($html, $pageUrl);

        if (!$imageUrl) {
            return null;
        }

        $source->forceFill([
            'profile_image_url' => $imageUrl,
            'profile_image_path' => null,
        ])->save();

        return $imageUrl;
    }

    private function pageUrl(Source $source): string
    {
        if ($source->type === 'eitaa') {
            $channel = trim((string) $source->identifier, " @/\t\n\r\0\x0B");
            if ($channel === '') {
                throw new RuntimeException('شناسه کانال ایتا برای این منبع ثبت نشده است.');
            }

            return 'https://eitaa.com/' . rawurlencode($channel);
        }

        $url = trim((string) $source->url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new RuntimeException('آدرس وب معتبری برای این منبع ثبت نشده است.');
        }

        $parts = parse_url($url);
        if (empty($parts['host'])) {
            throw new RuntimeException('دامنه منبع قابل تشخیص نیست.');
        }

        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . '/';
    }

    private function eitaaProfileImage(string $html, string $pageUrl): ?string
    {
        [$document, $xpath] = $this->parseHtml($html);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' etme_page_photo_image ') and contains(concat(' ', normalize-space(@class), ' '), ' bgcolor5 ')]//img[@src] | //img[contains(concat(' ', normalize-space(@class), ' '), ' etme_page_photo_image ') and contains(concat(' ', normalize-space(@class), ' '), ' bgcolor5 ') and @src]");
        foreach ($nodes ?: [] as $node) {
            $url = $this->absoluteUrl($node->getAttribute('src'), $pageUrl);
            if ($url) return $url;
        }

        // بعضی نسخه‌های صفحه، کلاس‌ها را روی عکس و بعضی روی ظرف عکس می‌گذارند.
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' etme_page_photo_image ') and contains(concat(' ', normalize-space(@class), ' '), ' bgcolor5 ')]//*[@src]");
        foreach ($nodes ?: [] as $node) {
            $url = $this->absoluteUrl($node->getAttribute('src'), $pageUrl);
            if ($url) return $url;
        }

        return null;
    }

    private function favicon(string $html, string $pageUrl): ?string
    {
        [, $xpath] = $this->parseHtml($html);
        $nodes = $xpath->query('//link[@href and (contains(translate(@rel,"ICON","icon"),"icon") or contains(translate(@rel,"APPLE","apple"),"apple"))]');
        $candidates = [];
        foreach ($nodes ?: [] as $node) {
            $url = $this->absoluteUrl($node->getAttribute('href'), $pageUrl);
            if ($url) $candidates[] = $url;
        }

        if ($candidates) return $candidates[0];

        $fallback = $this->absoluteUrl('/favicon.ico', $pageUrl);
        if (!$fallback) return null;
        try {
            $response = Http::connectTimeout(4)->timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; RasedSourceIconFinder/1.0)'])->get($fallback);
            if ($response->successful() && str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/')) {
                return $fallback;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function parseHtml(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [$document, new DOMXPath($document)];
    }

    private function absoluteUrl(string $path, string $baseUrl): ?string
    {
        $path = trim(html_entity_decode($path, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($path === '' || str_starts_with($path, 'data:')) return null;
        if (str_starts_with($path, '//')) $path = (string) parse_url($baseUrl, PHP_URL_SCHEME) . ':' . $path;
        elseif (!preg_match('~^https?://~i', $path)) {
            $parts = parse_url($baseUrl);
            if (empty($parts['host'])) return null;
            $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $path = str_starts_with($path, '/') ? $origin . $path : $origin . '/' . $path;
        }

        return filter_var($path, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $path
            : null;
    }
}
