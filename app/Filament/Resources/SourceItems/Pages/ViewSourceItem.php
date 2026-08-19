<?php

namespace App\Filament\Resources\SourceItems\Pages;

use App\Filament\Resources\SourceItems\SourceItemResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;

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
