<?php

namespace App\Filament\Resources\BaleSubscribers\Pages;

use App\Filament\Resources\BaleSubscribers\BaleSubscriberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBaleSubscriber extends EditRecord
{
    protected static string $resource = BaleSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
