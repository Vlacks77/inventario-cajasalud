<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicamento;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    /**
     * Stock actual consolidado por producto.
     *
     * Este endpoint no modifica inventario. Lee la cantidad_actual de cada lote
     * y calcula el stock disponible y el próximo vencimiento.
     */
    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));
        $partida = trim($request->query('partida', ''));
        $tipo = trim($request->query('tipo', ''));
        $estadoStock = trim($request->query('estado_stock', ''));
        $vencimiento = trim($request->query('vencimiento', ''));

        $productos = Medicamento::with([
                'partidaPresupuestaria:id,codigo,nombre',
                'lotes' => function ($query) {
                    $query->where('cantidad_actual', '>', 0)
                        ->with('proveedor:id,nombre')
                        ->orderBy('fecha_vencimiento');
                },
            ])
            ->where('estado', true)
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('concentracion', 'like', "%{$buscar}%")
                        ->orWhere('grupo_producto', 'like', "%{$buscar}%");
                });
            })
            ->when($partida !== '', function ($query) use ($partida) {
                $query->whereHas('partidaPresupuestaria', function ($q) use ($partida) {
                    $q->where('codigo', $partida);
                });
            })
            ->when($tipo !== '', fn ($query) => $query->where('tipo_producto', $tipo))
            ->orderBy('nombre')
            ->get();

        $hoy = now()->startOfDay();
        $limiteVencimiento = $hoy->copy()->addDays(90);

        $resultado = $productos->map(function ($producto) use ($hoy, $limiteVencimiento) {
            $stockTotal = (int) $producto->lotes->sum('cantidad_actual');
            $proximo = $producto->lotes->first()?->fecha_vencimiento;

            $estado = 'NORMAL';
            if ($stockTotal <= 0) {
                $estado = 'SIN_STOCK';
            } elseif ((int) $producto->stock_minimo > 0 && $stockTotal <= (int) $producto->stock_minimo) {
                $estado = 'STOCK_BAJO';
            }

            $vencimientoEstado = 'SIN_VENCIMIENTO';
            if ($proximo) {
                $fecha = $proximo->copy()->startOfDay();
                if ($fecha->lt($hoy)) {
                    $vencimientoEstado = 'VENCIDO';
                } elseif ($fecha->lte($limiteVencimiento)) {
                    $vencimientoEstado = 'PROXIMO';
                } else {
                    $vencimientoEstado = 'VIGENTE';
                }
            }

            return [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'concentracion' => $producto->concentracion,
                'forma_farmaceutica' => $producto->forma_farmaceutica,
                'unidad_presentacion' => $producto->unidad_presentacion,
                'tipo_producto' => $producto->tipo_producto,
                'grupo_producto' => $producto->grupo_producto,
                'stock_minimo' => (int) $producto->stock_minimo,
                'stock_total' => $stockTotal,
                'proximo_vencimiento' => $proximo?->format('Y-m-d'),
                'estado_stock' => $estado,
                'estado_vencimiento' => $vencimientoEstado,
                'partida' => $producto->partidaPresupuestaria ? [
                    'codigo' => $producto->partidaPresupuestaria->codigo,
                    'nombre' => $producto->partidaPresupuestaria->nombre,
                ] : null,
                'lotes' => $producto->lotes->map(fn ($lote) => [
                    'id' => $lote->id,
                    'codigo_lote' => $lote->codigo_lote,
                    'fecha_vencimiento' => $lote->fecha_vencimiento?->format('Y-m-d'),
                    'cantidad_actual' => (int) $lote->cantidad_actual,
                    'precio_unitario' => $lote->precio_unitario,
                    'proveedor' => $lote->proveedor?->nombre,
                ])->values(),
            ];
        });

        if ($estadoStock !== '') {
            $resultado = $resultado->filter(fn ($producto) => $producto['estado_stock'] === $estadoStock);
        }

        if ($vencimiento !== '') {
            $resultado = $resultado->filter(fn ($producto) => $producto['estado_vencimiento'] === $vencimiento);
        }

        return response()->json($resultado->values()->all());
    }

    /** Consulta histórica de movimientos de ingreso para el Kardex. */
    public function kardex(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));
        $procedencia = trim($request->query('procedencia', ''));

        $lotes = \App\Models\Lote::with(['medicamento.partidaPresupuestaria', 'proveedor', 'ingreso'])
            ->whereNotNull('ingreso_id')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->whereHas('medicamento', function ($producto) use ($buscar) {
                    $producto->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('concentracion', 'like', "%{$buscar}%");
                });
            })
            ->when($procedencia !== '', fn ($query) => $query->whereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$procedencia}%")))
            ->when($request->filled('fecha_desde'), fn ($query) => $query->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '>=', $request->query('fecha_desde'))))
            ->when($request->filled('fecha_hasta'), fn ($query) => $query->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '<=', $request->query('fecha_hasta'))))
            ->latest('id')->limit(300)->get();

        return response()->json($lotes);
    }
}
