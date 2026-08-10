<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;

class EstablecimientoController extends Controller
{
    public function index()
    {
        $establecimientos = Establecimiento::where('estado', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'sigla',
                'tipo',
            ]);

        return response()->json($establecimientos);
    }
}
