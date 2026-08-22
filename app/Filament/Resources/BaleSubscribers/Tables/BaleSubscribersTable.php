<?php

namespace App\Filament\Resources\BaleSubscribers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BaleSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('شماره تماس')
                    ->searchable(),

                TextColumn::make('token')
                    ->label('توکن اتصال')
                    ->copyable()
                    ->copyMessage('توکن کپی شد')
                    ->copyMessageDuration(1500),

                TextColumn::make('chat_id')
                    ->label('شناسه بله')
                    ->placeholder('متصل نشده'),

                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                TextColumn::make('connected_at')
                    ->label('اتصال')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),

                TextColumn::make('last_sent_at')
                    ->label('آخرین ارسال')
                    ->since()
                    ->placeholder('—'),

            ])
            ->defaultSort(
                'created_at',
                'desc'
            )
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
