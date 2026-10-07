<?php

namespace App\Filament\Resources\Categories;

use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'دسته‌بندی‌ها';
    protected static ?string $modelLabel = 'دسته‌بندی';
    protected static ?string $pluralModelLabel = 'دسته‌بندی‌ها';
    protected static string|null|\UnitEnum $navigationGroup = 'مانیتورینگ';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required(),
            Textarea::make('keywords')->label('کلمات کلیدی')
                ->helperText('هر کلمه در یک خط. برای دسته‌بندی هوشمند کانال/سایت استفاده می‌شود.')
                ->dehydrateStateUsing(fn ($state) => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)$state) ?: []))))
                ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : (string) $state),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('دسته')->searchable()->sortable(),
            TextColumn::make('keywords')->label('کلمات')->formatStateUsing(fn ($state) => is_array($state) ? implode('، ', $state) : '')->limit(80),
            IconColumn::make('is_active')->label('فعال')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])
          ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
