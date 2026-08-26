<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

use App\Jobs\CheckSourceJob;
use App\Models\Source;

use Illuminate\Support\Facades\Artisan;

use App\Http\Controllers\BaleWebhookController;

Route::post(
    '/bale/webhook',
    [BaleWebhookController::class, 'handle']
);

Route::middleware('auth')->get('/bale/set-webhook', function () {

    $bale = app(\App\Services\BaleBotService::class);

    return response()->json(
        $bale->setWebhook(
            url('/bale/webhook')
        )
    );
});

Route::middleware('auth')->get('/bale/webhook-info', function () {

    $bale = app(\App\Services\BaleBotService::class);

    return response()->json(
        $bale->getWebhookInfo()
    );
});

Route::get('/cron/monitoring/{token}', function (string $token) {

    if (!hash_equals(
        (string) config('app.monitoring_secret'),
        $token
    )) {
        abort(403);
    }

    Artisan::call('monitoring:run');

    return response()->json([
        'success' => true,
        'message' => 'Monitoring command executed.',
        'output' => Artisan::output(),
    ]);
});

Route::get('/cron/queue/{token}', function (string $token) {

    if (!hash_equals(
        (string) config('app.monitoring_secret'),
        $token
    )) {
        abort(403);
    }

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--stop-when-empty' => true,
        '--max-time' => 50,
        '--tries' => 2,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Queue worker executed.',
        'output' => Artisan::output(),
    ]);
});

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

Route::get('/', [
    HomeController::class,
    'index'
])->name('home');

Route::get('/news/{sourceItem}', [
    NewsController::class,
    'show'
])->name('news.show');


Route::get('/run-migrations-temp', function () {
    Artisan::call('migrate:fresh', [
        '--force' => true,
    ]);

    return nl2br(Artisan::output());
});
