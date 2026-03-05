<?php
use Illuminate\Support\Facades\Route;

// Rota Curinga que redireciona qualquer tentativa de acesso para o Vue.js
// A REGRA MÁGICA: "(?!api)" significa "Capture tudo, EXCETO se começar com 'api'"
Route::get('/{any}', function () {
    return view('welcome'); // O seu ficheiro Blade principal
})->where('any', '^(?!api).*$');