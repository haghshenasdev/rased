<?php

namespace App\Filament\Resources\Keywords\Pages;

use App\Filament\Resources\Keywords\KeywordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeywords extends ListRecords
{
    protected static string $resource = KeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
