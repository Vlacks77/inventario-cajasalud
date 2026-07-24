<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngresoRequest extends FormRequest
{
    /** Este endpoint aún no requiere autenticación. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'medicamento.codigo' => ['required', 'string', 'max:50'],
            'medicamento.nombre' => ['required', 'string', 'max:255'],
            'medicamento.concentracion' => ['required', 'string', 'max:100'],
            'medicamento.forma_farmaceutica' => ['required', 'string', 'max:100'],
            'medicamento.unidad_presentacion' => ['required', 'string', 'max:100'],
            'medicamento.stock_minimo' => ['required', 'integer', 'min:0'],
            'medicamento.descripcion' => ['nullable', 'string'],
            'proveedor.nombre' => ['required', 'string', 'max:255'],
            'proveedor.nit' => ['nullable', 'string', 'max:30'],
            'proveedor.contacto' => ['nullable', 'string', 'max:255'],
            'proveedor.telefono' => ['nullable', 'string', 'max:30'],
            'proveedor.direccion' => ['nullable', 'string', 'max:255'],
            'lote.codigo_lote' => ['required', 'string', 'max:100'],
            'lote.fecha_vencimiento' => ['required', 'date', 'after:today'],
            'lote.cantidad' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lote.fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'lote.cantidad.min' => 'La cantidad ingresada debe ser al menos 1.',
        ];
    }
}
