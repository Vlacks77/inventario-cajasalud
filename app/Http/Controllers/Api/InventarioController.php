<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicamento;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    /**
     * Stock actual consolidado por producto.
     */
    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));
        $partida = trim($request->query('partida', ''));
        $grupo = trim($request->query('grupo', ''));
        $estadoStock = trim($request->query('estado_stock', ''));
        $vencimiento = trim($request->query('vencimiento', ''));

        $productos = Medicamento::with([
                'partidaPresupuestaria:id,codigo,nombre',
                'lotes' => function ($query) {
                    $query->where('cantidad_actual', '>', 0)
                        ->with(['proveedor:id,nombre', 'ingreso:id,fecha_ingreso'])
                        ->orderByRaw('fecha_vencimiento IS NULL')
                        ->orderBy('fecha_vencimiento')
                        ->orderBy('id');
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
                $query->whereHas('partidaPresupuestaria', fn ($q) => $q->where('codigo', $partida));
            })
            ->when($grupo !== '', fn ($query) => $query->where('grupo_producto', $grupo))
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
                $vencimientoEstado = $fecha->lt($hoy)
                    ? 'VENCIDO'
                    : ($fecha->lte($limiteVencimiento) ? 'PROXIMO' : 'VIGENTE');
            }

            return [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'concentracion' => $producto->concentracion,
                'forma_farmaceutica' => $producto->forma_farmaceutica,
                'unidad_presentacion' => $producto->unidad_presentacion,
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
                    'fecha_ingreso' => $lote->ingreso?->fecha_ingreso?->format('Y-m-d'),
                    'fecha_vencimiento' => $lote->fecha_vencimiento?->format('Y-m-d'),
                    'cantidad_actual' => (int) $lote->cantidad_actual,
                    'precio_unitario' => $lote->precio_unitario,
                    'valor_actual' => number_format(
                        ((int) $lote->cantidad_actual) * (float) $lote->precio_unitario,
                        2,
                        '.',
                        ''
                    ),
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
}
