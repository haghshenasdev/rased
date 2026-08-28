<?php

namespace App\Services\Monitoring;

use App\Models\Keyword;

class KeywordMatcher
{
    /**
     * @return array{
     *     keyword: ?Keyword,
     *     paragraph: ?string
     * }
     */
    public function match(string $content): array
    {
        $keywords = Keyword::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        if ($keywords->isEmpty()) {
            return [
                'keyword' => null,
                'paragraph' => null,
            ];
        }

        foreach ($keywords as $keyword) {

            $word = trim($keyword->word);

            if ($word === '') {
                continue;
            }

            $paragraph = $this->findParagraph(
                $content,
                $word
            );

            if ($paragraph !== null) {
                return [
                    'keyword' => $keyword,
                    'paragraph' => $paragraph,
                ];
            }
        }

        return [
            'keyword' => null,
            'paragraph' => null,
        ];
    }

    protected function findParagraph(
        string $content,
        string $keyword
    ): ?string {

        $paragraphs = preg_split(
            "/\R\s*\R/u",
            $content
        );

        if (!$paragraphs) {
            $paragraphs = [$content];
        }

        foreach ($paragraphs as $paragraph) {

            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (
                mb_stripos(
                    $this->normalize($paragraph),
                    $this->normalize($keyword)
                ) !== false
            ) {
                return $paragraph;
            }
        }

        /*
         * اگر متن پاراگراف‌بندی نشده بود،
         * کل متن را بررسی می‌کنیم.
         */
        if (
            mb_stripos(
                $this->normalize($content),
                $this->normalize($keyword)
            ) !== false
        ) {
            return trim($content);
        }

        return null;
    }

    /**
     * نرمال‌سازی متن فارسی برای جستجوی Keyword
     *
     * مثال:
     *
     * حاجی دلیگانی
     * حاجی‌دلیگانی
     * حاجیدلیگانی
     *
     * هر سه به شکل زیر تبدیل می‌شوند:
     *
     * حاجیدلیگانی
     */
    protected function normalize(string $text): string
    {
        // حروف را کوچک می‌کنیم
        $text = mb_strtolower($text, 'UTF-8');

        // یکسان‌سازی حروف عربی و فارسی
        $text = str_replace(
            ['ي', 'ى', 'ك'],
            ['ی', 'ی', 'ک'],
            $text
        );

        /*
         * حذف نیم‌فاصله
         * Unicode: U+200C
         */
        $text = str_replace(
            "\u{200C}",
            '',
            $text
        );

        /*
         * حذف فاصله معمولی
         *
         * بنابراین:
         *
         * حاجی دلیگانی
         * حاجی‌دلیگانی
         * حاجیدلیگانی
         *
         * همگی به:
         *
         * حاجیدلیگانی
         *
         * تبدیل می‌شوند.
         */
        $text = preg_replace(
            '/\s+/u',
            '',
            $text
        );

        return $text ?? '';
    }
}
