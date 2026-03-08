<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->string('motivo_cancelamento')->nullable()->after('status_comanda');
        });
    }
    public function down(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn('motivo_cancelamento');
        });
    }
};
