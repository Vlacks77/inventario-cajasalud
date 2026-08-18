<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicamento;
use Illuminate\Http\Request;

class MedicamentoController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));

        $medicamentos = Medicamento::with('partidaPresupuestaria:id,codigo,nombre')
            ->where('estado', true)
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('codigo', 'like', "%{$buscar}%")
                      ->orWhere('nombre', 'like', "%{$buscar}%")
                      ->orWhere('concentracion', 'like', "%{$buscar}%")
                      ->orWhere('grupo_producto', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get([
                'id',
                'codigo',
                'nombre',
                'concentracion',
                'forma_farmaceutica',
                'unidad_presentacion',
                'tipo_producto',
                'grupo_producto',
                'partida_presupuestaria_id',
            ]);

        return response()->json($medicamentos);
    }
}
