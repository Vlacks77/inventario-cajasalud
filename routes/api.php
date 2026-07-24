<?php

use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\InventarioController;
use Illuminate\Support\Facades\Route;

Route::post('/ingresos', [IngresoController::class, 'store'])
    ->name('api.ingresos.store');

Route::get('/inventario', [InventarioController::class, 'index'])
    ->name('api.inventario.index');
