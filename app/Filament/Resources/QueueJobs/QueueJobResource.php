<?php

namespace App\Filament\Resources\QueueJobs;

use App\Filament\Resources\QueueJobs\Pages;
use App\Models\QueueJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class QueueJobResource extends Resource
{
    protected static ?string $model = QueueJob::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-queue-list';

    protected static ?string $navigationLabel =
        'صف پردازش';

    protected static ?string $modelLabel =
        'Job';

    protected static ?string $pluralModelLabel =
        'صف پردازش';

    protected static string|null|\UnitEnum $navigationGroup =
        'سیستم';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('queue')
                    ->label('صف')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('تلاش')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('در حال اجرا')->jalaliDateTime()
                    ->dateTime('Y/m/d H:i:s')
                    ->placeholder('در انتظار'),

                Tables\Columns\TextColumn::make('available_at')
                    ->label('قابل اجرا')->jalaliDateTime()
                    ->dateTime('Y/m/d H:i:s'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('ایجاد')->jalaliDateTime()
                    ->dateTime('Y/m/d H:i:s'),

            ])
            ->defaultSort('id', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQueueJobs::route('/'),
        ];
    }
}
