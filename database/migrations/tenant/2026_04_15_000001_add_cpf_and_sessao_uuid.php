<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->after('telefone');
            $table->unique('cpf');
        });

        Schema::table('mesas', function (Blueprint $table) {
            $table->string('sessao_uuid', 36)->nullable()->after('status_mesa');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn('cpf');
        });

        Schema::table('mesas', function (Blueprint $table) {
            $table->dropColumn('sessao_uuid');
        });
    }
};
