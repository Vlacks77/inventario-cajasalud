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
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ProveedorController;

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

// Proveedores
Route::middleware('auth:sanctum')
    ->get('/proveedores', [ProveedorController::class, 'index']);
Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])
    ->post('/proveedores', [ProveedorController::class, 'store']);

// Ingresos
Route::middleware('auth:sanctum')
    ->get('/ingresos/siguiente-numero', [IngresoController::class, 'siguienteNumero']);
Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])
    ->post('/ingresos', [IngresoController::class, 'store']);
Route::middleware('auth:sanctum')
    ->get('/ingresos/{ingreso}/pdf', [IngresoController::class, 'pdf']);

// Salidas
Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])
    ->get('/salidas/siguiente-numero', [SalidaController::class, 'siguienteNumero']);

Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])
    ->post('/salidas', [SalidaController::class, 'store']);

// Reportes y regeneración de documentos
Route::middleware('auth:sanctum')->get('/reportes/ingresos', [ReporteController::class, 'ingresos']);
Route::middleware('auth:sanctum')->get('/reportes/ingresos/{ingreso}/excel', [ReporteController::class, 'ingresoExcel']);
Route::middleware('auth:sanctum')->get('/reportes/salidas', [ReporteController::class, 'salidas']);
Route::middleware('auth:sanctum')->get('/reportes/salidas/{salida}/pdf', [ReporteController::class, 'salidaPdf']);
Route::middleware('auth:sanctum')->get('/reportes/salidas/{salida}/excel', [ReporteController::class, 'salidaExcel']);
Route::middleware('auth:sanctum')->get('/reportes/inventario/pdf', [ReporteController::class, 'inventario']);
Route::middleware('auth:sanctum')->get('/reportes/inventario/excel', [ReporteController::class, 'inventarioExcel']);
Route::middleware('auth:sanctum')->get('/reportes/kardex/pdf', [ReporteController::class, 'kardex']);

// Cierre mensual y reporte institucional de inventario
use App\Http\Controllers\Api\CierreMensualController;
Route::middleware('auth:sanctum')->get('/cierres-mensuales', [CierreMensualController::class, 'index']);
Route::middleware('auth:sanctum')->get('/cierres-mensuales/preview', [CierreMensualController::class, 'preview']);
Route::middleware('auth:sanctum')->get('/cierres-mensuales/productos/{medicamento}/preview', [CierreMensualController::class, 'productoPreview']);
Route::middleware(['auth:sanctum', 'role:almacen,auxiliar,admin'])->post('/cierres-mensuales', [CierreMensualController::class, 'store']);
Route::middleware('auth:sanctum')->get('/cierres-mensuales/{cierreMensual}', [CierreMensualController::class, 'show']);
Route::middleware('auth:sanctum')->get('/cierres-mensuales/{cierreMensual}/pdf', [CierreMensualController::class, 'pdf']);
Route::middleware('auth:sanctum')->get('/cierres-mensuales/{cierreMensual}/excel', [CierreMensualController::class, 'excel']);
