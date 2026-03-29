<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\BiometricController;

Route::get('/',          fn() => redirect()->route('login'));
Route::get('/login',     fn() => view('auth.login'))->name('login');
Route::get('/register',  fn() => view('auth.registro'))->name('register');

Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/login',    [AuthController::class, 'login'])->name('login.post');

Route::get('/usuario/configuracion', function(){return view('/usuario.actualizacion');})->name('usuario.configuracion');
Route::post('/usuario/update', [UsuarioController::class, 'modificarUsuario'])->name('usuario.update');

Route::get('/biometria/registrar', [BiometricController::class,'viewRegister'])->name('biometria.registrar');
Route::post('/biometria/save', [BiometricController::class,'saveCredential'])->name('biometria.save');
Route::post('/biometria/login', [BiometricController::class, 'login'])->name('biometria.login');
Route::get('/biometria/challenge', [BiometricController::class,'getChallenge'])->name('biometria.challenge');

Route::middleware('auth.token')->group(function () {
    Route::post('/logout',    [AuthController::class, 'logout'])->name('logout');
    Route::get('/chat',       [ChatController::class, 'index'])->name('chat');
    Route::get('/usuarios',   [ChatController::class, 'usuarios']);
    Route::get('/chat/{id}',  [ChatController::class, 'conversacion']);
    Route::post('/chat/{id}', [ChatController::class, 'enviar']);
    Route::post('/firebase-token',   [ChatController::class, 'updateFirebaseToken']);
});