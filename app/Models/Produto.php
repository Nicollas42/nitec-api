<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome_produto',
        'codigo_barras', // NOVO CAMPO ADICIONADO
        'preco_venda',
        'estoque_atual'
    ];
}