<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nome_produto', 
        'codigo_barras', 
        'preco_venda', 
        'preco_custo',
        'categoria',
        'estoque_atual',
        'data_validade' // 🟢 NOVO CAMPO
    ];
}