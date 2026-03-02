<?php

namespace App\Models; // Esta linha faltava e é a causa do erro 500

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

/**
 * Representa um estabelecimento (inquilino) no sistema central Nitec.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Define os atributos que podem ser preenchidos via create/update.
     * @var array
     */
    protected $fillable = [
        'id',
        'data',
    ];

    /**
     * Define colunas customizadas se necessário.
     * @return array
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'data',
        ];
    }
}