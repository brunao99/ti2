<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota a determinados perfis.
 *
 * Uso na rota:  ->middleware('perfil:master,adm')
 */
class VerificaPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        // Conta desativada não navega, mesmo com sessão válida.
        if (! $usuario->ativo) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->withErrors(['usuario' => 'Esta conta está inativa. Procure o usuário master.']);
        }

        if ($perfis && ! in_array($usuario->perfil, $perfis, true)) {
            abort(403, 'Seu perfil não tem acesso a esta área.');
        }

        return $next($request);
    }
}
