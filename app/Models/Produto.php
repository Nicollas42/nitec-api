<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // 🟢 Importar

class Produto extends Model
{
    use SoftDeletes; // 🟢 Ativar

    protected $fillable = [
        'nome_produto', 
        'codigo_barras', 
        'preco_venda', 
        'preco_custo', // 🟢 Novo
        'categoria',   // 🟢 Novo
        'estoque_atual'
    ];
}