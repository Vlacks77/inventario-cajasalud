<?php

namespace Tests\Feature;

use App\Models\CierreMensual;
use App\Models\DetalleSalida;
use App\Models\Establecimiento;
use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\PartidaPresupuestaria;
use App\Models\Proveedor;
use App\Models\Salida;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngresoEdicionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Proveedor $proveedor;
    protected Medicamento $med1;
    protected Medicamento $med2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'almacen_ingreso_editor',
            'name'     => 'Encargada Ingresos',
            'role'     => 'almacen',
            'regional' => 'La Paz',
        ]);

        $this->proveedor = Proveedor::create([
            'nombre'   => 'DROGUERIA INTI S.A.',
            'telefono' => '22445566',
            'estado'   => true,
        ]);

        $partida = PartidaPresupuestaria::firstOrCreate(
            ['codigo' => 'P-342'],
            ['nombre' => 'PRODUCTOS FARMACEUTICOS']
        );

        $this->med1 = Medicamento::create([
            'codigo'                    => 'MED-01',
            'partida_presupuestaria_id' => $partida->id,
            'nombre'                    => 'AMOXICILINA 500MG',
            'forma_farmaceutica'        => 'CAPSULA',
            'concentracion'             => '500 mg',
            'unidad_presentacion'       => 'CAPSULA',
            'precio_referencial'        => 0.40,
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
    }

    private function crearIngresoBase(array $overrides = []): Ingreso
    {
        $ingreso = Ingreso::create(array_merge([
            'proveedor_id'        => $this->proveedor->id,
            'usuario_id'          => $this->user->id,
            'almacen'             => 'REGIONAL LA PAZ',
            'fecha_ingreso'       => '2026-10-15',
            'numero_nota'         => 'N.º 513',
            'correlativo_anual'   => 513,
            'numero_remision'     => 'REM-2026-001',
            'numero_factura'      => 'FAC-9988',
            'numero_orden_compra' => 'OC-1122',
            'tipo_ingreso'        => 'compra_local',
            'observacion'         => 'Recepción original de medicamentos',
            'recibido_por'        => 'Encargada Ingresos',
            'autorizado_por'      => 'CAJA NACIONAL DE CAMINOS',
        ], $overrides));

        Lote::create([
            'ingreso_id'        => $ingreso->id,
            'medicamento_id'    => $this->med1->id,
            'proveedor_id'      => $this->proveedor->id,
            'usuario_id'        => $this->user->id,
            'codigo_lote'       => 'LOTE-AMX-01',
            'fecha_vencimiento' => '2028-12-31',
            'cantidad_inicial'  => 100,
            'cantidad_actual'   => 100,
            'precio_unitario'   => 0.35,
            'importe_total'     => 35.00,
            'es_reembolso'      => false,
        ]);

        return $ingreso;
    }

    public function test_obtener_ingreso_para_edicion(): void
    {
        $ingreso = $this->crearIngresoBase();

        $this->actingAs($this->user);
        $resp = $this->getJson("/api/ingresos/{$ingreso->id}");

        $resp->assertOk()
            ->assertJsonPath('numero_nota', 'N.º 513')
            ->assertJsonPath('puede_editar', true)
            ->assertJsonPath('periodo_cerrado', false)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.codigo_lote', 'LOTE-AMX-01')
            ->assertJsonPath('items.0.cantidad', 100);
    }

    public function test_editar_ingreso_actualiza_cabecera_y_lotes_existentes(): void
    {
        $ingreso = $this->crearIngresoBase();
        $loteOriginal = $ingreso->lotes->first();

        $this->actingAs($this->user);
        $payload = [
            'ingreso' => [
                'almacen'             => 'REGIONAL LA PAZ - MODIFICADO',
                'fecha_ingreso'       => '2026-10-15',
                'numero_factura'      => 'FAC-NUEVA-001',
                'numero_remision'     => 'REM-NUEVA-001',
                'numero_orden_compra' => 'OC-NUEVA-001',
                'recibido_por'        => 'Responsable Modificado',
                'observacion'         => 'Corrección de transcripción',
            ],
            'proveedor' => [
                'nombre' => 'DROGUERIA INTI S.A.',
            ],
            'items' => [
                [
                    'id'                => $loteOriginal->id,
                    'producto_id'       => $this->med1->id,
                    'codigo_lote'       => 'LOTE-AMX-01-CORR',
                    'fecha_vencimiento' => '2028-12-31',
                    'cantidad'          => 120, // Aumenta de 100 a 120
                    'precio_unitario'   => 0.38,
                ],
            ],
        ];

        $resp = $this->putJson("/api/ingresos/{$ingreso->id}", $payload);

        $resp->assertOk()
            ->assertJson(['success' => true]);

        $ingresoActualizado = $ingreso->fresh();

        // El correlativo original NO cambia
        $this->assertEquals('N.º 513', $ingresoActualizado->numero_nota);
        $this->assertEquals(513, $ingresoActualizado->correlativo_anual);

        // Cabecera actualizada y bitácora
        $this->assertEquals('FAC-NUEVA-001', $ingresoActualizado->numero_factura);
        $this->assertEquals('REGIONAL LA PAZ - MODIFICADO', $ingresoActualizado->almacen);
        $this->assertStringContainsString('Modificado el', $ingresoActualizado->observacion);
        $this->assertStringContainsString('Encargada Ingresos', $ingresoActualizado->observacion);

        // Lote actualizado
        $loteActualizado = $loteOriginal->fresh();
        $this->assertEquals('LOTE-AMX-01-CORR', $loteActualizado->codigo_lote);
        $this->assertEquals(120, $loteActualizado->cantidad_inicial);
        $this->assertEquals(120, $loteActualizado->cantidad_actual);
        $this->assertEquals(0.38, (float) $loteActualizado->precio_unitario);
        $this->assertEquals(round(120 * 0.38, 2), (float) $loteActualizado->importe_total);
    }

    public function test_editar_ingreso_agrega_nuevo_item_a_nota_existente(): void
    {
        $ingreso = $this->crearIngresoBase();
        $loteOriginal = $ingreso->lotes->first();

        $this->actingAs($this->user);
        $payload = [
            'ingreso' => [
                'almacen'       => 'REGIONAL LA PAZ',
                'fecha_ingreso' => '2026-10-15',
                'recibido_por'  => 'Encargada Ingresos',
            ],
            'items' => [
                [
                    'id'              => $loteOriginal->id,
                    'producto_id'     => $this->med1->id,
                    'codigo_lote'     => $loteOriginal->codigo_lote,
                    'cantidad'        => 100,
                    'precio_unitario' => 0.35,
                ],
                [
                    // Ítem nuevo que se le había olvidado transcribir
                    'producto_id'       => $this->med2->id,
                    'codigo_lote'       => 'LOTE-IBU-NEW',
                    'fecha_vencimiento' => '2029-06-30',
                    'cantidad'          => 40,
                    'precio_unitario'   => 0.45,
                ],
            ],
        ];

        $resp = $this->putJson("/api/ingresos/{$ingreso->id}", $payload);
        $resp->assertOk()->assertJson(['success' => true]);

        $this->assertCount(2, $ingreso->fresh()->lotes);

        $nuevoLote = Lote::where('ingreso_id', $ingreso->id)->where('codigo_lote', 'LOTE-IBU-NEW')->first();
        $this->assertNotNull($nuevoLote);
        $this->assertEquals($this->med2->id, $nuevoLote->medicamento_id);
        $this->assertEquals(40, $nuevoLote->cantidad_inicial);
        $this->assertEquals(40, $nuevoLote->cantidad_actual);
        $this->assertEquals(0.45, (float) $nuevoLote->precio_unitario);
        $this->assertEquals(round(40 * 0.45, 2), (float) $nuevoLote->importe_total);
    }

    public function test_editar_ingreso_elimina_lote_sin_salidas_de_forma_segura(): void
    {
        $ingreso = $this->crearIngresoBase();
        $lote1 = $ingreso->lotes->first();

        // Creamos segundo lote sin salidas
        $lote2 = Lote::create([
            'ingreso_id'        => $ingreso->id,
            'medicamento_id'    => $this->med2->id,
            'proveedor_id'      => $this->proveedor->id,
            'usuario_id'        => $this->user->id,
            'codigo_lote'       => 'LOTE-SOBRANTE',
            'fecha_vencimiento' => '2028-12-31',
            'cantidad_inicial'  => 50,
            'cantidad_actual'   => 50,
            'precio_unitario'   => 0.50,
            'importe_total'     => 25.00,
            'es_reembolso'      => false,
        ]);

        $this->assertCount(2, $ingreso->fresh()->lotes);

        $this->actingAs($this->user);
        // Enviamos solamente lote1 (lote2 fue eliminado del formulario)
        $payload = [
            'items' => [
                [
                    'id'              => $lote1->id,
                    'producto_id'     => $this->med1->id,
                    'codigo_lote'     => $lote1->codigo_lote,
                    'cantidad'        => 100,
                    'precio_unitario' => 0.35,
                ],
            ],
        ];

        $resp = $this->putJson("/api/ingresos/{$ingreso->id}", $payload);
        $resp->assertOk()->assertJson(['success' => true]);

        $this->assertCount(1, $ingreso->fresh()->lotes);
        $this->assertDatabaseMissing('lotes', ['id' => $lote2->id]);
    }

    public function test_bloquea_eliminar_lote_con_despachos_registrados(): void
    {
        $ingreso = $this->crearIngresoBase();
        $lote1 = $ingreso->lotes->first();

        // Registramos una salida para el lote1 (se despacharon 20 unidades)
        $establecimiento = Establecimiento::create(['nombre' => 'C.S. TEST', 'tipo' => 'CENTRO DE SALUD', 'estado' => true]);
        $salida = Salida::create([
            'numero_salida'      => 'S-2026-0099',
            'fecha_salida'       => '2026-10-16',
            'almacen_origen'     => 'REGIONAL LA PAZ',
            'establecimiento_id' => $establecimiento->id,
            'solicitado_por'     => 'Dr. Test',
            'usuario_id'         => $this->user->id,
        ]);
        DetalleSalida::create([
            'salida_id'       => $salida->id,
            'lote_id'         => $lote1->id,
            'cantidad'        => 20,
            'precio_unitario' => 0.35,
            'subtotal'        => 7.00,
        ]);
        $lote1->decrement('cantidad_actual', 20); // Quedan 80 de 100

        // Intentamos enviar la nota con un lote nuevo omitiendo lote1 (intentando eliminarlo)
        $this->actingAs($this->user);
        $payload = [
            'items' => [
                [
                    'producto_id'     => $this->med2->id,
                    'codigo_lote'     => 'LOTE-NUEVO-REEMPLAZO',
                    'cantidad'        => 30,
                    'precio_unitario' => 0.50,
                ],
            ],
        ];

        $resp = $this->putJson("/api/ingresos/{$ingreso->id}", $payload);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // El lote original NO debe haberse eliminado
        $this->assertDatabaseHas('lotes', ['id' => $lote1->id]);
    }

    public function test_bloquea_reducir_cantidad_por_debajo_de_despachos_registrados(): void
    {
        $ingreso = $this->crearIngresoBase();
        $lote1 = $ingreso->lotes->first();

        // Se despacharon 40 unidades (quedan 60 de 100)
        $establecimiento = Establecimiento::create(['nombre' => 'C.S. TEST 2', 'tipo' => 'CENTRO DE SALUD', 'estado' => true]);
        $salida = Salida::create([
            'numero_salida'      => 'S-2026-0100',
            'fecha_salida'       => '2026-10-16',
            'almacen_origen'     => 'REGIONAL LA PAZ',
            'establecimiento_id' => $establecimiento->id,
            'solicitado_por'     => 'Dr. Test',
            'usuario_id'         => $this->user->id,
        ]);
        DetalleSalida::create([
            'salida_id'       => $salida->id,
            'lote_id'         => $lote1->id,
            'cantidad'        => 40,
            'precio_unitario' => 0.35,
            'subtotal'        => 14.00,
        ]);
        $lote1->decrement('cantidad_actual', 40);

        $this->actingAs($this->user);

        // Intento 1: Reducir cantidad inicial a 30 (menor a los 40 despachados) -> RECHAZADO
        $resp1 = $this->putJson("/api/ingresos/{$ingreso->id}", [
            'items' => [
                [
                    'id'              => $lote1->id,
                    'producto_id'     => $this->med1->id,
                    'codigo_lote'     => $lote1->codigo_lote,
                    'cantidad'        => 30,
                    'precio_unitario' => 0.35,
                ],
            ],
        ]);
        $resp1->assertStatus(422)->assertJsonValidationErrors(['items']);

        // Intento 2: Reducir cantidad inicial a 60 (mayor o igual a los 40 despachados) -> PERMITIDO
        // cantidad_actual debe ser 60 - 40 = 20
        $resp2 = $this->putJson("/api/ingresos/{$ingreso->id}", [
            'items' => [
                [
                    'id'              => $lote1->id,
                    'producto_id'     => $this->med1->id,
                    'codigo_lote'     => $lote1->codigo_lote,
                    'cantidad'        => 60,
                    'precio_unitario' => 0.35,
                ],
            ],
        ]);
        $resp2->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(60, $lote1->fresh()->cantidad_inicial);
        $this->assertEquals(20, $lote1->fresh()->cantidad_actual);
    }

    public function test_bloquea_editar_ingreso_en_periodo_cerrado(): void
    {
        $ingreso = $this->crearIngresoBase();

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
        $getResp = $this->getJson("/api/ingresos/{$ingreso->id}");
        $getResp->assertOk()
            ->assertJsonPath('puede_editar', false)
            ->assertJsonPath('periodo_cerrado', true);

        // Intentamos PUT
        $resp = $this->putJson("/api/ingresos/{$ingreso->id}", [
            'items' => [
                [
                    'producto_id'     => $this->med1->id,
                    'codigo_lote'     => 'LOT-01',
                    'cantidad'        => 50,
                    'precio_unitario' => 0.35,
                ],
            ],
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['periodo']);
    }

    public function test_bloquea_editar_ingreso_tipo_apertura_o_reembolso(): void
    {
        $ingresoApertura = $this->crearIngresoBase(['tipo_ingreso' => 'apertura']);
        $ingresoReembolso = $this->crearIngresoBase(['tipo_ingreso' => 'reembolso', 'numero_nota' => 'N.º 514']);

        $this->actingAs($this->user);

        // GET verifica puede_editar es false
        $this->getJson("/api/ingresos/{$ingresoApertura->id}")
            ->assertOk()
            ->assertJsonPath('puede_editar', false);

        $this->getJson("/api/ingresos/{$ingresoReembolso->id}")
            ->assertOk()
            ->assertJsonPath('puede_editar', false);

        // PUT a apertura bloqueado
        $respApertura = $this->putJson("/api/ingresos/{$ingresoApertura->id}", [
            'items' => [
                ['producto_id' => $this->med1->id, 'codigo_lote' => 'LOTE-X', 'cantidad' => 10, 'precio_unitario' => 1],
            ],
        ]);
        $respApertura->assertStatus(422)->assertJsonValidationErrors(['tipo_ingreso']);

        // PUT a reembolso bloqueado
        $respReembolso = $this->putJson("/api/ingresos/{$ingresoReembolso->id}", [
            'items' => [
                ['producto_id' => $this->med1->id, 'codigo_lote' => 'LOTE-Y', 'cantidad' => 10, 'precio_unitario' => 1],
            ],
        ]);
        $respReembolso->assertStatus(422)->assertJsonValidationErrors(['tipo_ingreso']);
    }
}
