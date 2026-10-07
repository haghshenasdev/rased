<?php

namespace App\Services\Monitoring;

use App\Models\SourceItem;
use Illuminate\Support\Carbon;

class SimilarityService
{
    public function findRepost(string $title, string $content, ?int $categoryId = null): ?array
    {
        $from = Carbon::now('Asia/Tehran')->subDays(2);

        $query = SourceItem::query()
            ->where('created_at', '>=', $from)
            ->where('is_repost', false)
            ->latest('id')
            ->limit(250);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $needle = $this->tokens($title . ' ' . $content);
        if (count($needle) < 5) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($query->get(['id', 'title', 'content', 'published_at']) as $item) {
            $candidate = $this->tokens(($item->title ?? '') . ' ' . ($item->content ?? ''));
            $score = $this->dice($needle, $candidate);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return ($best && $bestScore >= 0.84)
            ? ['item' => $best, 'percent' => round($bestScore * 100, 2)]
            : null;
    }

    private function tokens(string $text): array
    {
        $text = mb_strtolower(str_replace(['ي', 'ى', 'ك'], ['ی', 'ی', 'ک'], $text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($words, fn ($w) => mb_strlen($w) >= 2);
        return array_values(array_unique($words));
    }

    private function dice(array $a, array $b): float
    {
        if (!$a || !$b) return 0;
        $common = count(array_intersect($a, $b));
        return (2 * $common) / (count($a) + count($b));
    }
}
