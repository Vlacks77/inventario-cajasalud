<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReembolsoRequest;
use App\Models\Ingreso;
use App\Services\ReembolsoInventarioService;
use Illuminate\Http\JsonResponse;

/**
 * ReembolsoController
 * ─────────────────────────────────────────────────────────────────────────────
 * Gestiona el registro de Reembolsos Mensuales de Medicamentos.
 *
 * Endpoints:
 *   POST   /api/reembolsos          → store()   registra el reembolso completo
 *   GET    /api/reembolsos/{id}     → show()    consulta un reembolso por ID de ingreso
 */
class ReembolsoController extends Controller
{
    public function __construct(
        private readonly ReembolsoInventarioService $servicio
    ) {}

    /**
     * Registra el reembolso mensual completo:
     *   1. Nota de Ingreso con lotes virtuales (es_reembolso = true).
     *   2. Nota de Egreso consolidada con CPP por medicamento.
     */
    public function store(StoreReembolsoRequest $request): JsonResponse
    {
        $resultado = $this->servicio->procesar($request->validated());

        /** @var \App\Models\Ingreso $ingreso */
        $ingreso = $resultado['ingreso'];
        /** @var \App\Models\Salida $salida */
        $salida  = $resultado['salida'];

        $totalItems      = $ingreso->lotes->count();
        $totalImporte    = $ingreso->lotes->sum('importe_total');
        $totalEgreso     = $salida->detalles->count();

        return response()->json([
            'success'  => true,
            'message'  => "Reembolso {$ingreso->numero_nota} registrado correctamente. "
                        . "{$totalItems} ítem(s) ingresados; {$totalEgreso} línea(s) consolidada(s) en egreso.",
            'ingreso'  => [
                'id'           => $ingreso->id,
                'numero_nota'  => $ingreso->numero_nota,
                'tipo_ingreso' => $ingreso->tipo_ingreso,
                'fecha'        => $ingreso->fecha_ingreso,
                'total_items'  => $totalItems,
                'importe_total'=> round((float) $totalImporte, 2),
            ],
            'salida'   => [
                'id'             => $salida->id,
                'numero_salida'  => $salida->numero_salida,
                'tipo_salida'    => $salida->tipo_salida,
                'lineas_egreso'  => $totalEgreso,
            ],
        ], 201);
    }

    /**
     * Consulta el detalle de un reembolso ya registrado.
     * Recibe el ID del ingreso tipo 'reembolso'.
     */
    public function show(Ingreso $ingreso): JsonResponse
    {
        if ($ingreso->tipo_ingreso !== 'reembolso') {
            return response()->json([
                'message' => 'El ingreso indicado no es de tipo reembolso.',
            ], 422);
        }

        $ingreso->load([
            'proveedor',
            'usuario:id,name',
            'lotes.medicamento:id,codigo,nombre,forma_farmaceutica',
        ]);

        // Cargamos el egreso asociado (si existe)
        $salida = \App\Models\Salida::with([
                'detalles.lote.medicamento:id,codigo,nombre',
            ])
            ->where('movimiento_origen_id', $ingreso->id)
            ->where('tipo_salida', 'EGRESO_REEMBOLSO')
            ->first();

        return response()->json([
            'ingreso' => [
                'id'             => $ingreso->id,
                'numero_nota'    => $ingreso->numero_nota,
                'tipo_ingreso'   => $ingreso->tipo_ingreso,
                'fecha_ingreso'  => $ingreso->fecha_ingreso,
                'almacen'        => $ingreso->almacen,
                'observacion'    => $ingreso->observacion,
                'recibido_por'   => $ingreso->recibido_por,
                'proveedor'      => $ingreso->proveedor?->nombre,
                'registrado_por' => $ingreso->usuario?->name,
                'items'          => $ingreso->lotes->map(fn ($l) => [
                    'lote_id'        => $l->id,
                    'codigo_lote'    => $l->codigo_lote,
                    'medicamento'    => $l->medicamento?->nombre,
                    'medicamento_id' => $l->medicamento_id,
                    'cantidad'       => $l->cantidad_inicial,
                    'precio_unit'    => (float) $l->precio_unitario,
                    'importe'        => (float) $l->importe_total,
                    'es_reembolso'   => (bool) $l->es_reembolso,
                    'stock_actual'   => $l->cantidad_actual,
                ]),
            ],
            'egreso' => $salida ? [
                'id'            => $salida->id,
                'numero_salida' => $salida->numero_salida,
                'tipo_salida'   => $salida->tipo_salida,
                'fecha_salida'  => $salida->fecha_salida,
                'lineas'        => $salida->detalles->map(fn ($d) => [
                    'detalle_id'   => $d->id,
                    'medicamento'  => $d->lote?->medicamento?->nombre,
                    'cantidad'     => $d->cantidad,
                    'cpp'          => (float) $d->precio_unitario_reembolso,
                    'importe'      => round((float) $d->cantidad * (float) $d->precio_unitario_reembolso, 2),
                ]),
            ] : null,
        ]);
    }
}
