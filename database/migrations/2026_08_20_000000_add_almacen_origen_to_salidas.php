<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salidas', function (Blueprint $table) {
            $table->string('almacen_origen', 150)->nullable()->after('fecha_salida');
        });

        DB::table('salidas')->whereNull('almacen_origen')->update([
            'almacen_origen' => 'REGIONAL LA PAZ',
        ]);
    }

    public function down(): void
    {
        Schema::table('salidas', function (Blueprint $table) {
            $table->dropColumn('almacen_origen');
        });
    }
};
