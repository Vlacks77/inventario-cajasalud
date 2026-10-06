<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos para crear un Reembolso Mensual de Medicamentos.
 *
 * Diferencias respecto a StoreIngresoRequest:
 *  • tipo_ingreso está fijo en 'reembolso' (no lo envía el cliente).
 *  • codigo_lote_referencia es el número de factura del paciente (reemplaza
 *    al código de lote físico del proveedor).
 *  • No se requiere proveedor externo; el valor por defecto es 'VARIOS PACIENTES'.
 *  • Se permite un mínimo de 1 ítem y un máximo de 500 (un reembolso mensual
 *    puede tener muchas facturas).
 */
class StoreReembolsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Cabecera del proveedor (representante genérico de los pacientes)
            'proveedor.nombre'   => ['required', 'string', 'max:255'],
            'proveedor.telefono' => ['nullable', 'string', 'max:30'],

            // Datos del ingreso
            'ingreso.almacen'           => ['required', 'string', 'max:150'],
            'ingreso.fecha_ingreso'     => ['required', 'date'],
            'ingreso.numero_remision'   => ['nullable', 'string', 'max:100'],
            'ingreso.numero_factura'    => ['nullable', 'string', 'max:100'],
            'ingreso.observacion'       => ['nullable', 'string'],
            'ingreso.recibido_por'      => ['required', 'string', 'max:255'],

            // Ítems (una línea por cada factura/paciente)
            'items'                              => ['required', 'array', 'min:1', 'max:500'],
            'items.*.producto_id'                => ['required', 'integer', 'exists:medicamentos,id'],
            'items.*.cantidad'                   => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario'            => ['required', 'numeric', 'min:0'],
            'items.*.codigo_lote_referencia'     => ['nullable', 'string', 'max:100'],
            'items.*.fecha_vencimiento'          => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.producto_id.exists'  => 'El medicamento del ítem :index no existe en el catálogo.',
            'items.*.cantidad.min'        => 'La cantidad del ítem :index debe ser al menos 1.',
            'items.*.precio_unitario.min' => 'El precio unitario del ítem :index no puede ser negativo.',
        ];
    }
}
