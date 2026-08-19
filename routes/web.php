<?php

use Illuminate\Support\Facades\Route;

use App\Jobs\CheckSourceJob;
use App\Models\Source;

Route::get('/monitoring/run', function () {

    $sources = Source::query()
        ->where('is_active', true)
        ->get();

    $count = 0;

    foreach ($sources as $source) {

        CheckSourceJob::dispatch(
            $source->id
        );

        $count++;
    }

    return response()->json([
        'success' => true,
        'message' => 'تمام منابع برای بررسی ارسال شدند.',
        'count' => $count,
    ]);
});

Route::get('/', function () {
    return view('welcome');
});
