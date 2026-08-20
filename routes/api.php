<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\KardexController;
use App\Http\Controllers\Api\SalidaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EstablecimientoController;
use App\Http\Controllers\Api\MedicamentoController;
use App\Http\Controllers\Api\LoteController;

// Autenticación
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Inventario
Route::middleware('auth:sanctum')
    ->get('/inventario', [InventarioController::class, 'index']);
Route::middleware('auth:sanctum')
    ->get('/kardex', [KardexController::class, 'index']);

// Establecimientos
Route::middleware('auth:sanctum')
    ->get('/establecimientos', [EstablecimientoController::class, 'index']);

// Medicamentos
Route::middleware('auth:sanctum')
    ->get('/medicamentos', [MedicamentoController::class, 'index']);

// Lotes disponibles por medicamento
Route::middleware('auth:sanctum')
    ->get('/medicamentos/{medicamento}/lotes', [LoteController::class, 'index']);

// Ingresos
Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])
    ->post('/ingresos', [IngresoController::class, 'store']);
Route::middleware('auth:sanctum')
    ->get('/ingresos/{ingreso}/pdf', [IngresoController::class, 'pdf']);

// Salidas
Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])
    ->get('/salidas/siguiente-numero', [SalidaController::class, 'siguienteNumero']);

Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])
    ->post('/salidas', [SalidaController::class, 'store']);
