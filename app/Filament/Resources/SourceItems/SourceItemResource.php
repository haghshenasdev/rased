<?php

namespace App\Filament\Resources\SourceItems;

use App\Filament\Resources\SourceItems\Pages;
use App\Jobs\SendSelectedNewsToBaleJob;
use App\Models\BaleSubscriber;
use App\Models\SourceItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SourceItemResource extends Resource
{
    protected static ?string $model =
        SourceItem::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-newspaper';

    protected static ?string $navigationLabel =
        'اخبار پیدا شده';

    protected static ?string $modelLabel =
        'خبر';

    protected static ?string $pluralModelLabel =
        'اخبار پیدا شده';

    protected static string|null|\UnitEnum $navigationGroup =
        'مانیتورینگ';

    public static function form(
        Schema $schema
    ): Schema {

        return $schema
            ->components([]);
    }

    public static function table(
        Table $table
    ): Table {

        return $table

            ->columns([

                /*
                 * عنوان
                 */
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->limit(80)
                    ->wrap(),

                /*
                 * منبع
                 */
                TextColumn::make('source.name')
                    ->label('منبع')
                    ->badge()
                    ->searchable(),

                /*
                 * کلمه کلیدی
                 */
                TextColumn::make('matched_keyword')
                    ->label('کلمه کلیدی')
                    ->badge()
                    ->searchable(),

                /*
                 * پاراگراف مرتبط
                 */
                TextColumn::make(
                    'matched_content'
                )
                    ->label('پاراگراف مرتبط')
                    ->limit(100)
                    ->wrap()
                    ->toggleable(),

                /*
                 * تاریخ انتشار
                 */
                TextColumn::make(
                    'published_at'
                )
                    ->label('تاریخ انتشار')
                    ->jalaliDateTime(
                        'Y/m/d H:i'
                    )
                    ->sortable(),

                /*
                 * زمان دریافت
                 */
                TextColumn::make(
                    'created_at'
                )
                    ->label('زمان دریافت')
                    ->jalaliDateTime(
                        'Y/m/d H:i'
                    )
                    ->sortable(),
            ])

            ->defaultSort(
                'created_at',
                'desc'
            )

            /*
             * عملیات هر خبر
             */
            ->recordActions([

                /*
                 * ارسال یک خبر به بله
                 */
                Action::make('sendToBale')

                    ->label(
                        'ارسال به بله'
                    )

                    ->icon(
                        'heroicon-o-paper-airplane'
                    )

                    ->color('success')

                    ->modalHeading(
                        '📤 ارسال خبر به بله'
                    )

                    ->modalDescription(
                        'مشترکینی را که می‌خواهید این خبر برای آن‌ها ارسال شود انتخاب کنید.'
                    )

                    ->schema([

                        CheckboxList::make(
                            'subscriber_ids'
                        )

                            ->label(
                                'مشترکین بله'
                            )

                            ->options(
                                fn (): array =>
                                BaleSubscriber::query()
                                    ->where(
                                        'is_active',
                                        true
                                    )
                                    ->whereNotNull(
                                        'chat_id'
                                    )
                                    ->orderBy(
                                        'id'
                                    )
                                    ->get()
                                    ->mapWithKeys(
                                        function (
                                            BaleSubscriber $subscriber
                                        ) {

                                            /*
                                             * اگر مدل name دارد،
                                             * از آن استفاده می‌شود.
                                             *
                                             * در غیر این صورت chat_id
                                             * نمایش داده می‌شود.
                                             */
                                            $label =
                                                $subscriber->name
                                                ?? (
                                                'مشترک #' .
                                                $subscriber->id
                                            );

                                            return [
                                                $subscriber->id =>
                                                    $label,
                                            ];
                                        }
                                    )
                                    ->all()
                            )

                            ->searchable()

                            ->bulkToggleable()

                            ->columns(2)

                            ->required(),

                    ])

                    ->action(
                        function (
                            SourceItem $record,
                            array $data
                        ): void {

                            $subscriberIds =
                                collect(
                                    $data[
                                    'subscriber_ids'
                                    ] ?? []
                                )
                                    ->map(
                                        fn ($id) =>
                                        (int) $id
                                    )
                                    ->filter()
                                    ->values()
                                    ->all();

                            if (
                                empty(
                                $subscriberIds
                                )
                            ) {

                                Notification::make()
                                    ->title(
                                        'مشترکی انتخاب نشده است'
                                    )
                                    ->warning()
                                    ->send();

                                return;
                            }

                            /*
                             * ارسال به Queue
                             */
                            SendSelectedNewsToBaleJob::dispatch(
                                [$record->id],
                                $subscriberIds
                            );

                            Notification::make()
                                ->title(
                                    'خبر در صف ارسال قرار گرفت'
                                )
                                ->body(
                                    'خبر برای ' .
                                    count(
                                        $subscriberIds
                                    ) .
                                    ' مشترک ارسال خواهد شد.'
                                )
                                ->success()
                                ->send();
                        }
                    ),

                /*
                 * مشاهده
                 */
                ViewAction::make(),

                /*
                 * حذف
                 */
                DeleteAction::make(),
            ])

            /*
             * عملیات گروهی
             */
            ->toolbarActions([

                BulkActionGroup::make([

                    /*
                     * ارسال چند خبر به چند مشترک
                     */
                    BulkAction::make(
                        'sendSelectedToBale'
                    )

                        ->label(
                            'ارسال اخبار انتخاب‌شده به بله'
                        )

                        ->icon(
                            'heroicon-o-paper-airplane'
                        )

                        ->color('success')

                        ->modalHeading(
                            '📤 ارسال اخبار به بله'
                        )

                        ->modalDescription(
                            'مشترکین موردنظر را انتخاب کنید. تمام اخبار انتخاب‌شده برای این مشترکین ارسال خواهند شد.'
                        )

                        ->schema([

                            CheckboxList::make(
                                'subscriber_ids'
                            )

                                ->label(
                                    'مشترکین بله'
                                )

                                ->options(
                                    fn (): array =>
                                    BaleSubscriber::query()
                                        ->where(
                                            'is_active',
                                            true
                                        )
                                        ->whereNotNull(
                                            'chat_id'
                                        )
                                        ->orderBy(
                                            'id'
                                        )
                                        ->get()
                                        ->mapWithKeys(
                                            function (
                                                BaleSubscriber $subscriber
                                            ) {

                                                $label =
                                                    $subscriber->name
                                                    ?? (
                                                    'مشترک #' .
                                                    $subscriber->id
                                                );

                                                return [
                                                    $subscriber->id =>
                                                        $label,
                                                ];
                                            }
                                        )
                                        ->all()
                                )

                                ->searchable()

                                ->bulkToggleable()

                                ->columns(2)

                                ->required(),

                        ])

                        ->action(
                            function (
                                Collection $records,
                                array $data
                            ): void {

                                $subscriberIds =
                                    collect(
                                        $data[
                                        'subscriber_ids'
                                        ] ?? []
                                    )
                                        ->map(
                                            fn ($id) =>
                                            (int) $id
                                        )
                                        ->filter()
                                        ->values()
                                        ->all();

                                if (
                                    empty(
                                    $subscriberIds
                                    )
                                ) {

                                    Notification::make()
                                        ->title(
                                            'مشترکی انتخاب نشده است'
                                        )
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $sourceItemIds =
                                    $records
                                        ->pluck('id')
                                        ->map(
                                            fn ($id) =>
                                            (int) $id
                                        )
                                        ->values()
                                        ->all();

                                if (
                                    empty(
                                    $sourceItemIds
                                    )
                                ) {

                                    Notification::make()
                                        ->title(
                                            'خبری انتخاب نشده است'
                                        )
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                /*
                                 * ارسال به Queue
                                 */
                                SendSelectedNewsToBaleJob::dispatch(
                                    $sourceItemIds,
                                    $subscriberIds
                                );

                                Notification::make()
                                    ->title(
                                        'اخبار در صف ارسال قرار گرفتند'
                                    )
                                    ->body(
                                        count(
                                            $sourceItemIds
                                        )
                                        . ' خبر برای '
                                        . count(
                                            $subscriberIds
                                        )
                                        . ' مشترک ارسال خواهد شد.'
                                    )
                                    ->success()
                                    ->send();
                            }
                        )

                        /*
                         * بعد از اجرای موفق،
                         * انتخاب رکوردها پاک شود.
                         */
                        ->deselectRecordsAfterCompletion(),

                    /*
                     * حذف گروهی
                     */
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [

            'index' =>
                Pages\ListSourceItems::route('/'),

            'view' =>
                Pages\ViewSourceItem::route(
                    '/{record}'
                ),
        ];
    }
}
