<?php

namespace App\Filament\Resources\QueueJobs\Pages;

use App\Filament\Resources\QueueJobs\QueueJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQueueJob extends EditRecord
{
    protected static string $resource = QueueJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
