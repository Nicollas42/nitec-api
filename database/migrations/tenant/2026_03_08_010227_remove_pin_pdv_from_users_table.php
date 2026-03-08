<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'pin_pdv')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pin_pdv');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_pdv')->nullable();
        });
    }
};
