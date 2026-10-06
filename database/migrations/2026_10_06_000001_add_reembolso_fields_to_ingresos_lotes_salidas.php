<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: soporte para Reembolsos Mensuales de Medicamentos.
 *
 * Se añaden mínimamente los campos necesarios para que el flujo
 * Ingreso-REEMBOLSO → Egreso-EGRESO_REEMBOLSO quede trazable sin
 * alterar el stock físico de los lotes regulares de almacén.
 *
 * Cambios:
 *  ingresos   → tipo_ingreso acepta 'reembolso' (solo enum en validación).
 *  lotes      → es_reembolso BOOLEAN DEFAULT FALSE.
 *               Identifica lotes virtuales que no deben dispensarse
 *               desde farmacia porque serán consumidos automáticamente
 *               por la nota de egreso asociada.
 *  salidas    → tipo_salida VARCHAR(50) DEFAULT 'normal'.
 *               movimiento_origen_id FK nullable → ingresos.
 *               Permite ligar cada EGRESO_REEMBOLSO con su nota de ingreso.
 *  detalle_salidas → precio_unitario_reembolso DECIMAL nullable.
 *               Almacena el Costo Promedio Ponderado calculado en la
 *               consolidación, sin depender del precio del lote origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── lotes ────────────────────────────────────────────────────────────
        Schema::table('lotes', function (Blueprint $table) {
            // Flag que marca este lote como "virtual de reembolso".
            // Los lotes es_reembolso = true deben quedar en cantidad_actual = 0
            // una vez que la nota de egreso los consuma; no se dispensan desde
            // farmacia durante ese intervalo.
            $table->boolean('es_reembolso')
                ->default(false)
                ->after('importe_total')
                ->comment('TRUE = lote virtual creado por un ingreso de reembolso; no disponible para dispensación regular.');
        });

        // ── salidas ──────────────────────────────────────────────────────────
        Schema::table('salidas', function (Blueprint $table) {
            // Distingue salidas normales de egresos de reembolso.
            // Valores esperados: 'normal' | 'EGRESO_REEMBOLSO'
            $table->string('tipo_salida', 50)
                ->default('normal')
                ->after('estado')
                ->comment("'normal' para salidas regulares; 'EGRESO_REEMBOLSO' para el egreso consolidado que cierra un ingreso de reembolso.");

            // FK trazable: relaciona el egreso de reembolso con el ingreso que lo originó.
            $table->unsignedBigInteger('movimiento_origen_id')
                ->nullable()
                ->after('tipo_salida')
                ->comment('ID del ingreso (tabla ingresos) que originó este egreso de reembolso. NULL para salidas normales.');

            $table->foreign('movimiento_origen_id')
                ->references('id')
                ->on('ingresos')
                ->nullOnDelete();
        });

        // ── detalle_salidas ──────────────────────────────────────────────────
        Schema::table('detalle_salidas', function (Blueprint $table) {
            // El precio_unitario del lote es el precio individual de cada factura.
            // Para el egreso consolidado guardamos el CPP calculado aquí,
            // evitando así cualquier ambigüedad en la valoración del Kardex.
            $table->decimal('precio_unitario_reembolso', 14, 6)
                ->nullable()
                ->after('cantidad')
                ->comment('Costo Promedio Ponderado calculado al consolidar el egreso de reembolso. NULL en detalle_salidas regulares.');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_salidas', function (Blueprint $table) {
            $table->dropColumn('precio_unitario_reembolso');
        });

        Schema::table('salidas', function (Blueprint $table) {
            $table->dropForeign(['movimiento_origen_id']);
            $table->dropColumn(['tipo_salida', 'movimiento_origen_id']);
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('es_reembolso');
        });
    }
};
