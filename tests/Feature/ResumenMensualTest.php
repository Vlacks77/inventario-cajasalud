<?php

namespace Tests\Feature;

use App\Models\CierreMensual;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResumenMensualTest extends TestCase
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
            'username' => 'carmen_almacen',
            'role' => 'almacen',
            'regional' => 'La Paz',
        ]);

        $partida = PartidaPresupuestaria::firstOrCreate(
            ['codigo' => '34200'],
            ['nombre' => 'Medicamentos']
        );

        $this->medicamento = Medicamento::firstOrCreate(
            ['codigo' => 'J0101'],
            [
                'partida_presupuestaria_id' => $partida->id,
                'nombre' => 'Amoxicilina 500 mg',
                'concentracion' => '500 mg',
                'forma_farmaceutica' => 'Cápsula',
                'unidad_presentacion' => 'Caja x 100',
                'grupo_producto' => 'MEDICAMENTOS',
                'estado' => true,
            ]
        );

        $this->proveedor = Proveedor::create([
            'nombre' => 'Droguería INTI S.A.',
            'nit' => '1020304050',
        ]);

        $this->establecimiento = Establecimiento::create([
            'nombre' => 'Hospital Obrero N° 1',
            'tipo' => 'HOSPITAL',
            'municipio' => 'La Paz',
        ]);
    }

    public function test_resumen_mensual_retorna_20_partidas_oficiales_y_cuadre_exacto(): void
    {
        Sanctum::actingAs($this->user);

        // Simulamos un ingreso por compra local y otro por transferencia en el mes
        $fechaIngreso = '2026-10-05';

        $ingresoCompra = Ingreso::create([
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => $this->user->id,
            'almacen' => 'REGIONAL LA PAZ',
            'fecha_ingreso' => $fechaIngreso,
            'tipo_ingreso' => 'compra_local',
            'recibido_por' => 'Dra. Carmen',
            'autorizado_por' => 'Jefatura',
        ]);

        Lote::create([
            'ingreso_id' => $ingresoCompra->id,
            'medicamento_id' => $this->medicamento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo_lote' => 'LOTE-COMPRA-01',
            'cantidad_inicial' => 100,
            'cantidad_actual' => 100,
            'precio_unitario' => 10.50,
            'importe_total' => 1050.00,
            'fecha_vencimiento' => '2028-01-01',
        ]);

        $ingresoTransf = Ingreso::create([
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => $this->user->id,
            'almacen' => 'REGIONAL LA PAZ',
            'fecha_ingreso' => $fechaIngreso,
            'tipo_ingreso' => 'transferencia_regional',
            'recibido_por' => 'Dra. Carmen',
            'autorizado_por' => 'Jefatura',
        ]);

        Lote::create([
            'ingreso_id' => $ingresoTransf->id,
            'medicamento_id' => $this->medicamento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo_lote' => 'LOTE-TRANSF-01',
            'cantidad_inicial' => 50,
            'cantidad_actual' => 50,
            'precio_unitario' => 10.00,
            'importe_total' => 500.00,
            'fecha_vencimiento' => '2028-01-01',
        ]);

        $response = $this->getJson('/api/inventario/resumen-mensual?mes=10&anio=2026');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(20, count($data['tabla_a']));
        $this->assertEquals(20, count($data['tabla_b']));
        $this->assertTrue($data['cuadre_exacto']);

        // Verificar discriminación de ingresos
        $filaMedicamentos = collect($data['tabla_a'])->firstWhere('grupo', 'MEDICAMENTOS');
        $this->assertNotNull($filaMedicamentos);
        $this->assertEquals(1050.00, $filaMedicamentos['compras_locales']);
        $this->assertEquals(500.00, $filaMedicamentos['transferencias']);
        $this->assertEquals(1550.00, $filaMedicamentos['total_ingresos']);
        $this->assertEquals(1550.00, $filaMedicamentos['saldo_mes']);

        // Verificar cuadre Tabla B con Tabla A
        $filaB = collect($data['tabla_b'])->firstWhere('descripcion', 'MEDICAMENTOS');
        $this->assertNotNull($filaB);
        $this->assertEquals(150, $filaB['cantidades']['entradas']);
        $this->assertEquals(150, $filaB['cantidades']['saldo_final']);
        $this->assertEquals(1550.00, $filaB['valores']['entradas']);
        $this->assertEquals(1550.00, $filaB['valores']['saldo_final']);

        // Verificar totales
        $this->assertEquals($data['totales_a']['total_ingresos'], $data['totales_b']['valores']['entradas']);
        $this->assertEquals($data['totales_a']['saldo_mes'], $data['totales_b']['valores']['saldo_final']);
    }

    public function test_descarga_excel_resumen_mensual_incluye_tablas_y_firmas(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/api/inventario/resumen-mensual/excel?mes=10&anio=2026');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $content = $response->getContent();

        $this->assertStringContainsString('TABLA A: RESUMEN CUENTA 121', $content);
        $this->assertStringContainsString('TABLA B: RESUMEN MENSUAL REGIONAL', $content);
        $this->assertStringContainsString('Dra. Carmen', $content);
        $this->assertStringContainsString('ADMINISTRADOR / CONTADOR', $content);
        $this->assertStringContainsString('JEFATURA MÉDICA / REGIONAL', $content);
    }

    public function test_congelar_cierre_mensual_persiste_snapshot(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/inventario/resumen-mensual/cerrar', [
            'periodo' => '2026-10',
            'almacen' => 'REGIONAL LA PAZ',
            'observacion' => 'Cierre oficial auditado por Dra. Carmen',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull(CierreMensual::whereDate('periodo', '2026-10-01')->where('estado', 'CERRADO')->first());

        // Consulta posterior devuelve estado CERRADO
        $consulta = $this->getJson('/api/inventario/resumen-mensual?periodo=2026-10');
        $consulta->assertStatus(200);
        $this->assertEquals('CERRADO', $consulta->json('estado'));
    }
}
