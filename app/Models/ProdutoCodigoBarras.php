<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoCodigoBarras extends Model
{
    /**
     * Indica se a tabela utiliza timestamps automaticos.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Nome explicito da tabela para evitar singularizacao incorreta.
     *
     * @var string
     */
    protected $table = 'produto_codigos_barras';

    /**
     * Atributos liberados para atribuicao em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'produto_id',
        'codigo_barras',
        'descricao_variacao',
    ];

    /**
     * Retorna o produto proprietario do alias de codigo de barras.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
