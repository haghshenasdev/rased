<?php

namespace App\Filament\Resources\Keywords\Pages;

use App\Filament\Resources\Keywords\KeywordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKeyword extends EditRecord
{
    protected static string $resource = KeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
