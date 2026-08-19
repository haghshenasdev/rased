<?php

namespace App\Filament\Widgets;

use App\Models\SourceItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestNews extends BaseWidget
{
    protected static ?string $heading =
        'آخرین اخبار پیدا شده';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SourceItem::query()
                    ->with('source')
                    ->latest('created_at')
            )
            ->columns([

                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->limit(70)
                    ->wrap(),

                Tables\Columns\TextColumn::make('source.name')
                    ->label('منبع')
                    ->badge(),

                Tables\Columns\TextColumn::make(
                    'matched_keyword'
                )
                    ->label('کلمه کلیدی')
                    ->badge(),

                Tables\Columns\TextColumn::make(
                    'published_at'
                )
                    ->label('انتشار')
                    ->dateTime('Y/m/d H:i'),

                Tables\Columns\TextColumn::make(
                    'created_at'
                )
                    ->label('دریافت')
                    ->since(),

            ])
            ->recordActions([
                \Filament\Actions\Action::make('open')
                    ->label('مشاهده خبر اصلی')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(
                        fn ($record) => $record->url
                    )
                    ->openUrlInNewTab(),
            ])
            ->paginated([10, 25, 50]);
    }
}
