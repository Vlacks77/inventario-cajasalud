<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngresoRequest;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IngresoController extends Controller
{
    /** Registra un ingreso completo: medicamento, proveedor y lote. */
    public function store(StoreIngresoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $resultado = DB::transaction(function () use ($datos): array {
            // El código institucional identifica de manera única al medicamento.
            $medicamento = Medicamento::firstOrCreate(
                ['codigo' => $datos['medicamento']['codigo']],
                [
                    'nombre' => $datos['medicamento']['nombre'],
                    'concentracion' => $datos['medicamento']['concentracion'],
                    'forma_farmaceutica' => $datos['medicamento']['forma_farmaceutica'],
                    'unidad_presentacion' => $datos['medicamento']['unidad_presentacion'],
                    'stock_minimo' => $datos['medicamento']['stock_minimo'],
                    'descripcion' => $datos['medicamento']['descripcion'] ?? null,
                    'estado' => true,
                ]
            );

            // Mientras el NIT no sea único en la BD, el nombre es el criterio de búsqueda.
            $proveedor = Proveedor::firstOrCreate(
                ['nombre' => $datos['proveedor']['nombre']],
                [
                    'nit' => $datos['proveedor']['nit'] ?? null,
                    'contacto' => $datos['proveedor']['contacto'] ?? null,
                    'telefono' => $datos['proveedor']['telefono'] ?? null,
                    'direccion' => $datos['proveedor']['direccion'] ?? null,
                    'estado' => true,
                ]
            );

            // Un lote nuevo inicia con la totalidad de la cantidad recibida disponible.
            $lote = Lote::create([
                'medicamento_id' => $medicamento->id,
                'proveedor_id' => $proveedor->id,
                'codigo_lote' => $datos['lote']['codigo_lote'],
                'fecha_vencimiento' => $datos['lote']['fecha_vencimiento'],
                'cantidad_inicial' => $datos['lote']['cantidad'],
                'cantidad_actual' => $datos['lote']['cantidad'],
            ]);

            return compact('medicamento', 'proveedor', 'lote');
        });

        return response()->json([
            'message' => 'Ingreso registrado correctamente.',
            'data' => $resultado,
        ], 201);
    }
}
