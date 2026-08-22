<?php

namespace App\Http\Controllers;

use App\Models\SourceItem;

class NewsController extends Controller
{
    public function show(SourceItem $sourceItem)
    {
        $sourceItem->load('source');

        return view(
            'news.show',
            compact('sourceItem')
        );
    }
}
