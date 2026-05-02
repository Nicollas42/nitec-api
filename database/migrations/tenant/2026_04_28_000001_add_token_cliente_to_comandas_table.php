<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona token_cliente às comandas para suportar URLs de sessão individuais.
 *
 * Cada comanda digital (criada via QR code) recebe um UUID único que serve
 * como token de acesso pessoal do cliente. A URL da sessão fica:
 *   #/cardapio/mesa/{id}/s/{token_cliente}
 *
 * Isso permite que múltiplos clientes na mesma mesa tenham URLs distintas,
 * e que o cliente recupere sua sessão sem redigitar o CPF ao acessar o link salvo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->string('token_cliente', 36)->nullable()->unique()->after('cliente_id')
                  ->comment('UUID de sessão individual do cliente — usado na URL pessoal do cardápio.');
        });
    }

    public function down(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn('token_cliente');
        });
    }
};
