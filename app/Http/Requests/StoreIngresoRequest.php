<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngresoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'proveedor.nombre' => ['required', 'string', 'max:255'],
            'proveedor.telefono' => ['nullable', 'string', 'max:30'],
            'ingreso.almacen' => ['required', 'string', 'max:150'],
            'ingreso.fecha_ingreso' => ['required', 'date'],
            'ingreso.numero_remision' => ['nullable', 'string', 'max:100'],
            'ingreso.numero_factura' => ['nullable', 'string', 'max:100'],
            'ingreso.tipo_ingreso' => ['required', 'in:compra_local,transferencia,donacion,devolucion,otro'],
            'ingreso.observacion' => ['nullable', 'string'],
            'ingreso.recibido_por' => ['required', 'string', 'max:255'],
            'ingreso.autorizado_por' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.producto_id' => ['required', 'integer', 'exists:medicamentos,id'],
            'items.*.lote.codigo_lote' => ['required', 'string', 'max:100'],
            'items.*.lote.fecha_vencimiento' => ['nullable', 'date'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }
}
