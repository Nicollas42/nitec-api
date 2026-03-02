<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Força o mapeamento do driver 'mysql'
        config(['tenancy.database.managers.mysql' => \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class]);
        // O TRUQUE: Se o driver vier vazio (''), força o MySQL também!
        config(['tenancy.database.managers.' => \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class]);
        
        // Garante que a conexão template seja 'tenant'
        config(['tenancy.database.template_tenant_connection' => 'template_tenant']);
    }
}
