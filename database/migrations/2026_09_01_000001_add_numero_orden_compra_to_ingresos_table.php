<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->string('numero_orden_compra', 100)->nullable()->after('numero_factura');
        });
    }

    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropColumn('numero_orden_compra');
        });
    }
};
