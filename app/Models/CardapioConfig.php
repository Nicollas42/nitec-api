<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardapioConfig extends Model
{
    protected $table = 'cardapio_config';

    protected $fillable = [
        'nome_exibicao',
        'subtitulo',
        'mensagem_boas_vindas',
        'cor_primaria',
        'cor_destaque',
        'cor_fundo',
        'logo_url',
    ];

    public static function obter(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'nome_exibicao'        => 'Nosso Cardapio',
                'subtitulo'            => null,
                'mensagem_boas_vindas' => null,
                'cor_primaria'         => '#3B82F6',
                'cor_destaque'         => '#10B981',
                'cor_fundo'            => '#FFF7ED',
                'logo_url'             => null,
            ]
        );
    }
}
