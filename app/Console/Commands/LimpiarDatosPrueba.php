<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarDatosPrueba extends Command
{
    protected $signature = 'inventario:limpiar-datos-prueba
                            {--force : Omitir confirmacion interactiva}';

    protected $description = 'FASE 2 - Limpieza transaccional de datos de prueba antes de cargar inventario real (01/10/2026). NO toca users, partidas_presupuestarias ni migrations.';

    private array $tablasOperativas = [
        'detalle_salidas',
        'salidas',
        'lotes',
        'ingresos',
        'cierre_mensual_detalles',
        'cierres_mensuales',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('--- FASE 2 - LIMPIEZA TRANSACCIONAL DE DATOS DE PRUEBA ---');
        $this->line('    Inventario Real -> 01 de Octubre de 2026');
        $this->line('-----------------------------------------------------------');
        $this->newLine();

        $this->info('Conteo de registros ANTES de la limpieza:');
        $this->mostrarConteos();
        $this->newLine();

        if (! $this->option('force')) {
            $this->warn('ADVERTENCIA: Esta operacion es IRREVERSIBLE.');
            if (! $this->confirm('Confirmar limpieza completa?', false)) {
                $this->error('Operacion cancelada por el usuario.');
                return Command::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Iniciando transaccion de limpieza (usando DELETE para compatibilidad transaccional)...');
        $this->newLine();

        $resultados = [];
        $errorOcurrido = false;

        try {
            // NOTA: TRUNCATE en MySQL/MariaDB genera un commit implicito y no es transaccional.
            // Usamos DELETE + ALTER TABLE AUTO_INCREMENT fuera de la transaccion.
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            DB::beginTransaction();

            foreach ($this->tablasOperativas as $tabla) {
                $antes = DB::table($tabla)->count();
                DB::table($tabla)->delete();
                $despues = DB::table($tabla)->count();
                $resultados[$tabla] = ['antes' => $antes, 'despues' => $despues];
                $icono = $despues === 0 ? '[OK]' : '[FAIL]';
                $this->line("  {$icono}  [{$tabla}]  Eliminados: {$antes} -> Quedan: {$despues}");
            }

            $this->newLine();
            $this->info('Verificando medicamentos de prueba (PAR01 / Paracetamol 10 mg No aplica)...');

            $medEliminados = DB::table('medicamentos')
                ->where('codigo', 'PAR01')
                ->orWhere('descripcion', 'like', '%Paracetamol 10 mg No aplica%')
                ->get();

            if ($medEliminados->isEmpty()) {
                $this->line('  [INFO]  No se encontraron medicamentos de prueba PAR01.');
            } else {
                foreach ($medEliminados as $med) {
                    $this->warn("  [DEL]  Eliminando: [{$med->codigo}] {$med->descripcion}");
                }
                DB::table('medicamentos')
                    ->where('codigo', 'PAR01')
                    ->orWhere('descripcion', 'like', '%Paracetamol 10 mg No aplica%')
                    ->delete();
                $this->line("  [OK]  {$medEliminados->count()} medicamento(s) de prueba eliminado(s).");
            }

            DB::commit();

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $errorOcurrido = true;
            $this->newLine();
            $this->error('ERROR en la transaccion. ROLLBACK ejecutado.');
            $this->error('Detalle: ' . $e->getMessage());
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        if ($errorOcurrido) {
            return Command::FAILURE;
        }

        // Reinicio de AUTO_INCREMENT fuera de la transaccion (ALTER TABLE es DDL, no DML)
        $this->newLine();
        $this->info('Reiniciando AUTO_INCREMENT de tablas operativas...');
        foreach ($this->tablasOperativas as $tabla) {
            try {
                DB::statement("ALTER TABLE `{$tabla}` AUTO_INCREMENT = 1");
                $this->line("  [OK]  [{$tabla}] AUTO_INCREMENT -> 1");
            } catch (\Throwable $e) {
                $this->warn("  [WARN]  [{$tabla}]: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->line('--------------------------------------------------------------');
        $this->info('RESUMEN FINAL:');
        $this->newLine();

        $headers = ['Tabla', 'Antes', 'Despues', 'Estado'];
        $rows = [];
        $todosEnCero = true;

        foreach ($resultados as $tabla => $datos) {
            $estado = $datos['despues'] === 0 ? 'LIMPIO' : 'PENDIENTE';
            if ($datos['despues'] !== 0) $todosEnCero = false;
            $rows[] = [$tabla, $datos['antes'], $datos['despues'], $estado];
        }

        $this->table($headers, $rows);

        $totalMedicamentos = DB::table('medicamentos')->count();
        $this->newLine();
        $this->info("Total de medicamentos en catalogo: {$totalMedicamentos} registros.");
        $this->newLine();

        if ($todosEnCero) {
            $this->line('--- LIMPIEZA COMPLETADA EXITOSAMENTE ---');
            $this->line('--- El kardex esta listo para el inventario real al 01/10/2026 ---');
            return Command::SUCCESS;
        } else {
            $this->error('ATENCION: Algunas tablas NO quedaron en cero.');
            return Command::FAILURE;
        }
    }

    private function mostrarConteos(): void
    {
        $headers = ['Tabla', 'Registros actuales'];
        $rows = [];
        foreach ($this->tablasOperativas as $tabla) {
            try {
                $rows[] = [$tabla, DB::table($tabla)->count()];
            } catch (\Throwable $e) {
                $rows[] = [$tabla, 'Error: ' . $e->getMessage()];
            }
        }
        $this->table($headers, $rows);
    }
}