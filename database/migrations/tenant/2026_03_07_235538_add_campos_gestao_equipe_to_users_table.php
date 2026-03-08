<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_conta')->default('ativo')->after('tipo_usuario'); // ativo | inativo
            $table->string('tipo_contrato')->default('fixo')->after('status_conta'); // fixo | temporario
            $table->dateTime('expiracao_acesso')->nullable()->after('tipo_contrato'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status_conta',
                'tipo_contrato',
                'expiracao_acesso',
            ]);
        });
    }
};