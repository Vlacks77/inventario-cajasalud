<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Actualiza las credenciales de prueba del sistema.
     */
    public function up(): void
    {
        $carmen = User::where('username', 'dra.almacen')->first();

        if ($carmen) {
            $carmen->update([
                'name' => 'Dra. Carmen Gutierrez- Encargada de Almacen',
                'username' => 'dra.carmen-almacen',
                'role' => 'almacen',
                'email' => 'dra.carmen-almacen@cajasalud.local',
                'password' => Hash::make('cscgutierrez'),
            ]);
        } else {
            User::updateOrCreate(
                ['username' => 'dra.carmen-almacen'],
                [
                    'name' => 'Dra. Carmen Gutierrez- Encargada de Almacen',
                    'role' => 'almacen',
                    'email' => 'dra.carmen-almacen@cajasalud.local',
                    'password' => Hash::make('cscgutierrez'),
                ]
            );
        }

        User::updateOrCreate(
            ['username' => 'csc'],
            [
                'name' => 'Kdu - Encargado de Sistemas',
                'role' => 'admin',
                'email' => 'csc@cajasalud.local',
                'password' => Hash::make('+kdu140876$'),
            ]
        );
    }

    /**
     * No revierte credenciales por seguridad; la migración se considera
     * un cambio de configuración de usuarios de prueba.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};
