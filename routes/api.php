<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CleaningTaskController;
use App\Http\Controllers\Api\V1\LaundryTaskController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Controllers\Webhooks\BillplzWebhookController;
use App\Http\Controllers\Webhooks\SecurePayWebhookController;
use App\Http\Controllers\Webhooks\SesNotificationController;
use App\Http\Controllers\Webhooks\SubscriptionBillingWebhookController;
use App\Http\Controllers\Webhooks\ToyyibpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/register-fcm-token', [AuthController::class, 'registerFcmToken']);

        Route::get('me', [MeController::class, 'show']);
        Route::post('me/switch-tenant', [MeController::class, 'switchTenant']);

        Route::middleware('tenant.require')->group(function () {
            Route::middleware('throttle:api-read')->group(function () {
                Route::apiResource('properties', PropertyController::class);
                Route::apiResource('rooms', RoomController::class);
                Route::apiResource('bookings', BookingController::class);

                Route::get('properties/{property}/calendar', [CalendarController::class, 'show']);

                Route::get('tasks/cleaning', [CleaningTaskController::class, 'index']);
                Route::get('tasks/laundry', [LaundryTaskController::class, 'index']);

                Route::get('reports/occupancy', [ReportController::class, 'occupancy']);
                Route::get('reports/revenue', [ReportController::class, 'revenue']);
            });

            Route::middleware('throttle:api-write')->group(function () {
                Route::post('properties/{property}/calendar/block', [CalendarController::class, 'block']);
                Route::post('bookings/{booking}/check-in', [BookingController::class, 'checkIn']);
                Route::post('bookings/{booking}/check-out', [BookingController::class, 'checkOut']);
                Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
                Route::post('bookings/{booking}/mark-paid', [BookingController::class, 'markPaid']);

                Route::post('tasks/cleaning/{task}/start', [CleaningTaskController::class, 'start']);
                Route::post('tasks/cleaning/{task}/complete', [CleaningTaskController::class, 'complete']);
                Route::post('tasks/laundry/{task}/pickup', [LaundryTaskController::class, 'pickup']);
                Route::post('tasks/laundry/{task}/return', [LaundryTaskController::class, 'returnLaundry']);
            });
        });
    });
});

// Webhooks (public, signature-verified)
Route::post('/webhooks/toyyibpay', [ToyyibpayWebhookController::class, 'handle'])
    ->middleware('throttle:webhook-toyyibpay')
    ->name('webhooks.toyyibpay');

Route::post('/webhooks/billplz', [BillplzWebhookController::class, 'handle'])
    ->middleware('throttle:webhook-billplz')
    ->name('webhooks.billplz');

Route::post('/webhooks/securepay', [SecurePayWebhookController::class, 'handle'])
    ->middleware('throttle:webhook-securepay')
    ->name('webhooks.securepay');

// Platform subscription billing — a TENANT paying Tempahlah RM 49/mo into our
// own Billplz account. Distinct from /webhooks/billplz above, which is a GUEST
// paying a tenant through that tenant's own account.
Route::post('/webhooks/subscription-billing', [SubscriptionBillingWebhookController::class, 'handle'])
    ->middleware('throttle:webhook-subscription')
    ->name('webhooks.subscription-billing');

// Billplz card-tokenization callback: the tenant completed 3DS and Billplz is
// posting back the saved-card token. Checksum-verified inside the controller;
// api group has no CSRF, so no exemption needed (like the callback above).
Route::post('/webhooks/subscription-card', [\App\Http\Controllers\Tenant\SubscriptionCardController::class, 'callback'])
    ->middleware('throttle:webhook-subscription')
    ->name('subscription.card.callback');

// SES bounce + complaint notifications, delivered via SNS (signature-verified
// inside the controller). Keeps our sender reputation clean by suppressing dead
// addresses so we never re-send to them.
Route::post('/webhooks/ses', [SesNotificationController::class, 'handle'])
    ->middleware('throttle:webhook-ses')
    ->name('webhooks.ses');

// Sidecar callbacks — HMAC-signed, loopback only in practice (but signed anyway).
Route::post('/wa/webhook', WhatsappWebhookController::class)
    ->middleware('wa.webhook')
    ->name('webhooks.whatsapp');
