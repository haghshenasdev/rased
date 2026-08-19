<?php

namespace App\Filament\Resources\QueueJobs\Pages;

use App\Filament\Resources\QueueJobs\QueueJobResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQueueJob extends CreateRecord
{
    protected static string $resource = QueueJobResource::class;
}
