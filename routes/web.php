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
        '--timeout' => 90,
        '--tries' => 2,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Queue worker executed.',
        'output' => Artisan::output(),
    ]);
});

Route::middleware('auth')->get('/monitoring/run', function () {

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
    'landing'
])->name('home');

Route::get('/news', [HomeController::class, 'index'])->name('news.index');

Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [\App\Http\Controllers\PanelController::class, 'index'])->name('index');
    Route::post('/logout', [\App\Http\Controllers\PanelController::class, 'logout'])->name('logout');
    Route::get('/news', [\App\Http\Controllers\PanelController::class, 'news'])->name('news');
    Route::post('/monitoring/run', [\App\Http\Controllers\PanelController::class, 'runMonitoring'])->name('monitoring.run');
    Route::get('/bale-settings', [\App\Http\Controllers\PanelBaleSettingsController::class, 'index'])->name('bale-settings');
    Route::post('/bale-settings/webhook', [\App\Http\Controllers\PanelBaleSettingsController::class, 'setWebhook'])->name('bale-settings.webhook');
    Route::delete('/bale-settings/webhook', [\App\Http\Controllers\PanelBaleSettingsController::class, 'deleteWebhook'])->name('bale-settings.webhook.delete');
    Route::post('/bale-settings/test-message', [\App\Http\Controllers\PanelBaleSettingsController::class, 'testMessage'])->name('bale-settings.test-message');
    Route::get('/manage/{section}', [\App\Http\Controllers\PanelManagementController::class, 'index'])->name('manage.index');
    Route::get('/manage/{section}/create', [\App\Http\Controllers\PanelManagementController::class, 'create'])->name('manage.create');
    Route::post('/manage/{section}', [\App\Http\Controllers\PanelManagementController::class, 'store'])->name('manage.store');
    Route::post('/manage/sources/profile-images/find-all', [\App\Http\Controllers\PanelManagementController::class, 'findAllSourceProfileImages'])->name('manage.sources.profile-images.find-all');
    Route::post('/manage/sources/{record}/check', [\App\Http\Controllers\PanelManagementController::class, 'checkSource'])->whereNumber('record')->name('manage.sources.check');
    Route::post('/manage/sources/{record}/test', [\App\Http\Controllers\PanelManagementController::class, 'testSource'])->whereNumber('record')->name('manage.sources.test');
    Route::post('/manage/sources/{record}/profile-image/find', [\App\Http\Controllers\PanelManagementController::class, 'findSourceProfileImage'])->whereNumber('record')->name('manage.sources.profile-image.find');
    Route::get('/manage/source-items/{record}/send', [\App\Http\Controllers\PanelManagementController::class, 'sendForm'])->whereNumber('record')->name('manage.source-items.send-form');
    Route::post('/manage/source-items/{record}/send', [\App\Http\Controllers\PanelManagementController::class, 'sendToSubscribers'])->whereNumber('record')->name('manage.source-items.send');
    Route::get('/manage/{section}/{record}/edit', [\App\Http\Controllers\PanelManagementController::class, 'edit'])->whereNumber('record')->name('manage.edit');
    Route::put('/manage/{section}/{record}', [\App\Http\Controllers\PanelManagementController::class, 'update'])->whereNumber('record')->name('manage.update');
    Route::delete('/manage/{section}/{record}', [\App\Http\Controllers\PanelManagementController::class, 'destroy'])->whereNumber('record')->name('manage.destroy');
});

Route::get('/news/{sourceItem}', [
    NewsController::class,
    'show'
])->name('news.show');

Route::get('/clear-cache-temp', function () {

    Artisan::call('optimize:clear');

    return response()->json([
        'success' => true,
        'message' => 'تمام کش‌های لاراول پاک شد.',
        'output' => Artisan::output(),
    ]);
});


