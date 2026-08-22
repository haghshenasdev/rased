<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BaleBotService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? env('BALE_BOT_TOKEN');
        $this->baseUrl = "https://tapi.bale.ai/bot{$this->token}/";
    }

    /**
     * درخواست عمومی به API
     */
    private function request(string $method, array $data = [])
    {
        $response = Http::post($this->baseUrl . $method, $data);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * ارسال پیام
     */
    public function sendMessage($chatId, string $text, array $options = [])
    {
        return $this->request('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options));
    }

    /**
     * ویرایش پیام
     */
    public function editMessage(
        $chatId,
        $messageId,
        string $text,
        ?array $keyboard = null
    ) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];

        if ($keyboard) {
            $data['reply_markup'] = json_encode(
                $keyboard,
                JSON_UNESCAPED_UNICODE
            );
        }

        return $this->request('editMessageText', $data);
    }

    /**
     * حذف پیام
     */
    public function deleteMessage($chatId, $messageId)
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * ارسال فایل از URL
     */
    public function sendDocumentByUrl(
        $chatId,
        string $fileUrl,
        ?string $caption = null
    ) {
        $data = [
            'chat_id' => $chatId,
            'document' => $fileUrl,
        ];

        if ($caption) {
            $data['caption'] = $caption;
        }

        return $this->request('sendDocument', $data);
    }

    /**
     * ارسال فایل از محتوا
     */
    public function sendDocument(
        $chatId,
        $content,
        string $filename,
        ?string $caption = null
    ) {
        return Http::attach(
            'document',
            $content,
            $filename
        )->post($this->baseUrl . 'sendDocument', [
            'chat_id' => $chatId,
            'caption' => $caption,
        ]);
    }

    /**
     * کیبورد معمولی
     */
    public function replyKeyboard(array $buttons)
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * حذف کیبورد
     */
    public function removeKeyboard()
    {
        return [
            'remove_keyboard' => true,
        ];
    }

    /**
     * کیبورد اینلاین
     */
    public function inlineKeyboard(array $buttons)
    {
        return [
            'inline_keyboard' => $buttons,
        ];
    }

    /**
     * دکمه اینلاین
     */
    public function button(
        string $text,
        string $callback
    ) {
        return [
            'text' => $text,
            'callback_data' => $callback,
        ];
    }

    /**
     * ارسال پیام با کیبورد
     */
    public function sendWithKeyboard(
        $chatId,
        string $text,
        array $keyboard
    ) {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode(
                $keyboard,
                JSON_UNESCAPED_UNICODE
            )
        ]);
    }

    /**
     * دریافت فایل
     */
    public function getFileUrl(string $filePath)
    {
        return "https://tapi.bale.ai/file/bot{$this->token}/{$filePath}";
    }

    public function getFile(string $filePath)
    {
        return file_get_contents(
            $this->getFileUrl($filePath)
        );
    }
}
