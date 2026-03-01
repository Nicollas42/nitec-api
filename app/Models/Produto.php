<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    /**
     * Campos permitidos para gravação em massa.
     * @var array
     */
    protected $fillable = [
        'nome_produto',
        'codigo_barras',
        'preco_venda',
        'estoque_atual'
    ];
}