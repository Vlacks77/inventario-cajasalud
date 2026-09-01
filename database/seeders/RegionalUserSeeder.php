<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RegionalUserSeeder extends Seeder
{
    /**
     * Crea las nueve cuentas regionales sin modificar las cuentas existentes.
     */
    public function run(): void
    {
        $cuentas = [
            ['username' => 'ch', 'name' => 'Gestor de Almacén - Chuquisaca', 'regional' => 'Chuquisaca', 'email' => 'ch@cajasalud.local', 'password' => 'ch'],
            ['username' => 'lp', 'name' => 'Gestor de Almacén - La Paz', 'regional' => 'La Paz', 'email' => 'lp@cajasalud.local', 'password' => 'lp'],
            ['username' => 'cbba', 'name' => 'Gestor de Almacén - Cochabamba', 'regional' => 'Cochabamba', 'email' => 'cbba@cajasalud.local', 'password' => 'cbba'],
            ['username' => 'or', 'name' => 'Gestor de Almacén - Oruro', 'regional' => 'Oruro', 'email' => 'or@cajasalud.local', 'password' => 'or'],
            ['username' => 'pt', 'name' => 'Gestor de Almacén - Potosí', 'regional' => 'Potosí', 'email' => 'pt@cajasalud.local', 'password' => 'pt'],
            ['username' => 'tj', 'name' => 'Gestor de Almacén - Tarija', 'regional' => 'Tarija', 'email' => 'tj@cajasalud.local', 'password' => 'tj'],
            ['username' => 'sc', 'name' => 'Gestor de Almacén - Santa Cruz', 'regional' => 'Santa Cruz', 'email' => 'sc@cajasalud.local', 'password' => 'sc'],
            ['username' => 'bn', 'name' => 'Gestor de Almacén - Beni', 'regional' => 'Beni', 'email' => 'bn@cajasalud.local', 'password' => 'bn'],
            ['username' => 'pa', 'name' => 'Gestor de Almacén - Pando', 'regional' => 'Pando', 'email' => 'pa@cajasalud.local', 'password' => 'pa'],
        ];

        foreach ($cuentas as $cuenta) {
            User::updateOrCreate(
                ['username' => $cuenta['username']],
                [
                    'name' => $cuenta['name'],
                    'role' => 'almacen',
                    'regional' => $cuenta['regional'],
                    'email' => $cuenta['email'],
                    'password' => Hash::make($cuenta['password']),
                ]
            );
        }
    }
}
