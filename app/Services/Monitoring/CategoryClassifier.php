<?php

namespace App\Services\Monitoring;

use App\Models\Category;
use App\Models\Source;

class CategoryClassifier
{
    public function classify(Source $source, string $text): ?Category
    {
        if (!$source->auto_categorize) {
            return $source->default_category_id
                ? Category::find($source->default_category_id)
                : null;
        }

        if ($source->default_category_id && empty(Category::find($source->default_category_id)?->keywords)) {
            return Category::find($source->default_category_id);
        }

        $categories = Category::query()->where('is_active', true)->get();
        $normalizedText = $this->normalize($text);
        $best = null;
        $bestScore = 0;

        foreach ($categories as $category) {
            $score = 0;
            foreach (($category->keywords ?? []) as $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword !== '' && mb_stripos($normalizedText, $this->normalize($keyword)) !== false) {
                    $score += max(1, mb_strlen($keyword));
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $category;
            }
        }

        return $best ?: ($source->default_category_id ? Category::find($source->default_category_id) : null);
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(str_replace(['ي', 'ى', 'ك', 'ۀ', 'ة'], ['ی', 'ی', 'ک', 'ه', 'ه'], $text));
    }
}
