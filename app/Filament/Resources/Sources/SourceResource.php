<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages;
use App\Models\Category;
use App\Models\Source;
use App\Services\Monitoring\Readers\SourceReaderFactory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rss';
    protected static ?string $navigationLabel = 'منابع';
    protected static ?string $modelLabel = 'منبع';
    protected static ?string $pluralModelLabel = 'منابع';
    protected static string|null|\UnitEnum $navigationGroup = 'مانیتورینگ';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام منبع')->required()->maxLength(255),

            Select::make('type')
                ->label('نوع منبع')
                ->options([
                    'rss' => 'RSS',
                    'google' => 'Google Search',
                    'eitaa' => 'کانال ایتا',
                    'html' => 'HTML',
                    'javascript' => 'JavaScript',
                    'browser' => 'Browser',
                    'farsnews' => 'FarsNews',
                ])
                ->required()->live(),

            Select::make('parent_id')
                ->label('زیرمجموعه منبع')
                ->options(fn (?Source $record) => Source::query()
                    ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                    ->orderBy('name')->pluck('name', 'id'))
                ->searchable()->nullable()
                ->helperText('برای ساخت ساختار خبرگزاری ← RSS/کانال‌های زیرمجموعه استفاده کنید.'),

            TextInput::make('url')
                ->label('آدرس اصلی')
                ->placeholder('https://example.com/feed')
                ->url()
                ->visible(fn ($get) => in_array($get('type'), ['rss','html','javascript','browser','farsnews']))
                ->required(fn ($get) => in_array($get('type'), ['html','javascript','browser','farsnews'])),

            Repeater::make('settings.feed_urls')
                ->label('RSS / لینک‌های اضافی')
                ->simple(TextInput::make('url')->label('URL')->url()->required())
                ->visible(fn ($get) => $get('type') === 'rss')
                ->helperText('یک منبع می‌تواند چند RSS یا لینک برای بررسی داشته باشد.')
                ->addActionLabel('افزودن RSS'),

            TextInput::make('identifier')
                ->label('شناسه کانال ایتا')
                ->placeholder('farsna')
                ->visible(fn ($get) => $get('type') === 'eitaa')
                ->required(fn ($get) => $get('type') === 'eitaa'),

            TextInput::make('settings.query')
                ->label('کلمه کلیدی Google')
                ->placeholder('مثلاً حاجی دلیگانی')
                ->visible(fn ($get) => $get('type') === 'google')
                ->required(fn ($get) => $get('type') === 'google'),

            TextInput::make('settings.count')
                ->label('تعداد نتیجه Google')
                ->numeric()->minValue(1)->maxValue(10)->default(10)
                ->visible(fn ($get) => $get('type') === 'google'),

            FileUpload::make('profile_image_path')
                ->label('عکس پروفایل منبع')
                ->image()->disk('public')->directory('source-profiles')
                ->imageEditor()->nullable(),

            TextInput::make('profile_image_url')
                ->label('یا لینک عکس پروفایل')
                ->url()->nullable(),

            Select::make('default_category_id')
                ->label('دسته‌بندی پیش‌فرض')
                ->options(fn () => Category::query()->where('is_active', true)->orderBy('name')->pluck('name','id'))
                ->searchable()->nullable(),

            Toggle::make('auto_categorize')
                ->label('دسته‌بندی خودکار')
                ->default(true),

            Toggle::make('ignore_link_keyword')
                ->label('کلمه کلیدی فقط از متن خبر')
                ->default(true)
                ->helperText('کلمه داخل URL یا لینک‌های صفحه هیچ‌وقت برای تطبیق Keyword استفاده نمی‌شود.'),

            Toggle::make('is_active')->label('فعال')->default(true),

            Actions::make([
                Action::make('testSource')
                    ->label('تست خواندن منبع')->icon('heroicon-o-beaker')->color('info')->button()
                    ->action(function ($get) {
                        $source = new Source([
                            'name' => $get('name') ?: 'تست منبع',
                            'type' => $get('type'),
                            'url' => $get('url'),
                            'identifier' => $get('identifier'),
                            'settings' => [
                                'feed_urls' => array_map(
                                    fn ($v) => is_array($v) ? ($v['url'] ?? '') : $v,
                                    $get('settings.feed_urls') ?? []
                                ),
                                'query' => $get('settings.query'),
                                'count' => $get('settings.count') ?: 10,
                            ],
                            'auto_categorize' => (bool)$get('auto_categorize'),
                            'ignore_link_keyword' => true,
                        ]);

                        try {
                            $items = app(SourceReaderFactory::class)->make($source)->read($source);
                            $preview = collect(array_slice($items, 0, 5))
                                ->map(fn ($i, $n) => ($n+1).'. '.$i->title)
                                ->implode("\n");
                            Notification::make()->title('خواندن موفق بود')
                                ->body("تعداد: ".count($items)."\n\n".$preview)
                                ->success()->persistent()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('خطا در خواندن منبع')
                                ->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('والد')->placeholder('-')->badge(),
                TextColumn::make('type')->label('نوع')->badge(),
                TextColumn::make('defaultCategory.name')->label('دسته پیش‌فرض')->placeholder('-')->badge(),
                IconColumn::make('auto_categorize')->label('خودکار')->boolean(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('last_read_at')->label('آخرین بررسی')
                    ->jalaliDateTime('Y/m/d H:i')->sortable(),
            ])
            ->defaultSort('id','desc')
            ->recordActions([
                Action::make('check')->label('بررسی الآن')->icon('heroicon-o-arrow-path')
                    ->action(fn ($record) => \App\Jobs\CheckSourceJob::dispatch($record->id))
                    ->successNotificationTitle('در صف بررسی قرار گرفت'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                Action::make('checkAll')->label('بررسی همه')->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function () {
                        $count = 0;
                        Source::where('is_active',true)->each(function ($s) use (&$count) {
                            \App\Jobs\CheckSourceJob::dispatch($s->id); $count++;
                        });
                        Notification::make()->title("{$count} منبع در صف قرار گرفت")->success()->send();
                    }),
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Sources\RelationManagers\MonitoringLogsRelationManager::class,
        ];
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
