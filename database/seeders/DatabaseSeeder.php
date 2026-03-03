<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Semeia a base de dados central com os dados essenciais de inicialização.
     * * @return void
     */
    public function run(): void
    {
        // Verifica se o admin master já existe para não duplicar em futuros deploys
        $admin_existe = User::where('email', 'admin.master@nitec.dev.br')->first();

        if (!$admin_existe) {
            User::create([
                'name' => 'Administrador Central Nitec',
                'email' => 'admin.master@nitec.dev.br',
                'password' => Hash::make('Nitec@Master2026'), // Senha padrão de produção
                'tipo_usuario' => 'admin_master',
            ]);
            
            $this->command->info('Usuário Admin Master gerado com sucesso.');
        } else {
            $this->command->info('O usuário Admin Master já existe na base de dados central.');
        }
    }
}