<?php

namespace App\Filament\Resources\SourceItems\Pages;

use App\Filament\Resources\SourceItems\SourceItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSourceItem extends EditRecord
{
    protected static string $resource = SourceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
