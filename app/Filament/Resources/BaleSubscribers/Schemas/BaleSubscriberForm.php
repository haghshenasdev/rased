<?php

namespace App\Filament\Resources\BaleSubscribers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BaleSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            TextInput::make('name')
                ->label('نام فرد')
                ->required()
                ->maxLength(255),

            TextInput::make('phone')
                ->label('شماره تماس')
                ->tel()
                ->maxLength(30),

            Toggle::make('is_active')
                ->label('ارسال اخبار فعال باشد')
                ->default(true),

        ]);
    }
}
