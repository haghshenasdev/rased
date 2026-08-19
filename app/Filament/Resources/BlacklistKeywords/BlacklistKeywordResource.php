<?php

namespace App\Filament\Resources\BlacklistKeywords;

use App\Filament\Resources\BlacklistKeywords\Pages;
use App\Models\BlacklistKeyword;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlacklistKeywordResource extends Resource
{
    protected static ?string $model =
        BlacklistKeyword::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-no-symbol';

    protected static ?string $navigationLabel =
        'کلمات ممنوعه';

    protected static ?string $modelLabel =
        'کلمه ممنوعه';

    protected static ?string $pluralModelLabel =
        'کلمات ممنوعه';

    protected static string|null|\UnitEnum $navigationGroup =
        'مانیتورینگ';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('word')
                    ->label('کلمه ممنوعه')
                    ->required()
                    ->maxLength(500),

                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('word')
                    ->label('کلمه ممنوعه')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                \Filament\Actions\EditAction::make(),
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
                Pages\ListBlacklistKeywords::route('/'),

            'create' =>
                Pages\CreateBlacklistKeyword::route('/create'),

            'edit' =>
                Pages\EditBlacklistKeyword::route('/{record}/edit'),
        ];
    }
}
