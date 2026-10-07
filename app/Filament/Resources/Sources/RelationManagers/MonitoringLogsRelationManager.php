<?php

namespace App\Filament\Resources\Sources\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonitoringLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'monitoringLogs';
    protected static ?string $title = 'خطاهای این منبع';
    protected static ?string $label = 'خطا';
    protected static ?string $pluralLabel = 'خطاهای این منبع';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('تاریخ')
                    ->jalaliDateTime('Y/m/d H:i:s')->sortable(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('error_type')->label('نوع خطا')->limit(50),
                TextColumn::make('message')->label('پیام خطا')->limit(120)->wrap()
                    ->tooltip(fn ($record) => $record->message),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options([
                    'failed' => 'خطا',
                    'success' => 'موفق',
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('error_type', '!=', 'BALE_SEND_FAILED')
                ->where('error_type', '!=', 'BALE_SEND_SUCCESS'))
            ->recordActions([])
            ->toolbarActions([]);
    }
}
