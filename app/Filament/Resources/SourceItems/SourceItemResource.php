<?php

namespace App\Filament\Resources\SourceItems;

use App\Filament\Resources\SourceItems\Pages;
use App\Models\SourceItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourceItemResource extends Resource
{
    protected static ?string $model =
        SourceItem::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-newspaper';

    protected static ?string $navigationLabel =
        'اخبار پیدا شده';

    protected static ?string $modelLabel =
        'خبر';

    protected static ?string $pluralModelLabel =
        'اخبار پیدا شده';

    protected static string|null|\UnitEnum $navigationGroup =
        'مانیتورینگ';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->limit(80)
                    ->wrap(),

                TextColumn::make('source.name')
                    ->label('منبع')
                    ->badge()
                    ->searchable(),

                TextColumn::make('matched_keyword')
                    ->label('کلمه کلیدی')
                    ->badge()
                    ->searchable(),

                TextColumn::make('matched_content')
                    ->label('پاراگراف مرتبط')
                    ->limit(100)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('تاریخ انتشار')->jalaliDateTime()
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('زمان دریافت')->jalaliDateTime()
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

            ])
            ->defaultSort(
                'created_at',
                'desc'
            )
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                Pages\ListSourceItems::route('/'),

            'view' =>
                Pages\ViewSourceItem::route('/{record}'),
        ];
    }
}
