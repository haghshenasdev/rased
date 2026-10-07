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

    public function sendPhotoByUrl($chatId, string $photoUrl, ?string $caption = null, ?array $keyboard = null)
    {
        $data = ['chat_id' => $chatId, 'photo' => $photoUrl];
        if ($caption !== null) $data['caption'] = $caption;
        if ($keyboard) $data['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        return $this->request('sendPhoto', $data);
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
