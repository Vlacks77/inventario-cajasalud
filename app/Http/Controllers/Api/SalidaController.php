<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CierreMensual;
use App\Models\Salida;
use App\Models\Establecimiento;
use App\Services\SalidaService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalidaController extends Controller
{
    /**
     * Devuelve el siguiente número correlativo de salida para el mes correspondiente.
     * La numeración de salidas se resetea cada mes iniciando en 1.
     */
    public function siguienteNumero(Request $request)
    {
        $fecha = $request->query('fecha', now()->toDateString());
        $carbon = Carbon::parse($fecha);

        $max = Salida::whereYear('fecha_salida', $carbon->year)
            ->whereMonth('fecha_salida', $carbon->month)
            ->max('numero_salida');

        $siguiente = ((int) $max) + 1;

        return response()->json([
            'numero_salida' => $siguiente,
            'periodo'       => $carbon->format('Y-m'),
        ]);
    }

    public function show(int $id)
    {
        $salida = Salida::with([
            'establecimiento',
            'detalles.lote.medicamento.partidaPresupuestaria',
            'usuario'
        ])->findOrFail($id);

        $fecha = Carbon::parse($salida->fecha_salida);
        $periodoCerrado = CierreMensual::whereYear('periodo', $fecha->year)
            ->whereMonth('periodo', $fecha->month)
            ->where('estado', 'CERRADO')
            ->exists();

        $esReembolso = $salida->tipo_salida === 'EGRESO_REEMBOLSO';

        return response()->json([
            'id'                      => $salida->id,
            'numero_salida'           => $salida->numero_salida,
            'fecha_salida'            => $salida->fecha_salida?->format('Y-m-d') ?? (string) $salida->fecha_salida,
            'almacen_origen'          => $salida->almacen_origen,
            'establecimiento_id'      => $salida->establecimiento_id,
            'destino_establecimiento' => $salida->establecimiento?->nombre,
            'numero_pedido'           => $salida->numero_pedido,
            'solicitado_por'          => $salida->solicitado_por,
            'entregado_a'             => $salida->entregado_a,
            'observaciones'           => $salida->observaciones,
            'tipo_salida'             => $salida->tipo_salida,
            'periodo_cerrado'         => $periodoCerrado,
            'puede_editar'            => !$periodoCerrado && !$esReembolso,
            'detalles'                => $salida->detalles->map(function ($d) {
                $lote = $d->lote;
                $med = $lote?->medicamento;
                $stockDisponible = ($lote ? (int) $lote->cantidad_actual : 0) + (int) $d->cantidad;
                $pu = (float) ($d->precio_unitario_reembolso ?? $lote?->precio_unitario ?? 0);

                return [
                    'id'               => $d->id,
                    'lote_id'          => $d->lote_id,
                    'cantidad'         => (int) $d->cantidad,
                    'stock_disponible' => $stockDisponible,
                    'codigo_lote'      => $lote?->codigo_lote,
                    'fecha_vencimiento'=> $lote?->fecha_vencimiento?->format('Y-m-d'),
                    'precio_unitario'  => $pu,
                    'subtotal'         => round((float) $d->cantidad * $pu, 2),
                    'medicamento'      => $med ? [
                        'id'                  => $med->id,
                        'codigo'              => $med->codigo,
                        'nombre'              => $med->nombre,
                        'concentracion'       => $med->concentracion,
                        'forma_farmaceutica'  => $med->forma_farmaceutica,
                        'partida'             => $med->partidaPresupuestaria?->codigo,
                    ] : null,
                ];
            }),
        ]);
    }

    public function store(Request $request, SalidaService $salidaService)
    {
        $datos = $this->validarDatos($request);
        $datos['establecimiento_id'] = $this->resolverEstablecimientoId($datos);

        $salida = $salidaService->registrar($datos);

        return response()->json([
            'message' => 'Salida registrada correctamente.',
            'salida' => $salida,
        ], 201);
    }

    public function update(Request $request, int $id, SalidaService $salidaService)
    {
        $salida = Salida::findOrFail($id);
        $datos = $this->validarDatosActualizacion($request, $salida);
        if (isset($datos['destino_establecimiento']) || isset($datos['establecimiento_id'])) {
            $datos['establecimiento_id'] = $this->resolverEstablecimientoId($datos);
        }

        $salidaActualizada = $salidaService->actualizar($salida, $datos);

        return response()->json([
            'success' => true,
            'message' => 'Nota de salida N.º ' . $salidaActualizada->numero_salida . ' modificada correctamente.',
            'salida'  => $salidaActualizada,
        ]);
    }

    private function validarDatos(Request $request): array
    {
        return $request->validate([
            'fecha_salida' => 'required|date',
            'almacen_origen' => 'required|string|max:150',
            'establecimiento_id' => 'nullable|exists:establecimientos,id',
            'destino_establecimiento' => 'required_without:establecimiento_id|nullable|string|max:150',
            'numero_pedido' => 'nullable|string|max:100',
            'solicitado_por' => 'required|string|max:255',
            'entregado_a' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',

            'detalle' => 'required|array|min:1',
            'detalle.*.lote_id' => 'required|exists:lotes,id',
            'detalle.*.cantidad' => 'required|integer|min:1',
        ]);
    }

    private function validarDatosActualizacion(Request $request, Salida $salida): array
    {
        return $request->validate([
            'fecha_salida' => 'sometimes|required|date',
            'almacen_origen' => 'sometimes|required|string|max:150',
            'establecimiento_id' => 'nullable|exists:establecimientos,id',
            'destino_establecimiento' => 'nullable|string|max:150',
            'numero_pedido' => 'nullable|string|max:100',
            'solicitado_por' => 'sometimes|required|string|max:255',
            'entregado_a' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',

            'detalle' => 'required|array|min:1',
            'detalle.*.lote_id' => 'required|exists:lotes,id',
            'detalle.*.cantidad' => 'required|integer|min:1',
        ]);
    }

    private function resolverEstablecimientoId(array $datos): int
    {
        if (!empty($datos['establecimiento_id'])) {
            return (int) $datos['establecimiento_id'];
        }

        $nombreDestino = trim((string) ($datos['destino_establecimiento'] ?? ''));
        $normalizar = static function (string $valor): string {
            $valor = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor) ?: $valor;
            $valor = strtolower($valor);
            return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $valor));
        };

        $buscado = $normalizar($nombreDestino);
        $establecimiento = Establecimiento::where('estado', true)
            ->get(['id', 'nombre'])
            ->first(function ($item) use ($buscado, $normalizar) {
                return $normalizar($item->nombre) === $buscado;
            });

        if (!$establecimiento && $buscado !== '') {
            $palabras = array_values(array_filter(explode(' ', $buscado), fn($p) => strlen($p) >= 3));
            $mejor = null;
            $mejorPuntaje = 0;

            foreach (Establecimiento::where('estado', true)->get(['id', 'nombre']) as $item) {
                $catalogo = array_values(array_filter(explode(' ', $normalizar($item->nombre)), fn($p) => strlen($p) >= 3));
                if (!$catalogo || !$palabras) continue;
                $coincidencias = count(array_intersect($palabras, $catalogo));
                $coberturaBusqueda = $coincidencias / count(array_unique($palabras));
                $coberturaCatalogo = $coincidencias / count(array_unique($catalogo));
                $puntaje = ($coberturaBusqueda * 0.75) + ($coberturaCatalogo * 0.25);
                if ($puntaje > $mejorPuntaje) {
                    $mejorPuntaje = $puntaje;
                    $mejor = $item;
                }
            }

            $establecimiento = $mejorPuntaje >= 0.50 ? $mejor : null;
        }

        if (!$establecimiento) {
            $nombreFinal = $nombreDestino !== '' ? $nombreDestino : 'UNIDAD GENERAL';
            $establecimiento = Establecimiento::firstOrCreate(
                ['nombre' => $nombreFinal],
                ['tipo' => 'UNIDAD SOLICITANTE', 'estado' => true]
            );
        }

        return (int) $establecimiento->id;
    }
}
