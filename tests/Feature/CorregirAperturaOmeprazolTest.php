<?php

namespace Tests\Feature;

use App\Models\CierreMensual;
use App\Models\CierreMensualDetalle;
use App\Models\Ingreso;
use App\Models\Lote;
use App\Models\Medicamento;
use App\Models\PartidaPresupuestaria;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CorregirAperturaOmeprazolTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_corrige_apertura_omeprazol_a0205(): void
    {
        $user = User::factory()->create([
            'username' => 'almacen_omeprazol_test',
            'role'     => 'almacen',
            'regional' => 'La Paz',
        ]);

        $partida = PartidaPresupuestaria::firstOrCreate(
            ['codigo' => '34200'],
            ['nombre' => 'PRODUCTOS FARMACEUTICOS']
        );

        $proveedor = Proveedor::firstOrCreate(
            ['nombre' => 'PROVEEDOR CENTRAL'],
            ['estado' => true]
        );

        $med = Medicamento::firstOrCreate(
            ['codigo' => 'A0205'],
            [
                'partida_presupuestaria_id' => $partida->id,
                'nombre'                    => 'Omeprazol 40 mg/ml',
                'forma_farmaceutica'        => 'Inyectable',
                'concentracion'             => '40 mg/ml',
                'unidad_presentacion'       => 'Unidad',
                'stock_minimo'              => 10,
                'estado'                    => true,
            ]
        );

        $ingresoApertura = Ingreso::create([
            'id'                  => 1,
            'proveedor_id'        => $proveedor->id,
            'almacen'             => 'REGIONAL LA PAZ',
            'fecha_ingreso'       => '2026-10-01',
            'numero_nota'         => 'APT-20261001-001',
            'tipo_ingreso'        => 'apertura',
            'recibido_por'        => 'Sistema',
        ]);

        // Estado inicial con valores erróneos
        $lote = Lote::create([
            'ingreso_id'        => $ingresoApertura->id,
            'medicamento_id'    => $med->id,
            'proveedor_id'      => $proveedor->id,
            'codigo_lote'       => 'APT-20261001-A0205',
            'fecha_vencimiento' => '2030-12-31',
            'cantidad_inicial'  => 250,
            'cantidad_actual'   => 250,
            'precio_unitario'   => 8.150000,
            'importe_total'     => 2037.50,
            'es_reembolso'      => false,
        ]);

        $cierre = CierreMensual::create([
            'id'                => 1,
            'almacen'           => 'REGIONAL LA PAZ',
            'periodo'           => '2026-09-01',
            'fecha_desde'       => '2026-09-01',
            'fecha_hasta'       => '2026-09-30',
            'estado'            => 'CERRADO',
            'importe_saldo_mes' => 2037.50,
        ]);

        $detalleCierre = CierreMensualDetalle::create([
            'cierre_mensual_id'    => $cierre->id,
            'medicamento_id'       => $med->id,
            'codigo'               => 'A0205',
            'descripcion'          => 'Omeprazol',
            'saldo_mes_cantidad'   => 250.000,
            'saldo_mes_precio'     => 8.150000,
            'saldo_mes_importe'    => 2037.50,
        ]);

        // Ejecutar migración
        $migration = require database_path('migrations/2026_10_14_000000_corregir_apertura_omeprazol_a0205.php');
        $migration->up();

        // 1. Verificar lote corregido
        $lote->refresh();
        $this->assertEquals(650, $lote->cantidad_inicial);
        $this->assertEquals(650, $lote->cantidad_actual);
        $this->assertEquals(9.618570, (float) $lote->precio_unitario);
        $this->assertEquals(6252.07, (float) $lote->importe_total);

        // 2. Verificar detalle cierre corregido
        $detalleCierre->refresh();
        $this->assertEquals(650.000, (float) $detalleCierre->saldo_mes_cantidad);
        $this->assertEquals(9.618570, (float) $detalleCierre->saldo_mes_precio);
        $this->assertEquals(6252.07, (float) $detalleCierre->saldo_mes_importe);

        // 3. Verificar cabecera cierre recalculada
        $cierre->refresh();
        $this->assertEquals(6252.07, (float) $cierre->importe_saldo_mes);

        // 4. Verificar Inventario API
        $this->actingAs($user);
        $respInv = $this->getJson('/api/inventario?buscar=A0205');
        $respInv->assertOk();
        $dataInv = $respInv->json();
        $this->assertNotEmpty($dataInv);
        $this->assertEquals(650, $dataInv[0]['stock_total']);
        $this->assertEquals(9.618570, (float) $dataInv[0]['lotes'][0]['precio_unitario']);

        // 5. Verificar Kardex API
        $respKardex = $this->getJson('/api/kardex?buscar=A0205');
        $respKardex->assertOk();
        $dataKardex = $respKardex->json();
        $this->assertNotEmpty($dataKardex);
        $this->assertEquals(650, $dataKardex[0]['cantidad']);
        $this->assertEquals(9.618570, (float) $dataKardex[0]['precio_unitario']);
        $this->assertEquals(6252.07, (float) $dataKardex[0]['total']);

        // 6. Probar rollback (down)
        $migration->down();
        $lote->refresh();
        $this->assertEquals(250, $lote->cantidad_inicial);
        $this->assertEquals(250, $lote->cantidad_actual);
        $this->assertEquals(8.150000, (float) $lote->precio_unitario);
        $this->assertEquals(2037.50, (float) $lote->importe_total);

        $detalleCierre->refresh();
        $this->assertEquals(250.000, (float) $detalleCierre->saldo_mes_cantidad);
        $this->assertEquals(8.150000, (float) $detalleCierre->saldo_mes_precio);
        $this->assertEquals(2037.50, (float) $detalleCierre->saldo_mes_importe);
    }
}
