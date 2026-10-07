<?php

namespace App\Filament\Resources\MonitoringLogs\Pages;

use App\Filament\Resources\MonitoringLogs\MonitoringLogResource;
use Filament\Resources\Pages\ListRecords;

class ListMonitoringLogs extends ListRecords
{
    protected static string $resource = MonitoringLogResource::class;
}
