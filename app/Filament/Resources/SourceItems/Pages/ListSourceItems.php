<?php

namespace App\Filament\Resources\SourceItems\Pages;

use App\Filament\Resources\SourceItems\SourceItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSourceItems extends ListRecords
{
    protected static string $resource = SourceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
