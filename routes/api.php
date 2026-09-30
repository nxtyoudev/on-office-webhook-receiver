<?php

use App\Http\Controllers\WebhookReceiverController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/{slug}', WebhookReceiverController::class)
    ->name('webhooks.receive');
