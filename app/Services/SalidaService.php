<?php

namespace App\Services;


use App\Models\CierreMensual;
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

    /**
     * Actualiza una salida existente revirtiendo el stock previo,
     * validando que el periodo no esté cerrado ni sea de tipo EGRESO_REEMBOLSO,
     * aplicando el nuevo detalle y recalculando stock atómicamente.
     */
    public function actualizar(Salida $salida, array $datos): Salida
    {
        if ($salida->tipo_salida === 'EGRESO_REEMBOLSO') {
            throw ValidationException::withMessages([
                'tipo_salida' => ['No se permite editar salidas de tipo EGRESO_REEMBOLSO.']
            ]);
        }

        $fechaOriginal = Carbon::parse($salida->fecha_salida);
        $periodoOriginalCerrado = CierreMensual::whereYear('periodo', $fechaOriginal->year)
            ->whereMonth('periodo', $fechaOriginal->month)
            ->where('estado', 'CERRADO')
            ->exists();

        if ($periodoOriginalCerrado) {
            throw ValidationException::withMessages([
                'periodo'      => ['No se puede editar una salida perteneciente a un periodo mensual que ya ha sido cerrado.'],
                'fecha_salida' => ['No se puede editar una salida perteneciente a un periodo mensual que ya ha sido cerrado.']
            ]);
        }

        if (!empty($datos['fecha_salida'])) {
            $nuevaFecha = Carbon::parse($datos['fecha_salida']);
            if ($nuevaFecha->year !== $fechaOriginal->year || $nuevaFecha->month !== $fechaOriginal->month) {
                $periodoNuevoCerrado = CierreMensual::whereYear('periodo', $nuevaFecha->year)
                    ->whereMonth('periodo', $nuevaFecha->month)
                    ->where('estado', 'CERRADO')
                    ->exists();

                if ($periodoNuevoCerrado) {
                    throw ValidationException::withMessages([
                        'periodo'      => ['El nuevo periodo seleccionado ya ha sido cerrado.'],
                        'fecha_salida' => ['El nuevo periodo seleccionado ya ha sido cerrado.']
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($salida, $datos) {
            // a) Revertir el stock de los detalles actuales de la salida
            foreach ($salida->detalles as $detalle) {
                $lote = Lote::where('id', $detalle->lote_id)
                    ->lockForUpdate()
                    ->first();
                if ($lote) {
                    $lote->cantidad_actual += $detalle->cantidad;
                    $lote->save();
                }
            }

            // b) Eliminar los detalles anteriores de la salida
            $salida->detalles()->delete();

            // c) Procesar el nuevo listado de detalles: verificar stock disponible y descontarlo
            foreach ($datos['detalle'] as $item) {
                $lote = Lote::where('id', $item['lote_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lote->cantidad_actual < $item['cantidad']) {
                    $msg = "Stock insuficiente para el lote {$lote->codigo_lote}. "
                        . "Disponible: {$lote->cantidad_actual}. "
                        . "Solicitado: {$item['cantidad']}.";
                    throw ValidationException::withMessages([
                        'stock'   => [$msg],
                        'detalle' => [$msg],
                    ]);
                }

                $lote->cantidad_actual -= $item['cantidad'];
                $lote->save();

                // d) Crear los nuevos registros en detalle_salidas
                $salida->detalles()->create([
                    'lote_id'  => $lote->id,
                    'cantidad' => $item['cantidad'],
                ]);
            }

            // e) Actualizar campos de cabecera si cambiaron y registrar en observaciones:
            // "Modificado el {fecha_hora} por {usuario}"
            $usuario = Auth::user()?->name ?? Auth::user()?->username ?? 'Usuario';
            $fechaHora = now()->format('d/m/Y H:i');
            $notaModificacion = "Modificado el {$fechaHora} por {$usuario}";

            $obsInput = array_key_exists('observaciones', $datos) ? trim((string) ($datos['observaciones'] ?? '')) : trim((string) ($salida->observaciones ?? ''));
            if ($obsInput !== '') {
                $observacionesFinal = $obsInput . ' | ' . $notaModificacion;
            } else {
                $observacionesFinal = $notaModificacion;
            }

            $salida->update([
                'fecha_salida'       => $datos['fecha_salida'] ?? $salida->fecha_salida,
                'almacen_origen'     => $datos['almacen_origen'] ?? $salida->almacen_origen,
                'establecimiento_id' => $datos['establecimiento_id'] ?? $salida->establecimiento_id,
                'numero_pedido'      => array_key_exists('numero_pedido', $datos) ? $datos['numero_pedido'] : $salida->numero_pedido,
                'solicitado_por'     => $datos['solicitado_por'] ?? $salida->solicitado_por,
                'entregado_a'        => array_key_exists('entregado_a', $datos) ? $datos['entregado_a'] : $salida->entregado_a,
                'observaciones'      => $observacionesFinal,
            ]);

            return $salida->load([
                'establecimiento',
                'usuario',
                'detalles.lote.medicamento.partidaPresupuestaria',
            ]);
        });
    }
}