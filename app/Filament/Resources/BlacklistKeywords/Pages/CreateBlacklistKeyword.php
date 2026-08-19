<?php

namespace App\Filament\Resources\BlacklistKeywords\Pages;

use App\Filament\Resources\BlacklistKeywords\BlacklistKeywordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlacklistKeyword extends CreateRecord
{
    protected static string $resource = BlacklistKeywordResource::class;
}
