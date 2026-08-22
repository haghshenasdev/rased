<?php

namespace App\Filament\Resources\BaleSubscribers;

use App\Filament\Resources\BaleSubscribers\Pages\CreateBaleSubscriber;
use App\Filament\Resources\BaleSubscribers\Pages\EditBaleSubscriber;
use App\Filament\Resources\BaleSubscribers\Pages\ListBaleSubscribers;
use App\Filament\Resources\BaleSubscribers\Schemas\BaleSubscriberForm;
use App\Filament\Resources\BaleSubscribers\Tables\BaleSubscribersTable;
use App\Models\BaleSubscriber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class BaleSubscriberResource extends Resource
{
    protected static ?string $model =
        BaleSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-paper-airplane';

    protected static string|UnitEnum|null $navigationGroup =
        'رصد و اعلان‌ها';

    protected static ?string $navigationLabel =
        'مشترکین بله';

    protected static ?string $modelLabel =
        'مشترک بله';

    protected static ?string $pluralModelLabel =
        'مشترکین بله';

    public static function form(
        Schema $schema
    ): Schema {
        return BaleSubscriberForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return BaleSubscribersTable::configure(
            $table
        );
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListBaleSubscribers::route('/'),

            'create' =>
                CreateBaleSubscriber::route('/create'),

            'edit' =>
                EditBaleSubscriber::route('/{record}/edit'),
        ];
    }
}
