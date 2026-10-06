<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salida;
use App\Models\Establecimiento;
use App\Services\SalidaService;
use Illuminate\Http\Request;

class SalidaController extends Controller
{
    public function siguienteNumero()
    {
        $siguiente = ((int) Salida::max('numero_salida')) + 1;

        return response()->json([
            'numero_salida' => $siguiente,
        ]);
    }

    public function store(Request $request, SalidaService $salidaService)
    {
        $datos = $request->validate([
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

        // El frontend muestra un catálogo oficial de unidades solicitantes,
        // pero el nombre puede variar ligeramente respecto al catálogo de BD.
        // Si ya llegó un ID válido lo respetamos; si no, resolvemos por nombre.
        // Si ya llegó un ID válido lo respetamos; si no, resolvemos por nombre o creamos la unidad.
        if (empty($datos['establecimiento_id'])) {
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
                // Si la unidad no está registrada en el catálogo, se crea automáticamente
                // para no bloquear el flujo operativo de almacén.
                $nombreFinal = $nombreDestino !== '' ? $nombreDestino : 'UNIDAD GENERAL';
                $establecimiento = Establecimiento::firstOrCreate(
                    ['nombre' => $nombreFinal],
                    ['tipo' => 'UNIDAD SOLICITANTE', 'estado' => true]
                );
            }

            $datos['establecimiento_id'] = $establecimiento->id;
        }

        $salida = $salidaService->registrar($datos);

        return response()->json([
            'message' => 'Salida registrada correctamente.',
            'salida' => $salida,
        ], 201);
    }
}
