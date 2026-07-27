<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Medicamento;
use App\Models\Proveedor;
use App\Models\Lote;
use Illuminate\Support\Facades\DB;

class IngresoController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validamos que desde Vue nos envíen los datos obligatorios
        $request->validate([
            'medicamento.codigo' => 'required',
            'medicamento.nombre' => 'required',
            'proveedor.nombre' => 'required',
            'lote.codigo_lote' => 'required',
            'lote.fecha_vencimiento' => 'required|date',
            'lote.cantidad' => 'required|integer|min:1',
        ]);

        try {
            // Iniciamos la Transacción: O se guarda todo, o no se guarda nada.
            DB::beginTransaction();

            // 2. Buscar si el medicamento ya existe (por su código). Si no existe, lo crea.
            $medicamento = Medicamento::firstOrCreate(
                ['codigo' => $request->input('medicamento.codigo')],
                [
                    'nombre' => $request->input('medicamento.nombre'),
                    'concentracion' => $request->input('medicamento.concentracion'),
                    'forma_farmaceutica' => $request->input('medicamento.forma_farmaceutica'),
                    'unidad_presentacion' => $request->input('medicamento.unidad_presentacion'),
                    'stock_minimo' => $request->input('medicamento.stock_minimo', 0),
                    'descripcion' => $request->input('medicamento.descripcion'),
                ]
            );

            // 3. Buscar si el Proveedor ya existe. Si no, lo crea.
            $proveedor = Proveedor::firstOrCreate(
                ['nombre' => $request->input('proveedor.nombre')],
                [
                    'nit' => $request->input('proveedor.nit'),
                    'contacto' => $request->input('proveedor.contacto'),
                    'telefono' => $request->input('proveedor.telefono'),
                    'direccion' => $request->input('proveedor.direccion'),
                ]
            );

            // 4. Registrar el nuevo Lote de ingreso asociando el Medicamento y el Proveedor
            $lote = new Lote();
            $lote->medicamento_id = $medicamento->id;
            $lote->proveedor_id = $proveedor->id;
            $lote->codigo_lote = $request->input('lote.codigo_lote');
            $lote->fecha_vencimiento = $request->input('lote.fecha_vencimiento');
            
            // Al ser un ingreso nuevo, la cantidad actual es igual a la inicial
            $lote->cantidad_inicial = $request->input('lote.cantidad');
            $lote->cantidad_actual = $request->input('lote.cantidad');
            $lote->save();

            // Si todo salió bien, confirmamos los cambios en la Base de Datos
            DB::commit();

            // Respondemos a Vue que todo fue un éxito
            return response()->json([
                'success' => true,
                'message' => '¡Ingreso registrado correctamente en el inventario!'
            ], 201);

        } catch (\Exception $e) {
            // Si hubo algún error (ej. se cortó la luz), deshacemos todo para evitar errores
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar en base de datos: ' . $e->getMessage()
            ], 500);
        }
    }
}