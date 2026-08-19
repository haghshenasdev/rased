<?php

namespace App\Filament\Resources\BlacklistKeywords\Pages;

use App\Filament\Resources\BlacklistKeywords\BlacklistKeywordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlacklistKeyword extends EditRecord
{
    protected static string $resource = BlacklistKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
