<?php

namespace App\Filament\Widgets;

use App\Models\Keyword;
use App\Models\Source;
use App\Models\SourceItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonitoringStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(
                'منابع فعال',
                Source::where('is_active', true)->count()
            )
                ->description('منابع در حال رصد')
                ->icon('heroicon-o-rss'),

            Stat::make(
                'کلمات کلیدی',
                Keyword::where('is_active', true)->count()
            )
                ->description('کلمات فعال')
                ->icon('heroicon-o-magnifying-glass'),

            Stat::make(
                'کل اخبار',
                SourceItem::count()
            )
                ->description('اخبار ذخیره شده')
                ->icon('heroicon-o-newspaper'),

            Stat::make(
                'اخبار امروز',
                SourceItem::whereDate(
                    'created_at',
                    today()
                )->count()
            )
                ->description('اخبار پیدا شده امروز')
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
