<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\ConversationController;

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('conversations', [ConversationController::class, 'index'])->middleware('ability:chat.read');
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])
            ->middleware(['ability:chat.read', 'throttle:60,1']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
            ->middleware('ability:chat.reply');
        Route::post('conversations/{conversation}/take-over', [ConversationController::class, 'takeOver'])
            ->middleware('ability:chat.takeover');
        Route::post('conversations/{conversation}/release', [ConversationController::class, 'release'])
            ->middleware('ability:chat.takeover');
    });
