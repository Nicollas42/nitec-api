<?php

namespace App\Models;

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
     * Define as colunas físicas na tabela 'tenants'.
     * @return array
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'ativo',
        ];
    }
    
    // O pacote stancl/tenancy já faz todo o resto automaticamente!
}