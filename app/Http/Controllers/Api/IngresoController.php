<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngresoRequest;
use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class IngresoController extends Controller
{
    /**
     * Devuelve el siguiente número correlativo que se mostrará en el formulario.
     * La numeración se resetea anualmente. Para la gestión 2026 arranca en 513
     * porque septiembre cerró con el 512.
     */
    public function siguienteNumero(Request $request)
    {
        $fecha = $request->query('fecha', now()->toDateString());
        $year = (int) Carbon::parse($fecha)->year;
        $siguiente = $this->calcularSiguienteNumeroNota($year);

        return response()->json([
            'numero_nota' => 'N.º ' . $siguiente,
            'correlativo' => $siguiente,
            'gestion'     => $year,
        ]);
    }

    public function calcularSiguienteNumeroNota(int $year): int
    {
        $max = Ingreso::whereYear('fecha_ingreso', $year)
            ->where('tipo_ingreso', '!=', 'apertura')
            ->max('correlativo_anual');

        if (!$max) {
            $notas = Ingreso::whereYear('fecha_ingreso', $year)
                ->where('tipo_ingreso', '!=', 'apertura')
                ->where('numero_nota', 'like', 'N.º %')
                ->pluck('numero_nota');

            foreach ($notas as $nota) {
                $num = (int) preg_replace('/\D/', '', $nota);
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        // Gestión 2026: septiembre cerró con el 512, por lo que arranca en 513
        if ($year === 2026) {
            return max(512, (int) $max) + 1;
        }

        // Otras gestiones: se resetea anualmente iniciando en 1
        return ((int) $max) + 1;
    }

    /** Registra una recepción completa: una cabecera y todos sus productos. */
    public function store(StoreIngresoRequest $request)
    {
        $datos = $request->validated();

        $ingreso = DB::transaction(function () use ($datos) {
            $proveedor = Proveedor::firstOrCreate(
                ['nombre' => $datos['proveedor']['nombre']],
                array_filter(['telefono' => $datos['proveedor']['telefono'] ?? null], fn ($valor) => $valor !== null)
            );

            $year = (int) Carbon::parse($datos['ingreso']['fecha_ingreso'])->year;
            $correlativo = $this->calcularSiguienteNumeroNota($year);

            $ingreso = Ingreso::create([
                'proveedor_id' => $proveedor->id,
                'usuario_id' => Auth::id(),
                'almacen' => $datos['ingreso']['almacen'],
                'fecha_ingreso' => $datos['ingreso']['fecha_ingreso'],
                'numero_nota' => 'N.º ' . $correlativo,
                'correlativo_anual' => $correlativo,
                'numero_remision' => $datos['ingreso']['numero_remision'] ?? null,
                'numero_factura' => $datos['ingreso']['numero_factura'] ?? null,
                'numero_orden_compra' => $datos['ingreso']['numero_orden_compra'] ?? null,
                'tipo_ingreso' => $datos['ingreso']['tipo_ingreso'],
                'observacion' => $datos['ingreso']['observacion'] ?? null,
                'recibido_por' => $datos['ingreso']['recibido_por'],
            ]);

            foreach ($datos['items'] as $item) {
                $producto = Medicamento::where('estado', true)->findOrFail($item['producto_id']);

                Lote::create([
                    'ingreso_id' => $ingreso->id,
                    'medicamento_id' => $producto->id,
                    'proveedor_id' => $proveedor->id,
                'usuario_id' => Auth::id(),
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
            'numero_nota' => $ingreso->numero_nota,
        ], 201);
    }

    public function show(int $id)
    {
        $ingreso = Ingreso::with([
            'proveedor',
            'usuario',
            'lotes.medicamento.partidaPresupuestaria',
            'lotes.detalleSalidas',
        ])->findOrFail($id);

        $fecha = Carbon::parse($ingreso->fecha_ingreso);
        $periodoCerrado = \App\Models\CierreMensual::whereYear('periodo', $fecha->year)
            ->whereMonth('periodo', $fecha->month)
            ->where('estado', 'CERRADO')
            ->exists();

        $esRestringido = in_array($ingreso->tipo_ingreso, ['apertura', 'reembolso']);

        return response()->json([
            'id'                  => $ingreso->id,
            'numero_nota'         => $ingreso->numero_nota,
            'correlativo_anual'   => $ingreso->correlativo_anual,
            'fecha_ingreso'       => $ingreso->fecha_ingreso?->format('Y-m-d') ?? (string) $ingreso->fecha_ingreso,
            'almacen'             => $ingreso->almacen,
            'tipo_ingreso'        => $ingreso->tipo_ingreso,
            'numero_remision'     => $ingreso->numero_remision,
            'numero_factura'      => $ingreso->numero_factura,
            'numero_orden_compra' => $ingreso->numero_orden_compra,
            'recibido_por'        => $ingreso->recibido_por,
            'autorizado_por'      => $ingreso->autorizado_por,
            'observacion'         => $ingreso->observacion,
            'proveedor_id'        => $ingreso->proveedor_id,
            'proveedor'           => $ingreso->proveedor,
            'periodo_cerrado'     => $periodoCerrado,
            'puede_editar'        => !$periodoCerrado && !$esRestringido,
            'items'               => $ingreso->lotes->map(function ($lote) {
                $despachadas = (int) $lote->cantidad_inicial - (int) $lote->cantidad_actual;
                $tieneSalidas = $lote->detalleSalidas->isNotEmpty() || $despachadas > 0;
                $med = $lote->medicamento;

                return [
                    'id'                  => $lote->id,
                    'lote_id'             => $lote->id,
                    'producto_id'         => $lote->medicamento_id,
                    'codigo_lote'         => $lote->codigo_lote,
                    'fecha_vencimiento'   => $lote->fecha_vencimiento?->format('Y-m-d'),
                    'cantidad_inicial'    => (int) $lote->cantidad_inicial,
                    'cantidad_actual'     => (int) $lote->cantidad_actual,
                    'cantidad'            => (int) $lote->cantidad_inicial,
                    'cantidad_despachada' => max(0, $despachadas),
                    'tiene_salidas'       => $tieneSalidas,
                    'precio_unitario'     => (float) $lote->precio_unitario,
                    'importe_total'       => (float) $lote->importe_total,
                    'medicamento'         => $med ? [
                        'id'                  => $med->id,
                        'codigo'              => $med->codigo,
                        'nombre'              => $med->nombre,
                        'concentracion'       => $med->concentracion,
                        'forma_farmaceutica'  => $med->forma_farmaceutica,
                        'unidad_presentacion' => $med->unidad_presentacion,
                        'partida'             => $med->partidaPresupuestaria?->codigo,
                    ] : null,
                ];
            }),
        ]);
    }

    public function update(Request $request, int $id, \App\Services\IngresoService $ingresoService)
    {
        $ingreso = Ingreso::findOrFail($id);
        $datos = $request->validate([
            'proveedor.nombre'               => 'sometimes|required|string|max:255',
            'proveedor.telefono'             => 'nullable|string|max:30',
            'proveedor_id'                   => 'nullable|exists:proveedores,id',
            'ingreso.almacen'                => 'sometimes|required|string|max:150',
            'ingreso.fecha_ingreso'          => 'sometimes|required|date',
            'ingreso.numero_remision'        => 'nullable|string|max:100',
            'ingreso.numero_factura'         => 'nullable|string|max:100',
            'ingreso.numero_orden_compra'    => 'nullable|string|max:100',
            'ingreso.observacion'            => 'nullable|string',
            'ingreso.recibido_por'           => 'sometimes|required|string|max:255',

            'almacen'                        => 'sometimes|required|string|max:150',
            'fecha_ingreso'                  => 'sometimes|required|date',
            'numero_remision'                => 'nullable|string|max:100',
            'numero_factura'                 => 'nullable|string|max:100',
            'numero_orden_compra'            => 'nullable|string|max:100',
            'observacion'                    => 'nullable|string',
            'recibido_por'                   => 'sometimes|required|string|max:255',

            'items'                          => 'required|array|min:1',
            'items.*.id'                     => 'nullable|integer',
            'items.*.lote_id'                => 'nullable|integer',
            'items.*.producto_id'            => 'required_without:items.*.medicamento_id|nullable|integer|exists:medicamentos,id',
            'items.*.medicamento_id'         => 'required_without:items.*.producto_id|nullable|integer|exists:medicamentos,id',
            'items.*.codigo_lote'            => 'required_without:items.*.lote.codigo_lote|nullable|string|max:100',
            'items.*.lote.codigo_lote'       => 'required_without:items.*.codigo_lote|nullable|string|max:100',
            'items.*.fecha_vencimiento'      => 'nullable|date',
            'items.*.lote.fecha_vencimiento' => 'nullable|date',
            'items.*.cantidad'               => 'required|integer|min:1',
            'items.*.precio_unitario'        => 'required|numeric|min:0',
        ]);

        $ingresoActualizado = $ingresoService->actualizar($ingreso, $datos);

        return response()->json([
            'success'     => true,
            'message'     => 'Nota de ingreso ' . $ingresoActualizado->numero_nota . ' modificada correctamente.',
            'ingreso'     => $ingresoActualizado,
            'ingreso_id'  => $ingresoActualizado->id,
            'pdf_id'      => $ingresoActualizado->id,
            'numero_nota' => $ingresoActualizado->numero_nota,
        ]);
    }

    public function pdf(Ingreso $ingreso)
    {
        $ingreso->load(['proveedor', 'lotes.medicamento.partidaPresupuestaria']);
        $total = $ingreso->lotes->sum('importe_total');

        $pdf = Pdf::loadView('pdf.nota-ingreso', [
            'ingreso' => $ingreso,
            'total' => $total,
            'totalLiteral' => $this->montoEnLetras((float) $total),
        ])->setPaper('letter', 'portrait');

        $contenido = $pdf->output();
        $nombre = 'nota-ingreso-'.str_replace(['N.º ', ' '], ['', '-'], $ingreso->numero_nota).'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
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
