<?php

namespace App\Filament\Resources\Keywords;

use App\Filament\Resources\Keywords\Pages;
use App\Models\Keyword;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel =
        'کلمات کلیدی';

    protected static ?string $modelLabel =
        'کلمه کلیدی';

    protected static ?string $pluralModelLabel =
        'کلمات کلیدی';

    protected static string|null|\UnitEnum $navigationGroup =
        'مانیتورینگ';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('word')
                    ->label('کلمه کلیدی')
                    ->required()
                    ->maxLength(500),

                TextInput::make('priority')
                    ->label('اولویت')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->helperText(
                        'عدد بیشتر یعنی این کلمه زودتر بررسی می‌شود.'
                    ),

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
                    ->label('کلمه')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('اولویت')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')->jalaliDateTime()
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

            ])
            ->defaultSort('priority', 'desc')
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
            'index' => Pages\ListKeywords::route('/'),
            'create' => Pages\CreateKeyword::route('/create'),
            'edit' => Pages\EditKeyword::route('/{record}/edit'),
        ];
    }
}
