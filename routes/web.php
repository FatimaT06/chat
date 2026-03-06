<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;

Route::get('/',          fn() => redirect()->route('login'));
Route::get('/login',     fn() => view('auth.login'))->name('login');
Route::get('/register',  fn() => view('auth.registro'))->name('register');

Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/login',    [AuthController::class, 'login'])->name('login.post');

Route::middleware('auth.token')->group(function () {
    Route::post('/logout',    [AuthController::class, 'logout'])->name('logout');
    Route::get('/chat',       [ChatController::class, 'index'])->name('chat');
    Route::get('/usuarios',   [ChatController::class, 'usuarios']);
    Route::get('/chat/{id}',  [ChatController::class, 'conversacion']);
    Route::post('/chat/{id}', [ChatController::class, 'enviar']);
});