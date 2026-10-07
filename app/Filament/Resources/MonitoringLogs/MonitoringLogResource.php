<?php

namespace App\Filament\Resources\MonitoringLogs;

use App\Filament\Resources\MonitoringLogs\Pages;
use App\Models\MonitoringLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonitoringLogResource extends Resource
{
    protected static ?string $model = MonitoringLog::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';
    protected static ?string $navigationLabel = 'کنسول لاگ‌ها';
    protected static ?string $modelLabel = 'لاگ';
    protected static ?string $pluralModelLabel = 'لاگ‌های مانیتورینگ';
    protected static string|null|\UnitEnum $navigationGroup = 'مانیتورینگ';
    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('تاریخ')
                    ->jalaliDateTime('Y/m/d H:i:s')->sortable(),
                TextColumn::make('source.name')->label('منبع')->searchable()->sortable(),
                TextColumn::make('operation_label')->label('عملیات')->badge()
                    ->color(fn ($state) => $state === 'ارسال بله' ? 'warning' : 'info'),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->color(fn ($state) => $state === 'failed' ? 'danger' : 'success'),
                TextColumn::make('error_type')->label('نوع')->searchable()->toggleable(),
                TextColumn::make('message')->label('پیام')->limit(100)->wrap()->tooltip(fn ($record) => $record->message),
                TextColumn::make('context.title')->label('خبر')->limit(60)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('source_id')->label('منبع')->relationship('source', 'name')->searchable(),
                SelectFilter::make('status')->label('وضعیت')->options([
                    'failed' => 'خطا',
                    'success' => 'موفق',
                ]),
                Filter::make('bale')->label('فقط ارسال بله')
                    ->query(fn (Builder $query) => $query->where('error_type', 'like', 'BALE_SEND_%')),
                Filter::make('read_errors')->label('فقط خطاهای خواندن')
                    ->query(fn (Builder $query) => $query->where('error_type', '!=', 'BALE_SEND_FAILED')->where('error_type', '!=', 'BALE_SEND_SUCCESS')),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonitoringLogs::route('/'),
        ];
    }
}
