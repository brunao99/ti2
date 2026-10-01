<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function mostrarLogin(): View
    {
        return view('auth.login');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'usuario' => ['required', 'string'],
            'senha'   => ['required', 'string'],
        ], [
            'usuario.required' => 'Informe o nome de usuário.',
            'senha.required'   => 'Informe a senha.',
        ]);

        $credenciais = [
            'usuario'  => $dados['usuario'],
            'password' => $dados['senha'],
        ];

        if (! Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            // Mensagem genérica de propósito: não revela se o usuário existe.
            throw ValidationException::withMessages([
                'usuario' => 'Usuário ou senha inválidos.',
            ]);
        }

        // Tela 03 do wireframe: conta existe, mas está desativada.
        if (! $request->user()->ativo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'usuario' => 'Esta conta está inativa. Procure o usuário master da prefeitura.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('painel.inicio'));
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
