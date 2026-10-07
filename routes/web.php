<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\PostagemController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\AlertaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas
|--------------------------------------------------------------------------
| Quem pode o quê:
|   Client  → só visualizar postagens publicadas
|   ADM     → tudo de postagens + insights + cadastrar usuários (menos Master)
|   Master  → tudo
*/

// A raiz leva cada um ao seu lugar. Um redirect fixo para /login criava um
// loop para quem já estava logado: /login devolvia a pessoa para a raiz.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'painel.inicio' : 'login'));

// ------------------------------------------------------------------- público
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'entrar'])->name('login.entrar');
});

Route::post('/sair', [AuthController::class, 'sair'])
    ->middleware('auth')
    ->name('logout');

// ------------------------------------------------------------- autenticado
Route::middleware(['auth', 'perfil'])->group(function () {

    Route::get('/painel', [PainelController::class, 'inicio'])->name('painel.inicio');

    // Tela "Mais" da barra inferior — existe só no mobile.
    Route::get('/mais', [PainelController::class, 'mais'])->name('painel.mais');

    // Minha conta — cada um decide se recebe alertas por WhatsApp.
    Route::get('/minha-conta', [ContaController::class, 'edit'])->name('conta.edit');
    Route::put('/minha-conta', [ContaController::class, 'update'])->name('conta.update');

    // Leitura — os três perfis
    Route::get('/postagens', [PostagemController::class, 'index'])->name('postagens.index');
    Route::get('/postagens/{postagem}', [PostagemController::class, 'show'])
        ->whereNumber('postagem')
        ->name('postagens.show');
});

// -------------------------------------------- escrita: só Master e ADM
Route::middleware(['auth', 'perfil:master,adm'])->group(function () {

    Route::get('/postagens/nova', [PostagemController::class, 'create'])->name('postagens.create');
    Route::post('/postagens', [PostagemController::class, 'store'])->name('postagens.store');
    Route::get('/postagens/{postagem}/editar', [PostagemController::class, 'edit'])->name('postagens.edit');
    Route::put('/postagens/{postagem}', [PostagemController::class, 'update'])->name('postagens.update');
    Route::delete('/postagens/{postagem}', [PostagemController::class, 'destroy'])->name('postagens.destroy');

    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/novo', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

    // Gestão de alertas: apenas Master e ADM.
    Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::get('/alertas/publicacoes/{postagem}/enviar', [AlertaController::class, 'enviar'])
        ->name('alertas.enviar');
    Route::post('/alertas/publicacoes/{postagem}/preview', [AlertaController::class, 'preview'])
        ->name('alertas.preview');
    Route::post('/alertas/publicacoes/{postagem}/disparar', [AlertaController::class, 'disparar'])
        ->name('alertas.disparar');
    Route::get('/alertas/disparos/{disparo}', [AlertaController::class, 'show'])
        ->name('alertas.show');
    Route::post('/alertas/disparos/{disparo}/reenviar', [AlertaController::class, 'reenviar'])
        ->name('alertas.reenviar');

    Route::get('/alertas/modelos', [AlertaController::class, 'modelos'])->name('alertas.modelos');
    Route::get('/alertas/modelos/novo', [AlertaController::class, 'criarModelo'])->name('alertas.modelos.novo');
    Route::post('/alertas/modelos', [AlertaController::class, 'salvarModelo'])->name('alertas.modelos.salvar');
    Route::get('/alertas/modelos/{modelo}/editar', fn (\App\Models\AlertTemplate $modelo) => view('alertas.modelos.form', compact('modelo')))
        ->name('alertas.modelos.editar');
    Route::put('/alertas/modelos/{modelo}', [AlertaController::class, 'salvarModelo'])->name('alertas.modelos.atualizar');
});
