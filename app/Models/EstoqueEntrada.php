<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstoqueEntrada extends Model
{
    protected $table = 'estoque_entradas';

    protected $fillable = [
        'produto_id', 'usuario_id', 'quantidade_adicionada', 'custo_unitario_compra', 'fornecedor'
    ];

    public function produto() { return $this->belongsTo(Produto::class); }
    public function usuario() { return $this->belongsTo(User::class); }
}