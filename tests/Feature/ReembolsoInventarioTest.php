<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\PartidaPresupuestaria;
use App\Models\Salida;
use App\Models\User;
use App\Services\ReembolsoInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReembolsoInventarioTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Medicamento $medicamento;
    protected Establecimiento $establecimiento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'almacen_test',
            'role'     => 'almacen',
            'regional' => 'La Paz',
        ]);

        $partida = PartidaPresupuestaria::firstOrCreate(
            ['codigo' => 'TEST-342'],
            ['nombre' => 'PRODUCTOS QUIMICOS Y FARMACEUTICOS']
        );

        $this->medicamento = Medicamento::create([
            'codigo'                    => 'MED-TEST-RMB',
            'partida_presupuestaria_id' => $partida->id,
            'nombre'                    => 'PARACETAMOL 500MG TEST REEMBOLSO',
            'forma_farmaceutica'        => 'COMPRIMIDO',
            'concentracion'             => '500 mg',
            'unidad_presentacion'       => 'COMPRIMIDO',
            'precio_referencial'        => 1.50,
            'stock_minimo'              => 10,
            'estado'                    => true,
        ]);

        $this->establecimiento = Establecimiento::firstOrCreate(
            ['nombre' => 'ALMACEN CENTRAL REEMBOLSOS'],
            ['tipo' => 'ALMACEN', 'estado' => true]
        );
    }

    public function test_procesar_reembolso_calcula_cpp_y_mantiene_stock_real_intacto(): void
    {
        $this->actingAs($this->user);

        $proveedor = \App\Models\Proveedor::firstOrCreate(
            ['nombre' => 'PROVEEDOR TEST REAL']
        );

        // 1. Crear un lote físico real existente con stock 100 a Bs 2.00
        $ingresoReal = Ingreso::create([
            'proveedor_id'  => $proveedor->id,
            'usuario_id'    => $this->user->id,
            'almacen'       => 'REGIONAL LA PAZ',
            'fecha_ingreso' => '2026-10-01',
            'tipo_ingreso'  => 'compra_local',
            'recibido_por'  => 'Encargado Almacén',
        ]);

        $loteReal = Lote::create([
            'ingreso_id'        => $ingresoReal->id,
            'medicamento_id'    => $this->medicamento->id,
            'proveedor_id'      => $proveedor->id,
            'codigo_lote'       => 'LOTE-REAL-FISICO-01',
            'fecha_vencimiento' => '2027-12-31',
            'cantidad_inicial'  => 100,
            'cantidad_actual'   => 100,
            'precio_unitario'   => 2.00,
            'importe_total'     => 200.00,
            'es_reembolso'      => false,
        ]);

        // 2. Procesar reembolso con 2 compras externas del mismo medicamento
        // Ítem 1: 30 unidades a Bs 5.00 = 150.00
        // Ítem 2: 20 unidades a Bs 5.50 = 110.00
        // Total cantidad = 50, Total importe = 260.00 => CPP = 260 / 50 = 5.20
        $service = app(ReembolsoInventarioService::class);

        $payload = [
            'ingreso' => [
                'almacen'       => 'REGIONAL LA PAZ',
                'fecha_ingreso' => '2026-10-31',
                'observacion'   => 'Reembolso Facturas Octubre Pacientes',
                'recibido_por'  => 'Lic. Responsable',
            ],
            'proveedor' => [
                'nombre' => 'VARIOS PACIENTES TEST',
            ],
            'items' => [
                [
                    'producto_id'            => $this->medicamento->id,
                    'cantidad'               => 30,
                    'precio_unitario'        => 5.00,
                    'codigo_lote_referencia' => 'FAC-001',
                ],
                [
                    'producto_id'            => $this->medicamento->id,
                    'cantidad'               => 20,
                    'precio_unitario'        => 5.50,
                    'codigo_lote_referencia' => 'FAC-002',
                ],
            ],
        ];

        $resultado = $service->procesar($payload);

        $ingresoRmb = $resultado['ingreso'];
        $salidaRmb  = $resultado['salida'];

        // Verificaciones de Ingreso
        $this->assertEquals('reembolso', $ingresoRmb->tipo_ingreso);
        $this->assertEquals(2, $ingresoRmb->lotes->count());

        // Verificación de los lotes virtuales del reembolso: cantidad_actual debe ser 0 y es_reembolso = true
        foreach ($ingresoRmb->lotes as $loteVirtual) {
            $this->assertTrue((bool)$loteVirtual->es_reembolso);
            $this->assertEquals(0, $loteVirtual->fresh()->cantidad_actual);
        }

        // Verificación de aislamiento de stock físico real: lote real sigue con 100 unidades
        $this->assertEquals(100, $loteReal->fresh()->cantidad_actual);

        // Verificaciones de Egreso consolidado
        $this->assertEquals('EGRESO_REEMBOLSO', $salidaRmb->tipo_salida);
        $this->assertEquals($ingresoRmb->id, $salidaRmb->movimiento_origen_id);

        // Consolidación: 1 sola línea de egreso para este medicamento
        $this->assertCount(1, $salidaRmb->detalles);
        $detalleEgreso = $salidaRmb->detalles->first();

        $this->assertEquals(50, $detalleEgreso->cantidad);
        // CPP esperado: 5.20
        $this->assertEquals(5.20, (float)$detalleEgreso->precio_unitario_reembolso);
        $this->assertEquals(5.20, (float)$detalleEgreso->precio_unitario_efectivo);
    }

    public function test_endpoint_api_reembolso_store_y_show(): void
    {
        $this->actingAs($this->user);

        $payload = [
            'ingreso' => [
                'almacen'       => 'REGIONAL LA PAZ',
                'fecha_ingreso' => '2026-10-31',
                'observacion'   => 'Prueba Reembolso API',
                'recibido_por'  => 'Lic. Responsable',
            ],
            'proveedor' => [
                'nombre' => 'VARIOS PACIENTES API TEST',
            ],
            'items' => [
                [
                    'producto_id'            => $this->medicamento->id,
                    'cantidad'               => 10,
                    'precio_unitario'        => 8.00,
                    'codigo_lote_referencia' => 'FAC-999',
                ],
            ],
        ];

        $response = $this->postJson('/api/reembolsos', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $ingresoId = $response->json('ingreso.id');

        // Test endpoint show
        $showResponse = $this->getJson("/api/reembolsos/{$ingresoId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('ingreso.id', $ingresoId)
            ->assertJsonPath('ingreso.tipo_ingreso', 'reembolso')
            ->assertJsonPath('egreso.tipo_salida', 'EGRESO_REEMBOLSO');
    }

    public function test_kardex_y_cierre_mensual_incluyen_reembolso(): void
    {
        $this->actingAs($this->user);

        // Registrar reembolso vía API
        $payload = [
            'ingreso' => [
                'almacen'       => 'REGIONAL LA PAZ',
                'fecha_ingreso' => '2026-10-15',
                'observacion'   => 'Reembolso para Kardex y Cierre',
                'recibido_por'  => 'Lic. Responsable',
            ],
            'proveedor' => [
                'nombre' => 'PACIENTE KARDEX TEST',
            ],
            'items' => [
                [
                    'producto_id'            => $this->medicamento->id,
                    'cantidad'               => 40,
                    'precio_unitario'        => 10.00,
                    'codigo_lote_referencia' => 'FAC-KDX-01',
                ],
            ],
        ];

        $postResponse = $this->postJson('/api/reembolsos', $payload);
        $postResponse->assertStatus(201);

        // 1. Verificar en Kardex
        $kardexResponse = $this->getJson("/api/kardex?buscar={$this->medicamento->codigo}");
        $kardexResponse->assertStatus(200);

        $movimientos = collect($kardexResponse->json());
        $ingresoMov = $movimientos->firstWhere('tipo', 'REEMBOLSO_INGRESO');
        $egresoMov  = $movimientos->firstWhere('tipo', 'EGRESO_REEMBOLSO');

        $this->assertNotNull($ingresoMov, 'El movimiento REEMBOLSO_INGRESO debe aparecer en el Kardex');
        $this->assertNotNull($egresoMov, 'El movimiento EGRESO_REEMBOLSO debe aparecer en el Kardex');
        $this->assertEquals(40, $ingresoMov['cantidad']);
        $this->assertEquals(40, $egresoMov['cantidad']);
        $this->assertEquals(10.00, (float)$egresoMov['precio_unitario']);

        // 2. Verificar en Preview de Cierre Mensual
        $cierreResponse = $this->getJson('/api/cierres-mensuales/preview?periodo=2026-10&almacen=REGIONAL+LA+PAZ');
        $cierreResponse->assertStatus(200);

        $detalles = collect($cierreResponse->json('detalles'));
        $detalleMed = $detalles->firstWhere('medicamento_id', $this->medicamento->id);

        $this->assertNotNull($detalleMed, 'El medicamento debe estar presente en el detalle del cierre mensual');
        // Debe sumarse en la columna transferencia (ingreso) y en egreso
        $this->assertEquals(40, $detalleMed['transferencia_cantidad']);
        $this->assertEquals(400.00, (float)$detalleMed['transferencia_importe']);
        $this->assertEquals(40, $detalleMed['egreso_cantidad']);
        $this->assertEquals(400.00, (float)$detalleMed['egreso_importe']);
    }
}

