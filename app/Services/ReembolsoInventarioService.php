<?php

namespace App\Services;

use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Salida;
use App\Models\Proveedor;
use App\Models\Medicamento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ReembolsoInventarioService
 * ─────────────────────────────────────────────────────────────────────────────
 * Procesa el flujo completo de Reembolsos Mensuales de Medicamentos en una
 * única transacción atómica.
 *
 * Lógica de negocio:
 *  A) Registra la Nota de Ingreso por paciente/factura, con un lote virtual
 *     por ítem (es_reembolso = true). El stock_actual comienza en la cantidad
 *     de cada ítem para que la salida pueda consumirlo.
 *
 *  B) Agrupa los ítems por medicamento_id, calcula el Costo Promedio Ponderado
 *     (CPP) y genera un único registro de Salida tipo 'EGRESO_REEMBOLSO' con
 *     una línea consolidada por medicamento. Consume los lotes virtuales
 *     dejando su cantidad_actual en 0.
 *
 * Resultado contable:
 *  • Kardex / cierre mensual: refleja el ingreso y egreso del reembolso en sus
 *    columnas correspondientes (Transferencias/Ingresos y Egresos), según
 *    tipo_ingreso = 'reembolso'.
 *  • Stock físico real: intacto, porque los únicos lotes afectados son los
 *    virtuales (es_reembolso = true) creados en el Paso A.
 */
class ReembolsoInventarioService
{
    /**
     * Ejecuta el procesamiento completo del reembolso.
     *
     * @param  array  $datos  Datos validados por StoreReembolsoRequest.
     *   {
     *     "ingreso": {
     *       "almacen": "REGIONAL LA PAZ",
     *       "fecha_ingreso": "2026-10-31",
     *       "observacion": "Reembolso octubre 2026",
     *       "recibido_por": "Lic. Flores"
     *     },
     *     "proveedor": { "nombre": "VARIOS PACIENTES" },
     *     "items": [
     *       {
     *         "producto_id": 42,
     *         "cantidad": 30,
     *         "precio_unitario": 5.00,
     *         "codigo_lote_referencia": "FAC-00123",   // nro. de factura del paciente
     *         "fecha_vencimiento": null                 // opcional
     *       },
     *       ...
     *     ]
     *   }
     *
     * @return array  { ingreso: Ingreso, salida: Salida }
     *
     * @throws ValidationException  Si algún medicamento no existe o está inactivo.
     */
    public function procesar(array $datos): array
    {
        return DB::transaction(function () use ($datos) {

            // ──────────────────────────────────────────────────────────────────
            // PASO A — Nota de Ingreso (tipo_ingreso = 'reembolso')
            // ──────────────────────────────────────────────────────────────────
            $proveedor = Proveedor::firstOrCreate(
                ['nombre' => $datos['proveedor']['nombre']],
                array_filter(
                    ['telefono' => $datos['proveedor']['telefono'] ?? null],
                    fn ($v) => $v !== null
                )
            );

            $ingreso = Ingreso::create([
                'proveedor_id'      => $proveedor->id,
                'usuario_id'        => Auth::id(),
                'almacen'           => $datos['ingreso']['almacen'],
                'fecha_ingreso'     => $datos['ingreso']['fecha_ingreso'],
                'numero_remision'   => $datos['ingreso']['numero_remision'] ?? null,
                'numero_factura'    => $datos['ingreso']['numero_factura']  ?? null,
                'tipo_ingreso'      => 'reembolso',   // valor fijo para esta ruta
                'observacion'       => $datos['ingreso']['observacion']     ?? null,
                'recibido_por'      => $datos['ingreso']['recibido_por'],
            ]);

            // El número de nota es correlativo al ID (igual que los ingresos normales).
            $ingreso->update(['numero_nota' => 'RMB-' . $ingreso->id]);

            // Creamos un lote virtual por cada ítem del reembolso.
            // Es_reembolso = true impide que farmacia dispense de estos lotes.
            $lotesCreados = [];
            foreach ($datos['items'] as $item) {
                $producto = Medicamento::where('estado', true)->find($item['producto_id']);

                if (!$producto) {
                    throw ValidationException::withMessages([
                        'items' => ["El medicamento ID {$item['producto_id']} no existe o está inactivo."],
                    ]);
                }

                $cantidad      = (int) $item['cantidad'];
                $precioUnit    = (float) $item['precio_unitario'];
                $importe       = round($cantidad * $precioUnit, 2);
                $codigoLote    = $item['codigo_lote_referencia'] ?? ('RMB-' . $ingreso->id . '-' . $producto->id);

                $lote = Lote::create([
                    'ingreso_id'        => $ingreso->id,
                    'medicamento_id'    => $producto->id,
                    'proveedor_id'      => $proveedor->id,
                    'codigo_lote'       => $codigoLote,
                    'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                    'cantidad_inicial'  => $cantidad,
                    'cantidad_actual'   => $cantidad,   // stock temporal; se zeroa en Paso B
                    'precio_unitario'   => $precioUnit,
                    'importe_total'     => $importe,
                    'es_reembolso'      => true,
                ]);

                $lotesCreados[] = [
                    'lote'           => $lote,
                    'medicamento_id' => $producto->id,
                    'cantidad'       => $cantidad,
                    'precio_unit'    => $precioUnit,
                    'importe'        => $importe,
                ];
            }

            // ──────────────────────────────────────────────────────────────────
            // PASO B — Consolidación y Nota de Egreso (EGRESO_REEMBOLSO)
            // ──────────────────────────────────────────────────────────────────
            // Agrupa los ítems por medicamento y calcula el CPP.
            $consolidado = [];
            foreach ($lotesCreados as $registro) {
                $mid = $registro['medicamento_id'];
                if (!isset($consolidado[$mid])) {
                    $consolidado[$mid] = [
                        'medicamento_id'  => $mid,
                        'cantidad_total'  => 0,
                        'importe_total'   => 0.0,
                        'lotes'           => [],
                    ];
                }
                $consolidado[$mid]['cantidad_total'] += $registro['cantidad'];
                $consolidado[$mid]['importe_total']  += $registro['importe'];
                $consolidado[$mid]['lotes'][]         = $registro['lote'];
            }

            // Calculamos el CPP por grupo y preparamos los detalles consolidados.
            $detallesEgreso = [];
            foreach ($consolidado as &$grupo) {
                $cantTotal = $grupo['cantidad_total'];
                $impTotal  = $grupo['importe_total'];

                // CPP = Σ(cantidad * precio_unit) / Σ(cantidad)
                $cpp = $cantTotal > 0 ? round($impTotal / $cantTotal, 6) : 0.0;

                $grupo['cpp'] = $cpp;

                // Seleccionamos el primer lote del grupo como representante para
                // el detalle_salida (solo necesita un lote_id vinculado al medicamento).
                // Los demás lotes del mismo medicamento también se zeroan a continuación.
                $detallesEgreso[] = [
                    'lote_referencia'         => $grupo['lotes'][0],
                    'cantidad'                => $cantTotal,
                    'precio_unitario_reembolso' => $cpp,
                    'todos_los_lotes'         => $grupo['lotes'],
                ];
            }
            unset($grupo);

            // Obtenemos el establecimiento de "reembolsos" (interno, no envío real).
            // Se asume que existe un establecimiento con nombre 'REEMBOLSO INTERNO'
            // o, si no existe, se toma el primero disponible como fallback.
            // En producción este ID debe estar configurado o ser un parámetro.
            $establecimientoId = $this->establecimientoReembolsoId();

            // Cabecera del egreso consolidado
            $salida = Salida::create([
                'fecha_salida'        => $datos['ingreso']['fecha_ingreso'],
                'almacen_origen'      => $datos['ingreso']['almacen'],
                'establecimiento_id'  => $establecimientoId,
                'solicitado_por'      => $datos['ingreso']['recibido_por'],
                'entregado_a'         => 'REEMBOLSO — ' . ($datos['ingreso']['observacion'] ?? ''),
                'observaciones'       => 'Egreso consolidado automático del ingreso ' . $ingreso->numero_nota,
                'estado'              => 'ACTIVA',
                'tipo_salida'         => 'EGRESO_REEMBOLSO',
                'movimiento_origen_id' => $ingreso->id,
                'usuario_id'          => Auth::id(),
            ]);
            // Correlativo igual que salidas normales
            $salida->numero_salida = $salida->id;
            $salida->save();

            // Creamos los detalles de salida (una línea consolidada por medicamento)
            // y zeramos los lotes virtuales.
            foreach ($detallesEgreso as $detalle) {
                /** @var Lote $loteRef */
                $loteRef = $detalle['lote_referencia'];

                $salida->detalles()->create([
                    'lote_id'                   => $loteRef->id,
                    'cantidad'                  => $detalle['cantidad'],
                    'precio_unitario_reembolso' => $detalle['precio_unitario_reembolso'],
                ]);

                // Zeramos TODOS los lotes virtuales del grupo (es_reembolso = true)
                // para reflejar que el stock contable quedó en cero.
                foreach ($detalle['todos_los_lotes'] as $loteVirtual) {
                    /** @var Lote $loteVirtual */
                    $loteVirtual->lockForUpdate();
                    $loteVirtual->cantidad_actual = 0;
                    $loteVirtual->save();
                }
            }

            return [
                'ingreso' => $ingreso->load(['proveedor', 'lotes.medicamento']),
                'salida'  => $salida->load(['detalles.lote.medicamento']),
            ];
        });
    }

    /**
     * Devuelve el ID del establecimiento usado para egresos de reembolso.
     *
     * Busca primero un establecimiento cuyo nombre contenga 'REEMBOLSO'
     * o 'ALMACEN' (sin distinción de mayúsculas). Si no existe ninguno,
     * retorna el ID del primer establecimiento activo como fallback seguro.
     *
     * @throws \RuntimeException  Si no existe ningún establecimiento en el sistema.
     */
    private function establecimientoReembolsoId(): int
    {
        $establecimiento = DB::table('establecimientos')
            ->where(function ($q) {
                $q->whereRaw("LOWER(nombre) LIKE '%reembolso%'")
                  ->orWhereRaw("LOWER(nombre) LIKE '%almacen%'")
                  ->orWhereRaw("LOWER(nombre) LIKE '%almacén%'");
            })
            ->orderBy('id')
            ->first();

        if (!$establecimiento) {
            // Fallback: primer establecimiento del sistema.
            $establecimiento = DB::table('establecimientos')->orderBy('id')->first();
        }

        if (!$establecimiento) {
            throw new \RuntimeException(
                'No existe ningún establecimiento en el sistema. ' .
                'Cree al menos uno antes de procesar reembolsos.'
            );
        }

        return (int) $establecimiento->id;
    }
}
