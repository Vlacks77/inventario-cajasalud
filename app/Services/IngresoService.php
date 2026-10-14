<?php

namespace App\Services;

use App\Models\CierreMensual;
use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngresoService
{
    /**
     * Actualiza una nota de ingreso existente y sus lotes correspondientes,
     * garantizando la integridad de stock y el control de periodos contables.
     *
     * @param Ingreso $ingreso
     * @param array $datos
     * @return Ingreso
     * @throws ValidationException
     */
    public function actualizar(Ingreso $ingreso, array $datos): Ingreso
    {
        // 1. REGLA: No se permite editar ingresos de tipo 'apertura' ni 'reembolso'
        if (in_array($ingreso->tipo_ingreso, ['apertura', 'reembolso'])) {
            throw ValidationException::withMessages([
                'tipo_ingreso' => ["No se permite editar notas de ingreso de tipo '{$ingreso->tipo_ingreso}'."]
            ]);
        }

        // 2. REGLA: Solo se pueden editar ingresos de periodos abiertos
        $fechaOriginal = Carbon::parse($ingreso->fecha_ingreso);
        $periodoOriginalCerrado = CierreMensual::whereYear('periodo', $fechaOriginal->year)
            ->whereMonth('periodo', $fechaOriginal->month)
            ->where('estado', 'CERRADO')
            ->exists();

        if ($periodoOriginalCerrado) {
            throw ValidationException::withMessages([
                'periodo'       => ['No se puede editar un ingreso perteneciente a un periodo mensual que ya ha sido cerrado.'],
                'fecha_ingreso' => ['No se puede editar un ingreso perteneciente a un periodo mensual que ya ha sido cerrado.']
            ]);
        }

        // Validar si la nueva fecha pertenece a un periodo cerrado
        $nuevaFechaStr = $datos['ingreso']['fecha_ingreso'] ?? $datos['fecha_ingreso'] ?? null;
        if (!empty($nuevaFechaStr)) {
            $nuevaFecha = Carbon::parse($nuevaFechaStr);
            if ($nuevaFecha->year !== $fechaOriginal->year || $nuevaFecha->month !== $fechaOriginal->month) {
                $periodoNuevoCerrado = CierreMensual::whereYear('periodo', $nuevaFecha->year)
                    ->whereMonth('periodo', $nuevaFecha->month)
                    ->where('estado', 'CERRADO')
                    ->exists();

                if ($periodoNuevoCerrado) {
                    throw ValidationException::withMessages([
                        'periodo'       => ['El nuevo periodo seleccionado ya ha sido cerrado.'],
                        'fecha_ingreso' => ['El nuevo periodo seleccionado ya ha sido cerrado.']
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($ingreso, $datos) {
            // Resolver proveedor
            $proveedorId = $ingreso->proveedor_id;
            if (!empty($datos['proveedor']['nombre'])) {
                $prov = Proveedor::firstOrCreate(
                    ['nombre' => trim($datos['proveedor']['nombre'])],
                    array_filter(['telefono' => $datos['proveedor']['telefono'] ?? null], fn ($v) => $v !== null)
                );
                $proveedorId = $prov->id;
            } elseif (!empty($datos['proveedor_id'])) {
                $proveedorId = $datos['proveedor_id'];
            }

            // Cargar lotes actuales de la nota con bloqueo pesimista
            $lotesActuales = Lote::where('ingreso_id', $ingreso->id)
                ->with('detalleSalidas')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $itemsEnviados = $datos['items'] ?? [];

            // Recopilar IDs de lotes existentes que se mantienen
            $idsEnviados = [];
            foreach ($itemsEnviados as $it) {
                $id = $it['id'] ?? $it['lote_id'] ?? null;
                if ($id) {
                    $idsEnviados[] = (int) $id;
                }
            }

            // a) Ítems eliminados (existían en la nota pero ya no vienen en la petición)
            foreach ($lotesActuales as $loteId => $lote) {
                if (!in_array($loteId, $idsEnviados, true)) {
                    $despachadas = (int) $lote->cantidad_inicial - (int) $lote->cantidad_actual;
                    $tieneSalidas = $lote->detalleSalidas->isNotEmpty() || $despachadas > 0;

                    if ($tieneSalidas) {
                        throw ValidationException::withMessages([
                            'items' => ["No se puede eliminar el lote {$lote->codigo_lote} porque ya cuenta con despachos registrados."],
                            'lotes' => ["No se puede eliminar el lote {$lote->codigo_lote} porque ya cuenta con despachos registrados."]
                        ]);
                    }

                    $lote->delete();
                }
            }

            // b) Procesar ítems (actualizar existentes o crear nuevos)
            foreach ($itemsEnviados as $item) {
                $loteId = $item['id'] ?? $item['lote_id'] ?? null;
                $productoId = $item['producto_id'] ?? $item['medicamento_id'] ?? null;
                $codigoLote = trim((string) ($item['codigo_lote'] ?? $item['lote']['codigo_lote'] ?? ''));
                $fechaVenc = $item['fecha_vencimiento'] ?? $item['lote']['fecha_vencimiento'] ?? null;
                $cantidadNueva = (int) $item['cantidad'];
                $precioUnitario = (float) $item['precio_unitario'];
                $importeTotal = round($cantidadNueva * $precioUnitario, 2);

                if ($loteId && isset($lotesActuales[$loteId])) {
                    // Ítem existente a modificar
                    $lote = $lotesActuales[$loteId];
                    $despachadas = (int) $lote->cantidad_inicial - (int) $lote->cantidad_actual;
                    $tieneSalidas = $lote->detalleSalidas->isNotEmpty() || $despachadas > 0;

                    if ($tieneSalidas && $cantidadNueva < $despachadas) {
                        throw ValidationException::withMessages([
                            'items'    => ["No se puede reducir la cantidad del lote {$lote->codigo_lote} por debajo de las {$despachadas} unidades ya despachadas."],
                            'cantidad' => ["No se puede reducir la cantidad del lote {$lote->codigo_lote} por debajo de las {$despachadas} unidades ya despachadas."]
                        ]);
                    }

                    $cantidadActualNueva = $tieneSalidas ? ($cantidadNueva - $despachadas) : $cantidadNueva;

                    $lote->update([
                        'medicamento_id'    => $productoId ?: $lote->medicamento_id,
                        'proveedor_id'      => $proveedorId,
                        'codigo_lote'       => $codigoLote ?: $lote->codigo_lote,
                        'fecha_vencimiento' => $fechaVenc,
                        'cantidad_inicial'  => $cantidadNueva,
                        'cantidad_actual'   => $cantidadActualNueva,
                        'precio_unitario'   => $precioUnitario,
                        'importe_total'     => $importeTotal,
                    ]);
                } else {
                    // Ítem nuevo agregado a la nota
                    $producto = Medicamento::where('estado', true)->findOrFail($productoId);

                    Lote::create([
                        'ingreso_id'        => $ingreso->id,
                        'medicamento_id'    => $producto->id,
                        'proveedor_id'      => $proveedorId,
                        'usuario_id'        => Auth::id() ?? $ingreso->usuario_id,
                        'codigo_lote'       => $codigoLote,
                        'fecha_vencimiento' => $fechaVenc,
                        'cantidad_inicial'  => $cantidadNueva,
                        'cantidad_actual'   => $cantidadNueva,
                        'precio_unitario'   => $precioUnitario,
                        'importe_total'     => $importeTotal,
                        'es_reembolso'      => false,
                    ]);
                }
            }

            // c) Actualizar proveedor en todos los lotes de este ingreso para mantener consistencia
            Lote::where('ingreso_id', $ingreso->id)->update(['proveedor_id' => $proveedorId]);

            // d) Actualizar datos de cabecera y bitácora de auditoría
            $usuario = Auth::user()?->name ?? Auth::user()?->username ?? 'Usuario';
            $fechaHora = now()->format('d/m/Y H:i');
            $notaModificacion = "Modificado el {$fechaHora} por {$usuario}";

            $cabecera = $datos['ingreso'] ?? $datos;
            $obsInput = array_key_exists('observacion', $cabecera)
                ? trim((string) ($cabecera['observacion'] ?? ''))
                : (array_key_exists('observaciones', $cabecera) ? trim((string) ($cabecera['observaciones'] ?? '')) : trim((string) ($ingreso->observacion ?? '')));

            if ($obsInput !== '') {
                $observacionFinal = $obsInput . ' | ' . $notaModificacion;
            } else {
                $observacionFinal = $notaModificacion;
            }

            // El correlativo (numero_nota y correlativo_anual) NO se modifica
            $updateData = [
                'proveedor_id' => $proveedorId,
                'observacion'  => $observacionFinal,
            ];

            if (!empty($cabecera['almacen'])) {
                $updateData['almacen'] = $cabecera['almacen'];
            }
            if (!empty($cabecera['fecha_ingreso'])) {
                $updateData['fecha_ingreso'] = $cabecera['fecha_ingreso'];
            }
            if (array_key_exists('numero_remision', $cabecera)) {
                $updateData['numero_remision'] = $cabecera['numero_remision'];
            }
            if (array_key_exists('numero_factura', $cabecera)) {
                $updateData['numero_factura'] = $cabecera['numero_factura'];
            }
            if (array_key_exists('numero_orden_compra', $cabecera)) {
                $updateData['numero_orden_compra'] = $cabecera['numero_orden_compra'];
            }
            if (!empty($cabecera['recibido_por'])) {
                $updateData['recibido_por'] = $cabecera['recibido_por'];
            }

            $ingreso->update($updateData);

            return $ingreso->fresh([
                'proveedor',
                'usuario',
                'lotes.medicamento.partidaPresupuestaria',
            ]);
        });
    }
}
