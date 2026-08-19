<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normaliza el catálogo institucional sin modificar stock, lotes,
        // precios ni movimientos históricos.
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
            $existente = DB::table('partidas_presupuestarias')
                ->where('codigo', $partida['codigo'])
                ->first();

            if ($existente) {
                DB::table('partidas_presupuestarias')
                    ->where('id', $existente->id)
                    ->update([
                        'nombre' => $partida['nombre'],
                        'estado' => true,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('partidas_presupuestarias')->insert([
                    'codigo' => $partida['codigo'],
                    'nombre' => $partida['nombre'],
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $partidaIds = DB::table('partidas_presupuestarias')
            ->whereIn('codigo', array_column($partidas, 'codigo'))
            ->pluck('id', 'codigo');

        $path = database_path('data/catalogo_institucional.json');
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo institucional actualizado.');
        }

        $catalogo = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach (array_chunk($catalogo, 250) as $bloque) {
            foreach ($bloque as $producto) {
                if (empty($producto['code']) || empty($producto['part']) || !isset($partidaIds[$producto['part']])) {
                    continue;
                }

                $data = [
                    'partida_presupuestaria_id' => $partidaIds[$producto['part']],
                    'nombre' => $producto['name'],
                    // La clasificación global es única; el detalle real está en grupo_producto.
                    'tipo_producto' => 'MEDICAMENTOS E INSUMOS MEDICOS',
                    'grupo_producto' => $producto['group'] ?: null,
                    'concentracion' => $producto['concentration'] ?: '',
                    'forma_farmaceutica' => $producto['form'] ?: '',
                    'updated_at' => now(),
                ];

                $existing = DB::table('medicamentos')
                    ->where('codigo', $producto['code'])
                    ->orderBy('id')
                    ->first();

                if ($existing) {
                    DB::table('medicamentos')->where('id', $existing->id)->update($data);
                } else {
                    DB::table('medicamentos')->insert($data + [
                        'codigo' => $producto['code'],
                        'unidad_presentacion' => $producto['unit'] ?: 'Unidad',
                        'stock_minimo' => 0,
                        'descripcion' => null,
                        'estado' => true,
                        'created_at' => now(),
                    ]);
                }
            }
        }

        // Corrección explícita solicitada para productos que históricamente
        // quedaron con información equivocada.
        $j0501Partida = $partidaIds['34200'];
        DB::table('medicamentos')->where('codigo', 'J0501')->update([
            'partida_presupuestaria_id' => $j0501Partida,
            'nombre' => 'Abacavir 300 mg',
            'tipo_producto' => 'MEDICAMENTOS E INSUMOS MEDICOS',
            'grupo_producto' => 'MEDICAMENTOS',
            'concentracion' => '300 mg',
            'forma_farmaceutica' => 'Comprimido',
            'updated_at' => now(),
        ]);

        DB::table('medicamentos')->where('codigo', 'L0209')->update([
            'partida_presupuestaria_id' => $j0501Partida,
            'nombre' => 'Abiraterona Acetato 250 mg',
            'tipo_producto' => 'MEDICAMENTOS E INSUMOS MEDICOS',
            'grupo_producto' => 'MEDICAMENTOS',
            'concentracion' => '250 mg',
            'forma_farmaceutica' => 'Comprimido',
            'updated_at' => now(),
        ]);

        DB::table('medicamentos')->where('codigo', 'D0603')->update([
            'partida_presupuestaria_id' => $j0501Partida,
            'nombre' => 'Aciclovir 5%',
            'tipo_producto' => 'MEDICAMENTOS E INSUMOS MEDICOS',
            'grupo_producto' => 'MEDICAMENTOS',
            'concentracion' => '5%',
            'forma_farmaceutica' => 'Crema dermica',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No revertimos datos maestros ni movimientos para evitar pérdida de
        // información histórica. Esta migración es deliberadamente no destructiva.
    }
};
