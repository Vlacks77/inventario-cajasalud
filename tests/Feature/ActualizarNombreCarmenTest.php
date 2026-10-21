<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarNombreCarmenTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_actualiza_nombre_carmen_en_todas_las_tablas(): void
    {
        // El usuario dra.carmen-almacen ya fue creado por las migraciones previas
        // y la nueva migración debió actualizar su nombre.
        $user = DB::table('users')->where('username', 'dra.carmen-almacen')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Dra. Carmen Loza Gutierrez- Encargada de Almacen', $user->name);

        $provId = DB::table('proveedores')->insertGetId([
            'nombre'     => 'PROVEEDOR TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $estId = DB::table('establecimientos')->insertGetId([
            'nombre'     => 'POLICLINICO TEST',
            'tipo'       => 'Policlínico',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulamos un ingreso con el nombre antiguo
        $ingresoId = DB::table('ingresos')->insertGetId([
            'proveedor_id'   => $provId,
            'numero_nota'    => 'N.º 9999',
            'fecha_ingreso'  => '2026-10-21',
            'almacen'        => 'REGIONAL LA PAZ',
            'tipo_ingreso'   => 'compra_local',
            'recibido_por'   => 'Dra. Carmen Gutierrez- Encargada de Almacen',
            'autorizado_por' => 'CAJA NACIONAL DE CAMINOS',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $salidaId = DB::table('salidas')->insertGetId([
            'usuario_id'          => $user->id,
            'establecimiento_id'  => $estId,
            'numero_salida'       => 9999,
            'fecha_salida'        => '2026-10-21',
            'almacen_origen'      => 'REGIONAL LA PAZ',
            'solicitado_por'      => 'Dra. Carmen Gutierrez- Encargada de Almacen',
            'entregado_a'         => 'Dra. Carmen Gutierrez- Encargada de Almacen',
            'tipo_salida'         => 'normal',
            'estado'              => 'ACTIVA',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Al correr up() de nuevo sobre estos registros
        $migration = require database_path('migrations/2026_10_21_000001_actualizar_nombre_carmen_loza_gutierrez.php');
        $migration->up();

        // Verificar que los registros históricos quedaron actualizados
        $ingreso = DB::table('ingresos')->where('id', $ingresoId)->first();
        $this->assertEquals('Dra. Carmen Loza Gutierrez- Encargada de Almacen', $ingreso->recibido_por);

        $salida = DB::table('salidas')->where('id', $salidaId)->first();
        $this->assertEquals('Dra. Carmen Loza Gutierrez- Encargada de Almacen', $salida->solicitado_por);
        $this->assertEquals('Dra. Carmen Loza Gutierrez- Encargada de Almacen', $salida->entregado_a);

        // Probar rollback (down)
        $migration->down();

        $userRevertido = DB::table('users')->where('username', 'dra.carmen-almacen')->first();
        $this->assertEquals('Dra. Carmen Gutierrez- Encargada de Almacen', $userRevertido->name);

        $ingresoRevertido = DB::table('ingresos')->where('id', $ingresoId)->first();
        $this->assertEquals('Dra. Carmen Gutierrez- Encargada de Almacen', $ingresoRevertido->recibido_por);
    }
}
