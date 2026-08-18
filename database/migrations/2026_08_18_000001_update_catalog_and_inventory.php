<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('medicamentos', 'grupo_producto')) {
            Schema::table('medicamentos', function (Blueprint $table) {
                $table->string('grupo_producto', 150)->nullable()->after('tipo_producto');
            });
        }

        $partidas = [
            ['codigo' => '32100', 'nombre' => 'Papeles'],
            ['codigo' => '33100', 'nombre' => 'Hilados, telas, fibras y algodón'],
            ['codigo' => '33200', 'nombre' => 'Confecciones textiles'],
            ['codigo' => '33300', 'nombre' => 'Prendas de vestir'],
            ['codigo' => '33400', 'nombre' => 'Calzados'],
            ['codigo' => '34200', 'nombre' => 'Medicamentos'],
            ['codigo' => '34500', 'nombre' => 'Productos minerales no metálicos y plásticos'],
            ['codigo' => '39400', 'nombre' => 'Insumos médicos, instrumental y prótesis'],
            ['codigo' => '39500', 'nombre' => 'Útiles de escritorio y oficina'],
            ['codigo' => '39700', 'nombre' => 'Útiles y materiales eléctricos'],
            ['codigo' => '39800', 'nombre' => 'Otros repuestos y accesorios'],
            ['codigo' => '39990', 'nombre' => 'Otros materiales y suministros'],
        ];

        foreach ($partidas as $partida) {
            $exists = DB::table('partidas_presupuestarias')->where('codigo', $partida['codigo'])->exists();
            $values = ['nombre' => $partida['nombre'], 'estado' => true, 'updated_at' => now()];

            if ($exists) {
                DB::table('partidas_presupuestarias')->where('codigo', $partida['codigo'])->update($values);
            } else {
                DB::table('partidas_presupuestarias')->insert($values + ['codigo' => $partida['codigo'], 'created_at' => now()]);
            }
        }

        $partidaIds = DB::table('partidas_presupuestarias')
            ->whereIn('codigo', array_column($partidas, 'codigo'))
            ->pluck('id', 'codigo');

        $path = database_path('data/catalogo_institucional.json');
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró database/data/catalogo_institucional.json. Instala el parche completo del catálogo.');
        }

        $catalogo = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach (array_chunk($catalogo, 250) as $lote) {
            foreach ($lote as $producto) {
                $existing = DB::table('medicamentos')->where('codigo', $producto['code'])->first();

                $data = [
                    'partida_presupuestaria_id' => $partidaIds[$producto['part']],
                    'nombre' => $producto['name'],
                    'tipo_producto' => $producto['tipo'],
                    'concentracion' => $producto['concentration'],
                    'forma_farmaceutica' => $producto['form'],
                    'unidad_presentacion' => $producto['unit'],
                    'grupo_producto' => $producto['group'],
                    'estado' => true,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    // Actualizamos solamente datos maestros. Nunca tocamos stock, lotes,
                    // precios ni movimientos existentes.
                    DB::table('medicamentos')
                        ->where('id', $existing->id)
                        ->update($data);
                } else {
                    $data['codigo'] = $producto['code'];
                    $data['stock_minimo'] = 0;
                    $data['descripcion'] = null;
                    $data['created_at'] = now();

                    DB::table('medicamentos')->insert($data);
                }
            }
        }
    }

    public function down(): void
    {
        // No eliminamos productos ni datos históricos al revertir.
        // Solamente quitamos la columna añadida por esta migración.
        if (Schema::hasColumn('medicamentos', 'grupo_producto')) {
            Schema::table('medicamentos', function (Blueprint $table) {
                $table->dropColumn('grupo_producto');
            });
        }
    }
};
