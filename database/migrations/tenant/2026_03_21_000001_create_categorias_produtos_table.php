<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->timestamps();
        });

        // Popula com categorias padrão
        $categorias_padrao = [
            'Geral', 'Cervejas', 'Drinks e Coquetéis', 'Destilados', 'Vinhos e Espumantes',
            'Bebidas Não Alcoólicas', 'Pratos Principais', 'Entradas e Petiscos',
            'Sobremesas', 'Lanches', 'Insumos de Bar', 'Descartáveis',
        ];

        foreach ($categorias_padrao as $nome) {
            DB::table('categorias_produtos')->insert(['nome' => $nome, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_produtos');
    }
};
