<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\PartidaPresupuestaria;
use App\Models\Proveedor;
use App\Models\Salida;
use App\Models\User;
use App\Services\ReembolsoInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorreccionesFlujoYReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Medicamento $medicamentoConConcentracion;
    private Medicamento $medicamentoSinConcentracion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create([
            'username' => 'almacen_test',
            'role'     => 'almacen',
            'regional' => 'La Paz',
        ]);

        $partida = PartidaPresupuestaria::firstOrCreate(
            ['codigo' => '34200'],
            ['nombre' => 'PRODUCTOS QUÍMICOS Y FARMACÉUTICOS']
        );

        // Caso 1: El nombre ya incluye la concentración
        $this->medicamentoConConcentracion = Medicamento::create([
            'codigo'                    => 'MED001',
            'partida_presupuestaria_id' => $partida->id,
            'nombre'                    => 'Abacavir 300 mg',
            'concentracion'             => '300 mg',
            'forma_farmaceutica'        => 'Comprimido',
            'unidad_presentacion'       => 'Pieza',
            'tipo_producto'             => 'MEDICAMENTO',
            'grupo_producto'            => 'MEDICAMENTOS',
            'estado'                    => true,
        ]);

        // Caso 2: El nombre no incluye la concentración
        $this->medicamentoSinConcentracion = Medicamento::create([
            'codigo'                    => 'MED002',
            'partida_presupuestaria_id' => $partida->id,
            'nombre'                    => 'Ibuprofeno',
            'concentracion'             => '400 mg',
            'forma_farmaceutica'        => 'Comprimido',
            'unidad_presentacion'       => 'Pieza',
            'tipo_producto'             => 'MEDICAMENTO',
            'grupo_producto'            => 'MEDICAMENTOS',
            'estado'                    => true,
        ]);

        Establecimiento::firstOrCreate(
            ['nombre' => 'REEMBOLSO INTERNO'],
            ['tipo' => 'ALMACEN', 'estado' => true]
        );
    }

    /**
     * Prueba 1: No duplicación de concentración en la descripción del ítem.
     */
    public function test_no_duplica_concentracion_en_descripcion_del_item(): void
    {
        // No debe repetir "Abacavir 300 mg 300 mg"
        $this->assertEquals(
            'Abacavir 300 mg',
            $this->medicamentoConConcentracion->descripcion_completa
        );

        // Debe adjuntar si no estaba presente
        $this->assertEquals(
            'Ibuprofeno 400 mg',
            $this->medicamentoSinConcentracion->descripcion_completa
        );
    }

    /**
     * Prueba 2: Petición no autenticada a PDF no lanza error 500 Route [login] not defined,
     * sino 401 Unauthorized JSON.
     */
    public function test_pdf_no_autenticado_retorna_401_sin_error_500_login(): void
    {
        $response = $this->get('/api/ingresos/999/pdf');

        $this->assertEquals(401, $response->getStatusCode());
        $response->assertJson(['message' => 'No autenticado.']);
    }

    /**
     * Prueba 3: Petición con ?token=... en URL autentica transparentemente vía middleware.
     */
    public function test_pdf_autentica_con_token_en_parametro_url(): void
    {
        $token = $this->usuario->createToken('test-token')->plainTextToken;

        $proveedor = Proveedor::create(['nombre' => 'Droguería Inti']);
        $ingreso = Ingreso::create([
            'proveedor_id'  => $proveedor->id,
            'usuario_id'    => $this->usuario->id,
            'almacen'       => 'REGIONAL LA PAZ',
            'fecha_ingreso' => '2026-10-01',
            'numero_nota'   => 'N.º 513',
            'recibido_por'  => 'Dra. Carmen',
        ]);

        Lote::create([
            'ingreso_id'        => $ingreso->id,
            'medicamento_id'    => $this->medicamentoConConcentracion->id,
            'proveedor_id'      => $proveedor->id,
            'codigo_lote'       => 'LOT-01',
            'cantidad_inicial'  => 10,
            'cantidad_actual'   => 10,
            'precio_unitario'   => 25.50,
            'importe_total'     => 255.00,
            'es_reembolso'      => false,
        ]);

        $response = $this->get("/api/ingresos/{$ingreso->id}/pdf?token={$token}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Prueba 4: Múltiples lotes en reembolso calculan CPP exacto y reporte de salida
     * refleja el Total Valorado de 250 Bs y P. Unitario de 25.00 Bs.
     */
    public function test_multiples_lotes_calculan_precio_promedio_ponderado_en_salida_y_pdf(): void
    {
        $servicio = app(ReembolsoInventarioService::class);

        // 5 unidades a 20 Bs = 100 Bs y 5 unidades a 30 Bs = 150 Bs. Total = 250 Bs.
        $payload = [
            'ingreso'   => [
                'almacen'       => 'REGIONAL LA PAZ',
                'fecha_ingreso' => '2026-10-15',
                'recibido_por'  => 'Dra. Carmen',
                'observacion'   => 'Reembolso multi-lote test',
            ],
            'proveedor' => [
                'nombre' => 'VARIOS PACIENTES TEST',
            ],
            'items'     => [
                [
                    'producto_id'            => $this->medicamentoConConcentracion->id,
                    'cantidad'               => 5,
                    'precio_unitario'        => 20.00,
                    'codigo_lote_referencia' => 'FAC-001',
                ],
                [
                    'producto_id'            => $this->medicamentoConConcentracion->id,
                    'cantidad'               => 5,
                    'precio_unitario'        => 30.00,
                    'codigo_lote_referencia' => 'FAC-002',
                ],
            ],
        ];

        $this->actingAs($this->usuario, 'sanctum');
        $resultado = $servicio->procesar($payload);

        /** @var Salida $salida */
        $salida = $resultado['salida'];

        // La salida consolidada debe tener 1 línea con 10 unidades
        $this->assertCount(1, $salida->detalles);
        $detalle = $salida->detalles->first();
        $this->assertEquals(10, $detalle->cantidad);

        // El CPP debe ser 250 / 10 = 25.000000 Bs
        $this->assertEquals(25.00, round((float) $detalle->precio_unitario_reembolso, 2));

        // Probar el endpoint PDF de Salida
        $responsePdf = $this->get("/api/reportes/salidas/{$salida->id}/pdf");
        $this->assertEquals(200, $responsePdf->getStatusCode());
        $this->assertEquals('application/pdf', $responsePdf->headers->get('Content-Type'));

        // Probar el endpoint Excel de Salida
        $responseExcel = $this->get("/api/reportes/salidas/{$salida->id}/excel");
        $this->assertEquals(200, $responseExcel->getStatusCode());
        // El contenido HTML de Excel debe contener el importe total 250.00 y precio unitario 25.00
        $html = $responseExcel->getContent();
        $this->assertStringContainsString('250.00', $html);
        $this->assertStringContainsString('25.00', $html);
        // Y NO debe perder 50 Bs (no debe decir 200.00 como total)
        $this->assertStringNotContainsString('200.00', $html);
    }
}
