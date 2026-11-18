<?php

use App\Http\Controllers\WompiWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wompi Webhook Route
|--------------------------------------------------------------------------
|
| Publish this file with:
|
|     php artisan vendor:publish --tag=wompi-webhook
|
| and register it in bootstrap/app.php (Laravel 11 and newer):
|
|     ->withRouting(
|         web: __DIR__.'/../routes/web.php',
|         api:  __DIR__.'/../routes/api.php',
|         then: function () {
|             Route::middleware('api')->group(base_path('routes/wompi.php'));
|         },
|     )
|
| or in app/Providers/RouteServiceProvider.php (Laravel 10):
|
|     Route::middleware('api')->group(base_path('routes/wompi.php'));
|
| The "api" middleware group does not include CSRF validation, so no change
| to VerifyCsrfToken is required.
|
*/

Route::post(config('wompi.webhook.path', 'webhooks/wompi'), WompiWebhookController::class)
    ->name(config('wompi.webhook.route', 'wompi.webhook'))
    ->middleware(['wompi.webhook'])
    ->withoutMiddleware('throttle:api');
