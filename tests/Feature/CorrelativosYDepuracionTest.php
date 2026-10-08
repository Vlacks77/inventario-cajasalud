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
use App\Services\SalidaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrelativosYDepuracionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Medicamento $medicamento;
    protected Proveedor $proveedor;
    protected Establecimiento $establecimiento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'almacen_corr_test',
            'role'     => 'almacen',
            'regional' => 'La Paz',
        ]);

        $partida = PartidaPresupuestaria::firstOrCreate(
            ['codigo' => 'P-342'],
            ['nombre' => 'PRODUCTOS FARMACEUTICOS']
        );

        $this->medicamento = Medicamento::create([
            'codigo'                    => 'MED-CORR-01',
            'partida_presupuestaria_id' => $partida->id,
            'nombre'                    => 'AMOXICILINA 500MG',
            'forma_farmaceutica'        => 'CAPSULA',
            'concentracion'             => '500 mg',
            'unidad_presentacion'       => 'CAPSULA',
            'precio_referencial'        => 2.00,
            'stock_minimo'              => 10,
            'estado'                    => true,
        ]);

        $this->proveedor = Proveedor::create([
            'nombre' => 'PROVEEDOR CENTRAL S.A.',
            'estado' => true,
        ]);

        $this->establecimiento = Establecimiento::create([
            'nombre' => 'CENTRO DE SALUD CORR',
            'tipo'   => 'CENTRO DE SALUD',
            'estado' => true,
        ]);
    }

    public function test_siguiente_numero_ingreso_inicia_en_513_en_2026_y_se_resetea_en_2027(): void
    {
        $this->actingAs($this->user);

        // Sin notas en 2026, debe iniciar en 513
        $resp2026 = $this->getJson('/api/ingresos/siguiente-numero?fecha=2026-10-01');
        $resp2026->assertOk()
            ->assertJson([
                'numero_nota' => 'N.º 513',
                'correlativo' => 513,
                'gestion'     => 2026,
            ]);

        // Sin notas en 2027, debe iniciar en 1 (reset anual)
        $resp2027 = $this->getJson('/api/ingresos/siguiente-numero?fecha=2027-01-10');
        $resp2027->assertOk()
            ->assertJson([
                'numero_nota' => 'N.º 1',
                'correlativo' => 1,
                'gestion'     => 2027,
            ]);
    }

    public function test_guardar_ingreso_asigna_correlativo_anual_513_y_siguiente_es_514(): void
    {
        $this->actingAs($this->user);

        $payload = [
            'proveedor' => [
                'nombre' => 'PROVEEDOR CENTRAL S.A.',
            ],
            'ingreso' => [
                'almacen'       => 'REGIONAL LA PAZ',
                'fecha_ingreso' => '2026-10-15',
                'tipo_ingreso'  => 'compra_local',
                'recibido_por'  => 'Responsable Almacén',
            ],
            'items' => [
                [
                    'producto_id'     => $this->medicamento->id,
                    'lote'            => [
                        'codigo_lote'       => 'LOTE-OCT-01',
                        'fecha_vencimiento' => '2028-12-31',
                    ],
                    'cantidad'        => 100,
                    'precio_unitario' => 2.50,
                ],
            ],
        ];

        $resp = $this->postJson('/api/ingresos', $payload);
        $resp->assertCreated()
            ->assertJson([
                'success'     => true,
                'numero_nota' => 'N.º 513',
            ]);

        $this->assertDatabaseHas('ingresos', [
            'numero_nota'       => 'N.º 513',
            'correlativo_anual' => 513,
        ]);

        // El siguiente ingreso en 2026 debe ser 514
        $siguiente = $this->getJson('/api/ingresos/siguiente-numero?fecha=2026-10-16');
        $siguiente->assertOk()
            ->assertJson([
                'numero_nota' => 'N.º 514',
                'correlativo' => 514,
            ]);
    }

    public function test_salidas_se_resetean_mensualmente(): void
    {
        $this->actingAs($this->user);

        // Crear lote con stock para dispensar
        $ingreso = Ingreso::create([
            'proveedor_id'  => $this->proveedor->id,
            'usuario_id'    => $this->user->id,
            'almacen'       => 'REGIONAL LA PAZ',
            'fecha_ingreso' => '2026-10-01',
            'numero_nota'   => 'APT-20261001-001',
            'tipo_ingreso'  => 'apertura',
            'recibido_por'  => 'Sistema',
        ]);

        $lote = Lote::create([
            'ingreso_id'        => $ingreso->id,
            'medicamento_id'    => $this->medicamento->id,
            'proveedor_id'      => $this->proveedor->id,
            'codigo_lote'       => 'LOTE-TEST-SALIDA',
            'cantidad_inicial'  => 500,
            'cantidad_actual'   => 500,
            'precio_unitario'   => 2.00,
            'importe_total'     => 1000.00,
        ]);

        // Siguiente salida en Octubre 2026 debe ser 1
        $octResp = $this->getJson('/api/salidas/siguiente-numero?fecha=2026-10-05');
        $octResp->assertOk()->assertJson(['numero_salida' => 1]);

        // Registrar primera salida en Octubre 2026
        $salidaService = app(SalidaService::class);
        $salida1 = $salidaService->registrar([
            'fecha_salida'       => '2026-10-05',
            'almacen_origen'     => 'REGIONAL LA PAZ',
            'establecimiento_id' => $this->establecimiento->id,
            'solicitado_por'     => 'Dr. Solicitante',
            'detalle'            => [
                [
                    'lote_id'  => $lote->id,
                    'cantidad' => 10,
                ],
            ],
        ]);
        $this->assertEquals(1, $salida1->numero_salida);

        // Segunda salida en Octubre 2026 debe ser 2
        $oct2Resp = $this->getJson('/api/salidas/siguiente-numero?fecha=2026-10-10');
        $oct2Resp->assertOk()->assertJson(['numero_salida' => 2]);

        $salida2 = $salidaService->registrar([
            'fecha_salida'       => '2026-10-10',
            'almacen_origen'     => 'REGIONAL LA PAZ',
            'establecimiento_id' => $this->establecimiento->id,
            'solicitado_por'     => 'Dra. Solicitante',
            'detalle'            => [
                [
                    'lote_id'  => $lote->id,
                    'cantidad' => 5,
                ],
            ],
        ]);
        $this->assertEquals(2, $salida2->numero_salida);

        // En Noviembre 2026, debe resetearse a 1
        $novResp = $this->getJson('/api/salidas/siguiente-numero?fecha=2026-11-01');
        $novResp->assertOk()->assertJson(['numero_salida' => 1]);

        $salidaNov = $salidaService->registrar([
            'fecha_salida'       => '2026-11-02',
            'almacen_origen'     => 'REGIONAL LA PAZ',
            'establecimiento_id' => $this->establecimiento->id,
            'solicitado_por'     => 'Enfermera Jefa',
            'detalle'            => [
                [
                    'lote_id'  => $lote->id,
                    'cantidad' => 20,
                ],
            ],
        ]);
        $this->assertEquals(1, $salidaNov->numero_salida);
    }
}
