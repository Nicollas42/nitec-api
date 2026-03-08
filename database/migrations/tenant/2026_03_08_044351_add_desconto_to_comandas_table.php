<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->decimal('desconto', 10, 2)->default(0)->after('valor_total');
        });
    }
    public function down(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn('desconto');
        });
    }
};
