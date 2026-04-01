<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->boolean('solicitando_atendimento')->default(false)->after('capacidade_pessoas');
            $table->json('solicitacao_detalhes')->nullable()->after('solicitando_atendimento');
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->dropColumn(['solicitando_atendimento', 'solicitacao_detalhes']);
        });
    }
};
