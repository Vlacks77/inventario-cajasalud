<?php

use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\SalidaController; // Agrega esto
use Illuminate\Support\Facades\Route;

Route::post('/ingresos', [IngresoController::class, 'store']);
Route::get('/inventario', [InventarioController::class, 'index']);
Route::post('/salidas', [SalidaController::class, 'store']); // Y agrega esto
