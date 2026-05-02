<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Representa um arquivo PDF de cardápio (principal, happy hour, promocional, etc).
 * Um tenant pode ter vários PDFs ativos, exibidos como abas no cardápio público.
 */
class CardapioPdf extends Model
{
    protected $table = 'cardapio_pdfs';

    protected $fillable = [
        'nome_cardapio',
        'arquivo_path',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'ativo' => 'boolean',
    ];

    /**
     * URL pública (sem auth) para baixar/streamar o arquivo PDF.
     */
    public function getArquivoUrlAttribute(): ?string
    {
        if (!$this->arquivo_path) {
            return null;
        }

        return URL::to('/api/cardapio/pdfs/' . $this->id . '/arquivo');
    }

    /**
     * Tamanho do arquivo em bytes (útil para exibir no admin).
     */
    public function getArquivoTamanhoAttribute(): ?int
    {
        if (!$this->arquivo_path || !Storage::disk('public')->exists($this->arquivo_path)) {
            return null;
        }

        return Storage::disk('public')->size($this->arquivo_path);
    }
}
