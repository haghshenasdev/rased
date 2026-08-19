<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;

interface SourceReaderInterface
{
    /**
     * @return SourceItemData[]
     */
    public function read(Source $source): array;
}
