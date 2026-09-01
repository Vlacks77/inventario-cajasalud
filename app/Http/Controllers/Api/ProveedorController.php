<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProveedorController extends Controller
{
    /**
     * Devuelve proveedores activos para apoyar el buscador de Procedencia / proveedor.
     */
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $proveedores = Proveedor::query()
            ->where('estado', true)
            ->when($buscar !== '', function ($query) use ($buscar) {
                $terminos = preg_split('/\s+/', $buscar, -1, PREG_SPLIT_NO_EMPTY);

                $query->where(function ($q) use ($terminos) {
                    foreach ($terminos as $termino) {
                        $q->where('nombre', 'like', '%' . $termino . '%');
                    }
                });
            })
            ->orderBy('nombre')
            ->limit(30)
            ->get(['id', 'nombre', 'telefono']);

        return response()->json($proveedores);
    }

    /**
     * Registra un proveedor nuevo desde la pantalla de Registrar ingreso.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'nit' => ['nullable', 'string', 'max:100'],
        ]);

        $nombre = trim($datos['nombre']);
        $nombreNormalizado = Str::lower($nombre);

        $duplicado = Proveedor::query()
            ->whereRaw('LOWER(nombre) = ?', [$nombreNormalizado])
            ->first();

        if ($duplicado) {
            return response()->json([
                'message' => 'El proveedor ya existe en el sistema.',
                'proveedor' => $duplicado->only(['id', 'nombre', 'telefono']),
            ], 422);
        }

        $proveedor = Proveedor::create([
            'nombre' => $nombre,
            'telefono' => $datos['telefono'] ?? null,
            'nit' => $datos['nit'] ?? null,
            'estado' => true,
        ]);

        return response()->json([
            'message' => 'Proveedor agregado correctamente.',
            'proveedor' => $proveedor->only(['id', 'nombre', 'telefono']),
        ], 201);
    }
}
