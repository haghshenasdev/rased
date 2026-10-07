<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class BaleBotService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? env('BALE_BOT_TOKEN');
        $this->baseUrl = "https://tapi.bale.ai/bot{$this->token}/";
    }

    private function request(string $method, array $data = []): array
    {
        try {
            $response = Http::timeout(25)->connectTimeout(10)->post($this->baseUrl . $method, $data);
            $json = $response->json();

            if (is_array($json)) {
                $json['_http_status'] = $response->status();
                if (!$response->successful() && !array_key_exists('_http_body', $json)) {
                    $json['_http_body'] = mb_substr($response->body(), 0, 4000);
                }
                return $json;
            }

            return [
                'ok' => false,
                '_http_status' => $response->status(),
                '_http_body' => mb_substr($response->body(), 0, 4000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                '_http_status' => null,
                '_exception' => get_class($e),
                '_error' => $e->getMessage(),
            ];
        }
    }

    public function sendMessage($chatId, string $text, array $options = [])
    {
        return $this->request('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options));
    }

    public function editMessage($chatId, $messageId, string $text, ?array $keyboard = null)
    {
        $data = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text];
        if ($keyboard) $data['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        return $this->request('editMessageText', $data);
    }

    public function deleteMessage($chatId, $messageId)
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * عکس اصلی خبر در دیتابیس همان URL دائمی باقی می‌ماند، اما برای بله:
     * 1) عکس را از URL اصلی دانلود می‌کنیم.
     * 2) موقتاً داخل public/bale-temp قرار می‌دهیم.
     * 3) یک URL عمومی و مستقیم به بله می‌دهیم.
     * 4) بلافاصله بعد از پاسخ API فایل موقت را حذف می‌کنیم.
     *
     * این کار مشکل "failed to get HTTP URL content" را برای URLهایی که
     * بله نمی‌تواند مستقیماً از منبع اصلی دریافت کند، برطرف می‌کند.
     */
    public function sendPhotoByUrl($chatId, string $photoUrl, ?string $caption = null, ?array $keyboard = null): array
    {
        $tempPath = null;
        $tempUrl = null;

        try {
            $download = Http::timeout(35)
                ->connectTimeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 Rased/1.0',
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                ])
                ->get($photoUrl);

            if (!$download->successful()) {
                return [
                    'ok' => false,
                    '_http_status' => $download->status(),
                    '_error' => 'دانلود تصویر از URL اصلی ناموفق بود.',
                    '_http_body' => mb_substr($download->body(), 0, 2000),
                    '_photo_source_url' => $photoUrl,
                    '_temporary_photo_url' => null,
                ];
            }

            $contents = $download->body();

            if ($contents === '') {
                return [
                    'ok' => false,
                    '_http_status' => $download->status(),
                    '_error' => 'فایل تصویر خالی است.',
                    '_photo_source_url' => $photoUrl,
                    '_temporary_photo_url' => null,
                ];
            }

            $imageInfo = @getimagesizefromstring($contents);

            if ($imageInfo === false || empty($imageInfo['mime'])) {
                return [
                    'ok' => false,
                    '_http_status' => $download->status(),
                    '_error' => 'فایل دریافت‌شده تصویر معتبر نیست یا MIME آن قابل تشخیص نیست.',
                    '_content_type' => $download->header('Content-Type'),
                    '_photo_source_url' => $photoUrl,
                    '_temporary_photo_url' => null,
                ];
            }

            $mime = strtolower((string) $imageInfo['mime']);
            $extension = match ($mime) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/bmp' => 'bmp',
                default => null,
            };

            if ($extension === null) {
                return [
                    'ok' => false,
                    '_http_status' => $download->status(),
                    '_error' => "فرمت تصویر برای ارسال به بله پشتیبانی نشده است: {$mime}",
                    '_content_type' => $download->header('Content-Type'),
                    '_photo_source_url' => $photoUrl,
                    '_temporary_photo_url' => null,
                ];
            }

            $directory = public_path('bale-temp');

            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                return [
                    'ok' => false,
                    '_http_status' => null,
                    '_error' => 'امکان ساخت پوشه موقت public/bale-temp وجود ندارد.',
                    '_photo_source_url' => $photoUrl,
                    '_temporary_photo_url' => null,
                ];
            }

            // پاک‌سازی فایل‌های قدیمی تا فایل موقت روی هاست جمع نشود.
            $this->cleanupTemporaryBalePhotos($directory);

            $filename = 'bale_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $tempPath = $directory . DIRECTORY_SEPARATOR . $filename;

            if (@file_put_contents($tempPath, $contents) === false) {
                return [
                    'ok' => false,
                    '_http_status' => null,
                    '_error' => 'امکان ذخیره فایل موقت تصویر روی هاست وجود ندارد.',
                    '_photo_source_url' => $photoUrl,
                    '_temporary_photo_url' => null,
                ];
            }

            $baseUrl = rtrim((string) config('app.url'), '/');
            if ($baseUrl === '') {
                $baseUrl = rtrim((string) env('APP_URL'), '/');
            }

            $tempUrl = $baseUrl . '/bale-temp/' . rawurlencode($filename);

            $data = [
                'chat_id' => $chatId,
                'photo' => $tempUrl,
            ];

            if ($caption !== null) $data['caption'] = $caption;
            if ($keyboard) $data['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);

            $result = $this->request('sendPhoto', $data);

            // اطلاعات تشخیصی را نگه می‌داریم ولی خود فایل بعد از درخواست حذف می‌شود.
            $result['_photo_source_url'] = $photoUrl;
            $result['_temporary_photo_url'] = $tempUrl;
            $result['_temporary_photo_mime'] = $mime;
            $result['_temporary_photo_size'] = strlen($contents);

            return $result;
        } catch (Throwable $e) {
            return [
                'ok' => false,
                '_http_status' => null,
                '_exception' => get_class($e),
                '_error' => $e->getMessage(),
                '_photo_source_url' => $photoUrl,
                '_temporary_photo_url' => $tempUrl,
            ];
        } finally {
            if ($tempPath && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * فایل‌های موقت قدیمی‌تر از 30 دقیقه را پاک می‌کند.
     * این بخش برای مواقعی است که PHP/queue قبل از finally متوقف شده باشد.
     */
    private function cleanupTemporaryBalePhotos(string $directory): void
    {
        $files = @glob($directory . DIRECTORY_SEPARATOR . 'bale_*');
        if (!$files) {
            return;
        }

        $threshold = time() - (30 * 60);

        foreach ($files as $file) {
            if (is_file($file) && @filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    public function sendDocumentByUrl($chatId, string $fileUrl, ?string $caption = null)
    {
        $data = ['chat_id' => $chatId, 'document' => $fileUrl];
        if ($caption) $data['caption'] = $caption;
        return $this->request('sendDocument', $data);
    }

    public function sendDocument($chatId, $content, string $filename, ?string $caption = null)
    {
        try {
            $response = Http::timeout(30)->attach('document', $content, $filename)
                ->post($this->baseUrl . 'sendDocument', ['chat_id' => $chatId, 'caption' => $caption]);
            $json = $response->json();
            return is_array($json) ? array_merge($json, ['_http_status' => $response->status()]) : [
                'ok' => false, '_http_status' => $response->status(), '_http_body' => mb_substr($response->body(), 0, 4000),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, '_exception' => get_class($e), '_error' => $e->getMessage()];
        }
    }

    public function replyKeyboard(array $buttons) { return ['keyboard' => $buttons, 'resize_keyboard' => true, 'one_time_keyboard' => false]; }
    public function removeKeyboard() { return ['remove_keyboard' => true]; }
    public function inlineKeyboard(array $buttons) { return ['inline_keyboard' => $buttons]; }
    public function button(string $text, string $callback): array { return ['text' => $text, 'callback_data' => $callback]; }
    public function urlButton(string $text, string $url): array { return ['text' => $text, 'url' => $url]; }
    public function sendWithKeyboard($chatId, string $text, array $keyboard) { return $this->sendMessage($chatId, $text, ['reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)]); }
    public function getFileUrl(string $filePath) { return "https://tapi.bale.ai/file/bot{$this->token}/{$filePath}"; }
    public function getFile(string $filePath) { return file_get_contents($this->getFileUrl($filePath)); }
    public function setWebhook(string $url) { return $this->request('setWebhook', ['url' => $url]); }
    public function deleteWebhook() { return $this->request('deleteWebhook'); }
    public function getWebhookInfo() { return $this->request('getWebhookInfo'); }
}
