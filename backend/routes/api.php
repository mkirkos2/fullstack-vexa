<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\AiReplyController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('conversations', ConversationController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // Nested message routes
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])
        ->name('conversations.messages.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->name('conversations.messages.store');

    // AI reply route
    Route::post('/conversations/{conversation}/ai-reply', AiReplyController::class)
        ->name('conversations.ai-reply');
});
