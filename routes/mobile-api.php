<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\AgentController;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\TriggerController;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\LearningController;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\VisitorsController;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\QuickReplyController;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\OpeningHourController;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\ChatSettingsController;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\ConversationController;

// Let op: chat-toegang loopt via `chat.ability:<recht>` (per site, per
// medewerker) i.p.v. de generieke token-`ability:`. Alleen geregistreerde
// livechat-medewerkers (of superadmin) komen erdoor. Configuratie-CRUD vereist
// het `chat.manage`-recht.
Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('visitors-live', [VisitorsController::class, 'live'])->middleware('chat.ability:chat.read');

        Route::get('conversations', [ConversationController::class, 'index'])->middleware('chat.ability:chat.read');
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->middleware('chat.ability:chat.read');
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])
            ->middleware(['chat.ability:chat.read', 'throttle:60,1']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
            ->middleware('chat.ability:chat.reply');
        Route::post('conversations/{conversation}/translate', [ConversationController::class, 'translate'])
            ->middleware(['chat.ability:chat.reply', 'throttle:30,1']);
        Route::post('conversations/{conversation}/take-over', [ConversationController::class, 'takeOver'])
            ->middleware('chat.ability:chat.takeover');
        Route::post('conversations/{conversation}/release', [ConversationController::class, 'release'])
            ->middleware('chat.ability:chat.takeover');
        Route::post('conversations/{conversation}/suggest-reply', [ConversationController::class, 'suggestReply'])
            ->middleware(['chat.ability:chat.reply', 'throttle:20,1']);
        Route::put('conversations/{conversation}/tags', [ConversationController::class, 'setTags'])
            ->middleware('chat.ability:chat.reply');
        Route::post('conversations/{conversation}/notes', [ConversationController::class, 'addNote'])
            ->middleware('chat.ability:chat.reply');

        Route::get('tags', [ConversationController::class, 'tags'])->middleware('chat.ability:chat.read');

        Route::get('agents', [AgentController::class, 'index'])->middleware('chat.ability:chat.manage');
        Route::get('agents/{agent}', [AgentController::class, 'show'])->middleware('chat.ability:chat.manage');
        Route::post('agents', [AgentController::class, 'store'])->middleware('chat.ability:chat.manage');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->middleware('chat.ability:chat.manage');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->middleware('chat.ability:chat.manage');

        Route::get('opening-hours', [OpeningHourController::class, 'index'])->middleware('chat.ability:chat.manage');
        Route::post('opening-hours', [OpeningHourController::class, 'store'])->middleware('chat.ability:chat.manage');
        Route::put('opening-hours/{openingHour}', [OpeningHourController::class, 'update'])->middleware('chat.ability:chat.manage');
        Route::delete('opening-hours/{openingHour}', [OpeningHourController::class, 'destroy'])->middleware('chat.ability:chat.manage');

        Route::get('triggers', [TriggerController::class, 'index'])->middleware('chat.ability:chat.manage');
        Route::get('triggers/{trigger}', [TriggerController::class, 'show'])->middleware('chat.ability:chat.manage');
        Route::post('triggers', [TriggerController::class, 'store'])->middleware('chat.ability:chat.manage');
        Route::put('triggers/{trigger}', [TriggerController::class, 'update'])->middleware('chat.ability:chat.manage');
        Route::delete('triggers/{trigger}', [TriggerController::class, 'destroy'])->middleware('chat.ability:chat.manage');

        Route::get('learnings', [LearningController::class, 'index'])->middleware('chat.ability:chat.manage');
        Route::post('learnings', [LearningController::class, 'store'])->middleware('chat.ability:chat.manage');
        Route::put('learnings/{learning}', [LearningController::class, 'update'])->middleware('chat.ability:chat.manage');
        Route::delete('learnings/{learning}', [LearningController::class, 'destroy'])->middleware('chat.ability:chat.manage');

        Route::get('quick-replies', [QuickReplyController::class, 'index'])->middleware('chat.ability:chat.read');

        Route::get('chat-settings', [ChatSettingsController::class, 'show'])->middleware('chat.ability:chat.manage');
        Route::put('chat-settings', [ChatSettingsController::class, 'update'])->middleware('chat.ability:chat.manage');
    });
