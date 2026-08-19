<?php

namespace App\Filament\Resources\BlacklistKeywords\Pages;

use App\Filament\Resources\BlacklistKeywords\BlacklistKeywordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlacklistKeywords extends ListRecords
{
    protected static string $resource = BlacklistKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
