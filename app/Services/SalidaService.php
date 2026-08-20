<?php

namespace App\Services;


use App\Models\Salida;
use App\Models\Lote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class SalidaService
{
    /**
     * Registra una salida completa con sus detalles
     * y descuenta el stock de los lotes correspondientes.
     */
    public function registrar(array $datos): Salida
    {
        return DB::transaction(function () use ($datos) {

            // Crear la cabecera de la salida
            $salida = Salida::create([
                'fecha_salida'      => $datos['fecha_salida'],
                'numero_pedido'     => $datos['numero_pedido'] ?? null,
                'establecimiento_id'=> $datos['establecimiento_id'],
                'solicitado_por'    => $datos['solicitado_por'],
                'entregado_a'       => $datos['entregado_a'] ?? null,
                'observaciones'    => $datos['observaciones'] ?? null,
                'estado'            => 'ACTIVA',
                'usuario_id'        => Auth::id(),
            ]);

            // El número de salida es correlativo y queda ligado al ID
            // interno de la salida, garantizando unicidad incluso con
            // registros concurrentes.
            $salida->numero_salida = $salida->id;
            $salida->save();

            // Procesar cada medicamento de la salida
            foreach ($datos['detalle'] as $item) {

                // Bloqueamos el lote durante la transacción
                // para evitar problemas de concurrencia.
                $lote = Lote::where('id', $item['lote_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // Verificar stock disponible
                if ($lote->cantidad_actual < $item['cantidad']) {
                    throw ValidationException::withMessages([
                        'detalle' => [
                            "Stock insuficiente para el lote {$lote->codigo_lote}. "
                            . "Disponible: {$lote->cantidad_actual}. "
                            . "Solicitado: {$item['cantidad']}."
                        ]
                    ]);
                }

                // Descontar stock
                $lote->cantidad_actual -= $item['cantidad'];
                $lote->save();

                // Crear detalle de la salida
                $salida->detalles()->create([
                    'lote_id'  => $lote->id,
                    'cantidad' => $item['cantidad'],
                ]);
            }

            return $salida->load([
                'establecimiento',
                'usuario',
                'detalles.lote.medicamento',
            ]);
        });
    }
}