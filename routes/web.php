<?php
use Illuminate\Support\Facades\Route;

// Rota que redireciona qualquer tentativa de acesso para o Vue.js
Route::get('/{any}', function () {
    return view('welcome'); // O nome do seu ficheiro Blade principal (ex: welcome.blade.php)
})->where('any', '.*');