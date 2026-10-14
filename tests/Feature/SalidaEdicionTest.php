<?php

namespace Tests\Feature;

use App\Models\CierreMensual;
use App\Models\DetalleSalida;
use App\Models\Establecimiento;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\PartidaPresupuestaria;
use App\Models\Salida;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalidaEdicionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Medicamento $med1;
    protected Medicamento $med2;
    protected Lote $lote1;
    protected Lote $lote2;
    protected Establecimiento $establecimiento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'almacen_editor',
            'name'     => 'Encargada Almacén',
            'role'     => 'almacen',
            'regional' => 'La Paz',
        ]);

        $partida = PartidaPresupuestaria::firstOrCreate(
            ['codigo' => 'P-342'],
            ['nombre' => 'PRODUCTOS FARMACEUTICOS']
        );

        $this->med1 = Medicamento::create([
            'codigo'                    => 'MED-01',
            'partida_presupuestaria_id' => $partida->id,
            'nombre'                    => 'PARACETAMOL 500MG',
            'forma_farmaceutica'        => 'COMPRIMIDO',
            'concentracion'             => '500 mg',
            'unidad_presentacion'       => 'COMPRIMIDO',
            'precio_referencial'        => 0.25,
            'stock_minimo'              => 10,
            'estado'                    => true,
        ]);

        $this->med2 = Medicamento::create([
            'codigo'                    => 'MED-02',
            'partida_presupuestaria_id' => $partida->id,
            'nombre'                    => 'IBUPROFENO 400MG',
            'forma_farmaceutica'        => 'COMPRIMIDO',
            'concentracion'             => '400 mg',
            'unidad_presentacion'       => 'COMPRIMIDO',
            'precio_referencial'        => 0.50,
            'stock_minimo'              => 10,
            'estado'                    => true,
        ]);

        $proveedor = \App\Models\Proveedor::create([
            'nombre' => 'PROVEEDOR TEST S.A.',
            'estado' => true,
        ]);

        $this->lote1 = Lote::create([
            'medicamento_id'    => $this->med1->id,
            'proveedor_id'      => $proveedor->id,
            'codigo_lote'       => 'LOT-001',
            'fecha_vencimiento' => '2028-12-31',
            'cantidad_inicial'  => 100,
            'cantidad_actual'   => 100,
            'precio_unitario'   => 0.2489,
            'estado'            => 'ACTIVO',
        ]);

        $this->lote2 = Lote::create([
            'medicamento_id'    => $this->med2->id,
            'proveedor_id'      => $proveedor->id,
            'codigo_lote'       => 'LOT-002',
            'fecha_vencimiento' => '2028-12-31',
            'cantidad_inicial'  => 50,
            'cantidad_actual'   => 50,
            'precio_unitario'   => 0.45,
            'estado'            => 'ACTIVO',
        ]);

        $this->establecimiento = Establecimiento::create([
            'nombre' => 'CENTRO DE SALUD MIRAFLORES',
            'tipo'   => 'CENTRO DE SALUD',
            'estado' => true,
        ]);
    }

    private function crearSalidaBase(array $overrides = []): Salida
    {
        $salida = Salida::create(array_merge([
            'numero_salida'       => 'S-2026-0005',
            'fecha_salida'        => '2026-10-10',
            'almacen_origen'      => 'REGIONAL LA PAZ',
            'numero_pedido'       => 'PED-001',
            'tipo_salida'         => 'PROGRAMADA',
            'establecimiento_id'  => $this->establecimiento->id,
            'solicitado_por'      => 'Dr. Juan Pérez',
            'entregado_a'         => 'Lic. María Gomez',
            'observaciones'       => 'Despacho ordinario',
            'estado'              => 'COMPLETADA',
            'usuario_id'          => $this->user->id,
        ], $overrides));

        DetalleSalida::create([
            'salida_id'       => $salida->id,
            'lote_id'         => $this->lote1->id,
            'cantidad'        => 30,
            'precio_unitario' => 0.2489,
            'subtotal'        => round(30 * 0.2489, 2),
        ]);

        // Descontar del lote
        $this->lote1->decrement('cantidad_actual', 30);

        return $salida;
    }

    public function test_obtener_salida_para_edicion(): void
    {
        $salida = $this->crearSalidaBase();

        $this->actingAs($this->user);
        $resp = $this->getJson("/api/salidas/{$salida->id}");

        $resp->assertOk()
            ->assertJsonPath('numero_salida', 'S-2026-0005')
            ->assertJsonPath('puede_editar', true)
            ->assertJsonPath('periodo_cerrado', false)
            ->assertJsonCount(1, 'detalles');
    }

    public function test_editar_salida_revertiendo_stock_y_descontando_nuevo(): void
    {
        $salida = $this->crearSalidaBase();
        $this->assertEquals(70, $this->lote1->fresh()->cantidad_actual);

        $this->actingAs($this->user);
        $payload = [
            'observaciones'  => 'Despacho ordinario con corrección',
            'solicitado_por' => 'Dr. Juan Pérez Modificado',
            'detalle' => [
                [
                    'lote_id'  => $this->lote1->id,
                    'cantidad' => 45, // De 30 pasa a 45
                ],
            ],
        ];

        $resp = $this->putJson("/api/salidas/{$salida->id}", $payload);

        $resp->assertOk()
            ->assertJson(['success' => true]);

        // Verificar correlativo intacto
        $salidaActualizada = $salida->fresh();
        $this->assertEquals('S-2026-0005', $salidaActualizada->numero_salida);
        $this->assertStringContainsString('Modificado el', $salidaActualizada->observaciones);
        $this->assertStringContainsString('Encargada Almacén', $salidaActualizada->observaciones);

        // Verificar stock de lote1: 100 - 45 = 55
        $this->assertEquals(55, $this->lote1->fresh()->cantidad_actual);

        // Verificar detalle
        $this->assertCount(1, $salidaActualizada->detalles);
        $this->assertEquals(45, $salidaActualizada->detalles->first()->cantidad);
    }

    public function test_editar_salida_agregando_nuevo_item(): void
    {
        $salida = $this->crearSalidaBase();

        $this->actingAs($this->user);
        $payload = [
            'detalle' => [
                [
                    'lote_id'  => $this->lote1->id,
                    'cantidad' => 30,
                ],
                [
                    'lote_id'  => $this->lote2->id,
                    'cantidad' => 15, // Nuevo item que se le olvidó a la encargada
                ],
            ],
        ];

        $resp = $this->putJson("/api/salidas/{$salida->id}", $payload);
        $resp->assertOk()->assertJson(['success' => true]);

        // lote1 sigue con 70 (30 revertidos + 30 descontados)
        $this->assertEquals(70, $this->lote1->fresh()->cantidad_actual);
        // lote2 se descontó 15 (50 - 15 = 35)
        $this->assertEquals(35, $this->lote2->fresh()->cantidad_actual);

        $salidaActualizada = $salida->fresh(['detalles']);
        $this->assertCount(2, $salidaActualizada->detalles);
    }

    public function test_no_permite_editar_salida_en_periodo_cerrado(): void
    {
        $salida = $this->crearSalidaBase();

        // Creamos cierre mensual cerrado para octubre 2026
        CierreMensual::create([
            'almacen'       => 'REGIONAL LA PAZ',
            'periodo'       => '2026-10-01',
            'fecha_desde'   => '2026-10-01',
            'fecha_hasta'   => '2026-10-31',
            'usuario_id'    => $this->user->id,
            'estado'        => 'CERRADO',
        ]);

        $this->actingAs($this->user);

        // Verificamos flag en GET
        $getResp = $this->getJson("/api/salidas/{$salida->id}");
        $getResp->assertOk()
            ->assertJsonPath('puede_editar', false)
            ->assertJsonPath('periodo_cerrado', true);

        // Intentamos PUT
        $resp = $this->putJson("/api/salidas/{$salida->id}", [
            'detalle' => [
                ['lote_id' => $this->lote1->id, 'cantidad' => 10],
            ],
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['periodo']);

        // Stock y detalles no deben haberse modificado
        $this->assertEquals(70, $this->lote1->fresh()->cantidad_actual);
    }

    public function test_no_permite_editar_salida_de_tipo_egreso_reembolso(): void
    {
        $salida = $this->crearSalidaBase(['tipo_salida' => 'EGRESO_REEMBOLSO']);

        $this->actingAs($this->user);

        // Verificamos flag en GET
        $getResp = $this->getJson("/api/salidas/{$salida->id}");
        $getResp->assertOk()
            ->assertJsonPath('puede_editar', false);

        // Intentamos PUT
        $resp = $this->putJson("/api/salidas/{$salida->id}", [
            'detalle' => [
                ['lote_id' => $this->lote1->id, 'cantidad' => 10],
            ],
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_salida']);

        // Stock no modificado
        $this->assertEquals(70, $this->lote1->fresh()->cantidad_actual);
    }

    public function test_falla_si_no_hay_stock_suficiente_y_revierte_cambios(): void
    {
        $salida = $this->crearSalidaBase(); // lote1 tenia 100, tomo 30, quedan 70

        $this->actingAs($this->user);

        // Pedimos 150 (max disponible es 70 actual + 30 de la salida = 100)
        $resp = $this->putJson("/api/salidas/{$salida->id}", [
            'detalle' => [
                ['lote_id' => $this->lote1->id, 'cantidad' => 150],
            ],
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);

        // DB debe haber revertido todo
        $this->assertEquals(70, $this->lote1->fresh()->cantidad_actual);
        $this->assertEquals(30, $salida->fresh()->detalles->first()->cantidad);
    }

    public function test_lote_controller_retorna_stock_disponible_considerando_salida_en_edicion(): void
    {
        $salida = $this->crearSalidaBase(); // lote1 tiene cantidad_actual = 70, pero la salida tiene 30

        $this->actingAs($this->user);

        // Consultar lotes con ?salida_id
        $resp = $this->getJson("/api/medicamentos/{$this->med1->id}/lotes?salida_id={$salida->id}");

        $resp->assertOk();
        $lotes = $resp->json();
        $this->assertCount(1, $lotes);
        $this->assertEquals(100, $lotes[0]['cantidad_disponible']);
        $this->assertEquals(70, $lotes[0]['cantidad_actual']);
        $this->assertEquals(30, $lotes[0]['salida_prestada']);
    }
}
