<?php

namespace App\Services;


use App\Models\Salida;
use App\Models\Lote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
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

            // El número de salida es correlativo mensual (se resetea cada mes iniciando en 1)
            $fechaSalida = Carbon::parse($datos['fecha_salida']);
            $maxNumero = Salida::whereYear('fecha_salida', $fechaSalida->year)
                ->whereMonth('fecha_salida', $fechaSalida->month)
                ->lockForUpdate()
                ->max('numero_salida');

            $numeroSalida = ((int) $maxNumero) + 1;

            // Crear la cabecera de la salida
            $salida = Salida::create([
                'fecha_salida'      => $datos['fecha_salida'],
                'almacen_origen'    => $datos['almacen_origen'],
                'numero_salida'     => $numeroSalida,
                'numero_pedido'     => $datos['numero_pedido'] ?? null,
                'establecimiento_id'=> $datos['establecimiento_id'],
                'solicitado_por'    => $datos['solicitado_por'],
                'entregado_a'       => $datos['entregado_a'] ?? null,
                'observaciones'     => $datos['observaciones'] ?? null,
                'estado'            => 'ACTIVA',
                'usuario_id'        => Auth::id(),
            ]);

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