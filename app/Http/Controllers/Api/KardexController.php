<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetalleSalida;
use App\Models\Lote;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    /**
     * Devuelve una línea cronológica única de ingresos y salidas.
     * Sin filtros: últimos 10 movimientos. Con filtros: hasta 300.
     */
    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));
        $procedencia = trim($request->query('procedencia', ''));
        $tieneFiltros = $buscar !== '' || $procedencia !== ''
            || $request->filled('fecha_desde') || $request->filled('fecha_hasta');
        $limite = $tieneFiltros ? 300 : 10;

        $ingresos = Lote::with(['medicamento.partidaPresupuestaria', 'proveedor', 'ingreso.usuario'])
            ->whereNotNull('ingreso_id')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->whereHas('medicamento', function ($producto) use ($buscar) {
                    $producto->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('grupo_producto', 'like', "%{$buscar}%");
                });
            })
            ->when($procedencia !== '', fn ($query) => $query->whereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$procedencia}%")))
            ->when($request->filled('fecha_desde'), fn ($query) => $query->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '>=', $request->query('fecha_desde'))))
            ->when($request->filled('fecha_hasta'), fn ($query) => $query->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '<=', $request->query('fecha_hasta'))))
            ->orderByDesc(\App\Models\Ingreso::select('fecha_ingreso')->whereColumn('ingresos.id', 'lotes.ingreso_id')->limit(1))
            ->orderByDesc('lotes.id')
            ->limit($limite)
            ->get()
            ->map(function ($lote) {
                return [
                    'id' => 'I-'.$lote->id,
                    'tipo' => 'INGRESO',
                    'fecha' => optional($lote->ingreso)->fecha_ingreso,
                    'registrado_en' => optional($lote->ingreso)->created_at,
                    'usuario' => optional(optional($lote->ingreso)->usuario)->name,
                    'usuario_username' => optional(optional($lote->ingreso)->usuario)->username,
                    'referencia' => optional($lote->ingreso)->numero_nota,
                    'documento' => optional($lote->ingreso)->numero_remision,
                    'partida' => optional($lote->medicamento->partidaPresupuestaria)->codigo,
                    'codigo' => optional($lote->medicamento)->codigo,
                    'producto' => optional($lote->medicamento)->nombre,
                    'forma' => optional($lote->medicamento)->forma_farmaceutica,
                    'procedencia' => optional($lote->proveedor)->nombre,
                    'lote' => $lote->codigo_lote,
                    'vencimiento' => $lote->fecha_vencimiento,
                    'cantidad' => $lote->cantidad_inicial,
                    'precio_unitario' => $lote->precio_unitario,
                    'total' => $lote->importe_total,
                ];
            });

        $salidas = DetalleSalida::with(['salida.establecimiento', 'salida.usuario', 'lote.medicamento.partidaPresupuestaria'])
            ->whereHas('salida', fn ($q) => $q->where('estado', 'ACTIVA'))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->whereHas('lote.medicamento', function ($producto) use ($buscar) {
                    $producto->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('grupo_producto', 'like', "%{$buscar}%");
                });
            })
            ->when($procedencia !== '', fn ($query) => $query->whereHas('salida.establecimiento', fn ($e) => $e->where('nombre', 'like', "%{$procedencia}%")))
            ->when($request->filled('fecha_desde'), fn ($query) => $query->whereHas('salida', fn ($s) => $s->whereDate('fecha_salida', '>=', $request->query('fecha_desde'))))
            ->when($request->filled('fecha_hasta'), fn ($query) => $query->whereHas('salida', fn ($s) => $s->whereDate('fecha_salida', '<=', $request->query('fecha_hasta'))))
            ->orderByDesc(\App\Models\Salida::select('fecha_salida')->whereColumn('salidas.id', 'detalle_salidas.salida_id')->limit(1))
            ->orderByDesc('detalle_salidas.id')
            ->limit($limite)
            ->get()
            ->map(function ($detalle) {
                $lote = $detalle->lote;
                $salida = $detalle->salida;
                return [
                    'id' => 'S-'.$detalle->id,
                    'tipo' => 'SALIDA',
                    'fecha' => optional($salida)->fecha_salida,
                    'registrado_en' => optional($salida)->created_at,
                    'usuario' => optional(optional($salida)->usuario)->name,
                    'usuario_username' => optional(optional($salida)->usuario)->username,
                    'referencia' => optional($salida)->numero_salida ? 'N.º '.optional($salida)->numero_salida : '—',
                    'documento' => optional($salida)->numero_pedido,
                    'partida' => optional($lote?->medicamento?->partidaPresupuestaria)->codigo,
                    'codigo' => optional($lote?->medicamento)->codigo,
                    'producto' => optional($lote?->medicamento)->nombre,
                    'forma' => optional($lote?->medicamento)->forma_farmaceutica,
                    'procedencia' => optional($salida?->establecimiento)->nombre,
                    'lote' => optional($lote)->codigo_lote,
                    'vencimiento' => optional($lote)->fecha_vencimiento,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => optional($lote)->precio_unitario,
                    'total' => ((float) $detalle->cantidad) * ((float) optional($lote)->precio_unitario),
                ];
            });

        $movimientos = $ingresos->merge($salidas)
            ->sortByDesc(fn ($fila) => sprintf('%s-%s', $fila['fecha'] ?? '', $fila['id']))
            ->values()
            ->take($limite)
            ->values();

        return response()->json($movimientos);
    }
}
