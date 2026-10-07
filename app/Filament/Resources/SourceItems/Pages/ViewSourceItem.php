<?php

namespace App\Filament\Resources\SourceItems\Pages;

use App\Filament\Resources\SourceItems\SourceItemResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;

class ViewSourceItem extends ViewRecord
{
    protected static string $resource =
        SourceItemResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextEntry::make('title')
                    ->label('عنوان')
                    ->columnSpanFull(),

                TextEntry::make('source.name')
                    ->label('منبع'),

                TextEntry::make('matched_keyword')
                    ->label('کلمه کلیدی')
                    ->badge(),

                TextEntry::make('category.name')
                    ->label('دسته‌بندی')
                    ->badge(),

                TextEntry::make('similarity_percent')
                    ->label('درصد تشابه')
                    ->suffix('%')
                    ->visible(fn ($record) => (bool)$record->is_repost),

                ImageEntry::make('featured_image_url')
                    ->label('تصویر شاخص')
                    ->visible(fn ($record) => filled($record->featured_image_url))
                    ->columnSpanFull(),

                TextEntry::make('published_at')
                    ->label('تاریخ انتشار')
                    ->dateTime('Y/m/d H:i'),

                TextEntry::make('url')
                    ->label('لینک خبر')
                    ->url(
                        fn ($record) =>
                        $record->url
                    )
                    ->openUrlInNewTab()
                    ->columnSpanFull(),

                TextEntry::make('matched_content')
                    ->label('پاراگراف مرتبط')
                    ->columnSpanFull()
                    ->prose(),

                TextEntry::make('content')
                    ->label('متن کامل')
                    ->columnSpanFull()
                    ->prose(),

            ]);
    }
}
