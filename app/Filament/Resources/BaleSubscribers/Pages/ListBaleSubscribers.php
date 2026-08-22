<?php

namespace App\Filament\Resources\BaleSubscribers\Pages;

use App\Filament\Resources\BaleSubscribers\BaleSubscriberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBaleSubscribers extends ListRecords
{
    protected static string $resource = BaleSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
