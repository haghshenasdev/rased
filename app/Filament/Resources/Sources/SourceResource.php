<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages;
use App\Models\Source;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-rss';

    protected static ?string $navigationLabel =
        'منابع';

    protected static ?string $modelLabel =
        'منبع';

    protected static ?string $pluralModelLabel =
        'منابع';

    protected static string|null|\UnitEnum $navigationGroup =
        'مانیتورینگ';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('نام منبع')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label('نوع منبع')
                    ->options([
                        'rss' => 'RSS',
                        'eitaa' => 'کانال ایتا',
                        'html' => 'HTML',
                        'javascript' => 'JavaScript',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('url')
                    ->label('آدرس منبع')
                    ->placeholder('https://example.com/feed')
                    ->url()
                    ->visible(
                        fn ($get) =>
                        in_array(
                            $get('type'),
                            ['rss', 'html', 'javascript']
                        )
                    )
                    ->required(
                        fn ($get) =>
                        in_array(
                            $get('type'),
                            ['rss', 'html', 'javascript']
                        )
                    ),

                TextInput::make('identifier')
                    ->label('شناسه کانال ایتا')
                    ->placeholder('Hamase4')
                    ->helperText(
                        'نام کانال را بدون https://eitaa.com/ وارد کنید.'
                    )
                    ->visible(
                        fn ($get) =>
                            $get('type') === 'eitaa'
                    )
                    ->required(
                        fn ($get) =>
                            $get('type') === 'eitaa'
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

                TextColumn::make('name')
                    ->label('نام منبع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(
                        fn ($state) => match ($state) {
                            'rss' => 'RSS',
                            'eitaa' => 'ایتا',
                            'html' => 'HTML',
                            'javascript' => 'JavaScript',
                            default => $state,
                        }
                    ),

                TextColumn::make('url')
                    ->label('آدرس')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('identifier')
                    ->label('شناسه')
                    ->placeholder('-'),

                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                TextColumn::make('last_item_id')
                    ->label('آخرین مطلب')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('last_read_at')
                    ->label('آخرین بررسی')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\Action::make('check')
                    ->label('بررسی الآن')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('بررسی منبع')
                    ->modalDescription(
                        fn ($record) =>
                        "آیا می‌خواهید «{$record->name}» همین الآن بررسی شود؟"
                    )
                    ->action(function ($record) {

                        \App\Jobs\CheckSourceJob::dispatch(
                            $record->id
                        );

                    })
                    ->successNotificationTitle(
                        'بررسی منبع در صف قرار گرفت'
                    ),
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
            'index' => Pages\ListSources::route('/'),
            'create' => Pages\CreateSource::route('/create'),
            'edit' => Pages\EditSource::route('/{record}/edit'),
        ];
    }
}
