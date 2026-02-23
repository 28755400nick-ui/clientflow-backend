<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas (sin autenticación)
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::post('/refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Rutas protegidas (requieren JWT válido)
|--------------------------------------------------------------------------
*/

Route::middleware('jwt')->group(function () {

    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Clientes — rutas estáticas antes de las rutas con parámetro {id}
    Route::get('/clients',         [ClientController::class, 'index']);
    Route::get('/clients/export',  [ClientController::class, 'export']);
    Route::post('/clients',        [ClientController::class, 'store']);
    Route::put('/clients/{id}',    [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);
});
