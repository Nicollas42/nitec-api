<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstoquePerda extends Model
{
    protected $table = 'estoque_perdas';

    protected $fillable = [
        'produto_id',
        'usuario_id',
        'quantidade',
        'motivo',
        'custo_total_perda'
    ];

    public function produto() {
        return $this->belongsTo(Produto::class);
    }
    
    public function usuario() {
        return $this->belongsTo(User::class);
    }
}