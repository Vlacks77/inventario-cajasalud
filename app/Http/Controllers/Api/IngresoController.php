<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngresoRequest;
use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class IngresoController extends Controller
{
    /** Registra una recepción completa: una cabecera y todos sus productos. */
    public function store(StoreIngresoRequest $request)
    {
        $datos = $request->validated();

        $ingreso = DB::transaction(function () use ($datos) {
            $proveedor = Proveedor::firstOrCreate(
                ['nombre' => $datos['proveedor']['nombre']],
                array_filter(['telefono' => $datos['proveedor']['telefono'] ?? null], fn ($valor) => $valor !== null)
            );

            $ingreso = Ingreso::create([
                'proveedor_id' => $proveedor->id,
                'almacen' => $datos['ingreso']['almacen'],
                'fecha_ingreso' => $datos['ingreso']['fecha_ingreso'],
                'numero_remision' => $datos['ingreso']['numero_remision'] ?? null,
                'numero_factura' => $datos['ingreso']['numero_factura'] ?? null,
                'tipo_ingreso' => $datos['ingreso']['tipo_ingreso'],
                'observacion' => $datos['ingreso']['observacion'] ?? null,
                'recibido_por' => $datos['ingreso']['recibido_por'],
                'autorizado_por' => $datos['ingreso']['autorizado_por'],
            ]);
            $ingreso->update(['numero_nota' => 'N.º '.str_pad((string) $ingreso->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($datos['items'] as $item) {
                $producto = Medicamento::where('estado', true)->findOrFail($item['producto_id']);

                Lote::create([
                    'ingreso_id' => $ingreso->id,
                    'medicamento_id' => $producto->id,
                    'proveedor_id' => $proveedor->id,
                    'codigo_lote' => $item['lote']['codigo_lote'],
                    'fecha_vencimiento' => $item['lote']['fecha_vencimiento'] ?? null,
                    'cantidad_inicial' => $item['cantidad'],
                    'cantidad_actual' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'importe_total' => round($item['cantidad'] * $item['precio_unitario'], 2),
                ]);
            }

            return $ingreso;
        });

        return response()->json([
            'success' => true,
            'message' => "{$ingreso->numero_nota} registrada con ".count($datos['items']).' ítem(s).',
            'ingreso_id' => $ingreso->id,
            'pdf_id' => $ingreso->id,
        ], 201);
    }

    public function pdf(Ingreso $ingreso)
    {
        $ingreso->load(['proveedor', 'lotes.medicamento.partidaPresupuestaria']);
        $total = $ingreso->lotes->sum('importe_total');

        return Pdf::loadView('pdf.nota-ingreso', [
            'ingreso' => $ingreso,
            'total' => $total,
            'totalLiteral' => $this->montoEnLetras((float) $total),
        ])->setPaper('letter', 'landscape')
            ->download('nota-ingreso-'.str_replace(['N.º ', ' '], ['', '-'], $ingreso->numero_nota).'.pdf');
    }

    private function montoEnLetras(float $monto): string
    {
        $enteros = (int) floor($monto);
        $centavos = (int) round(($monto - $enteros) * 100);
        return strtoupper($this->numeroEnLetras($enteros).' BOLIVIANOS CON '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100');
    }

    private function numeroEnLetras(int $numero): string
    {
        $unidades = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte'];
        if ($numero <= 20) return $unidades[$numero];
        $decenas = [2 => 'veinte', 3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];
        if ($numero < 30) return 'veinti'.$unidades[$numero - 20];
        if ($numero < 100) return $decenas[intdiv($numero, 10)].($numero % 10 ? ' y '.$unidades[$numero % 10] : '');
        $centenas = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];
        if ($numero === 100) return 'cien';
        if ($numero < 1000) return $centenas[intdiv($numero, 100)].($numero % 100 ? ' '.$this->numeroEnLetras($numero % 100) : '');
        if ($numero < 1000000) return ($numero < 2000 ? 'mil' : $this->numeroEnLetras(intdiv($numero, 1000)).' mil').($numero % 1000 ? ' '.$this->numeroEnLetras($numero % 1000) : '');
        return $numero === 1000000 ? 'un millón' : (string) $numero;
    }
}
