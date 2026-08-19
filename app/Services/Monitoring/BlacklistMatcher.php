<?php

namespace App\Services\Monitoring;

use App\Models\BlacklistKeyword;

class BlacklistMatcher
{
    public function hasMatch(string $content): bool
    {
        $blacklists = BlacklistKeyword::query()
            ->where('is_active', true)
            ->get();

        foreach ($blacklists as $blacklist) {

            $word = trim($blacklist->word);

            if ($word === '') {
                continue;
            }

            if (
                mb_stripos(
                    $this->normalize($content),
                    $this->normalize($word)
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(string $text): string
    {
        return str_replace(
            ['ي', 'ى', 'ك'],
            ['ی', 'ی', 'ک'],
            mb_strtolower($text)
        );
    }
}
