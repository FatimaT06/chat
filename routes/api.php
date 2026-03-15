<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UsuarioController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout',    [AuthController::class, 'logout']);
    Route::get('/usuarios',   [ChatController::class, 'usuarios']);
    Route::get('/chat/{id}',  [ChatController::class, 'conversacion']);
    Route::post('/chat/{id}', [ChatController::class, 'enviar']);
    Route::post('/usuario/update', [UsuarioController::class, 'modificarUsuario']);
});