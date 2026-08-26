<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages;
use App\Models\Source;
use App\Services\Monitoring\Readers\SourceReaderFactory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

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

                /*
                 * دکمه تست خواندن منبع
                 */
                Actions::make([
                    Action::make('testSource')
                        ->label('تست خواندن منبع')
                        ->icon('heroicon-o-beaker')
                        ->color('info')
                        ->button()

                        ->action(
                            function (
                                $livewire,
                                $get
                            ) {

                                /*
                                 * دریافت اطلاعات فعلی فرم
                                 */
                                $name = $get('name');
                                $type = $get('type');
                                $url = $get('url');
                                $identifier = $get('identifier');

                                /*
                                 * بررسی اطلاعات اولیه
                                 */
                                if (!$type) {

                                    Notification::make()
                                        ->title('نوع منبع مشخص نشده است')
                                        ->body(
                                            'ابتدا نوع منبع را انتخاب کنید.'
                                        )
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                if (
                                    in_array(
                                        $type,
                                        ['rss', 'html', 'javascript']
                                    )
                                    && !$url
                                ) {

                                    Notification::make()
                                        ->title('آدرس منبع وارد نشده است')
                                        ->body(
                                            'برای این نوع منبع باید آدرس URL وارد کنید.'
                                        )
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                if (
                                    $type === 'eitaa'
                                    && !$identifier
                                ) {

                                    Notification::make()
                                        ->title('شناسه کانال وارد نشده است')
                                        ->body(
                                            'شناسه کانال ایتا را وارد کنید.'
                                        )
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                try {

                                    /*
                                     * Source موقت
                                     *
                                     * هنوز چیزی در دیتابیس ذخیره نمی‌شود.
                                     */
                                    $source = new Source();

                                    $source->name =
                                        $name ?: 'تست منبع';

                                    $source->type =
                                        $type;

                                    $source->url =
                                        $url;

                                    $source->identifier =
                                        $identifier;

                                    $source->is_active =
                                        true;

                                    /*
                                     * Reader واقعی پروژه
                                     */
                                    $factory =
                                        app(SourceReaderFactory::class);

                                    $reader =
                                        $factory->make($source);

                                    /*
                                     * خواندن واقعی منبع
                                     */
                                    $items =
                                        $reader->read($source);

                                    /*
                                     * اگر چیزی برنگردد
                                     */
                                    if (empty($items)) {

                                        Notification::make()
                                            ->title(
                                                'اتصال موفق بود اما مطلبی دریافت نشد'
                                            )
                                            ->body(
                                                'Reader اجرا شد ولی هیچ پستی برای تحلیل برنگرداند.'
                                            )
                                            ->warning()
                                            ->persistent()
                                            ->send();

                                        return;
                                    }

                                    /*
                                     * تعداد مطالب
                                     */
                                    $count =
                                        count($items);

                                    /*
                                     * نمایش چند مطلب اول
                                     */
                                    $preview = '';

                                    foreach (
                                        array_slice(
                                            $items,
                                            0,
                                            5
                                        )
                                        as $index => $item
                                    ) {

                                        $title =
                                            $item->title
                                            ?? 'بدون عنوان';

                                        $externalId =
                                            $item->externalId
                                            ?? '-';

                                        $preview .=
                                            ($index + 1)
                                            . '. '
                                            . $title
                                            . "\n";

                                        $preview .=
                                            "ID: "
                                            . $externalId
                                            . "\n\n";
                                    }

                                    /*
                                     * موفقیت
                                     */
                                    Notification::make()
                                        ->title(
                                            '✅ خواندن منبع موفق بود'
                                        )
                                        ->body(
                                            "تعداد مطالب دریافت‌شده: {$count}\n\n"
                                            . $preview
                                        )
                                        ->success()
                                        ->persistent()
                                        ->send();

                                } catch (Throwable $e) {

                                    /*
                                     * ثبت خطا در Laravel Log
                                     */
                                    report($e);

                                    /*
                                     * نمایش خطا به کاربر
                                     */
                                    Notification::make()
                                        ->title(
                                            '❌ خطا در خواندن منبع'
                                        )
                                        ->body(
                                            $e->getMessage()
                                        )
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                }
                            }
                        ),
                ])
                    ->columnSpanFull(),

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
                    ->label('نوع منبع')
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

                Action::make('check')
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

                /*
                 * بررسی همه منابع فعال
                 */
                \Filament\Actions\Action::make('checkAll')
                    ->label('بررسی همه منابع')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('بررسی همه منابع')
                    ->modalDescription(
                        'همه منابع فعال در صف بررسی قرار خواهند گرفت. آیا ادامه می‌دهید؟'
                    )
                    ->action(function () {

                        $sources = \App\Models\Source::query()
                            ->where('is_active', true)
                            ->orderBy('id')
                            ->get();

                        $count = 0;

                        foreach ($sources as $source) {

                            \App\Jobs\CheckSourceJob::dispatch(
                                $source->id
                            );

                            $count++;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('منابع در صف قرار گرفتند')
                            ->body(
                                "{$count} منبع برای بررسی در صف قرار گرفت."
                            )
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),

            ]);
    }

    public static function getPages(): array
    {
        return [

            'index' =>
                Pages\ListSources::route('/'),

            'create' =>
                Pages\CreateSource::route('/create'),

            'edit' =>
                Pages\EditSource::route('/{record}/edit'),

        ];
    }
}
