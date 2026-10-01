<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cadastro de usuários — ainda sem wireframe, então a tela é simples
 * e segue a mesma linguagem visual do resto do painel.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $usuarios = User::query()
            // O ADM não enxerga contas Master.
            ->when($request->user()->ehAdm(), fn ($q) => $q->where('perfil', '!=', User::MASTER))
            ->orderBy('nome')
            ->get();

        return view('usuarios.index', ['usuarios' => $usuarios]);
    }

    public function create(Request $request): View
    {
        return view('usuarios.form', [
            'usuario' => new User(),
            'perfis'  => $request->user()->perfisQuePodeAtribuir(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validado($request, null);
        $dados['ativo'] = $request->boolean('ativo');

        User::create($dados);

        return redirect()->route('usuarios.index')->with('ok', 'Usuário cadastrado.');
    }

    public function edit(Request $request, User $usuario): View
    {
        abort_unless($request->user()->podeEditarUsuario($usuario), 403);

        return view('usuarios.form', [
            'usuario' => $usuario,
            'perfis'  => $request->user()->perfisQuePodeAtribuir(),
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($request->user()->podeEditarUsuario($usuario), 403);

        $dados = $this->validado($request, $usuario);
        $dados['ativo'] = $request->boolean('ativo');

        // Senha em branco no formulário significa "manter a atual".
        if (blank($dados['password'] ?? null)) {
            unset($dados['password']);
        }

        $usuario->update($dados);

        return redirect()->route('usuarios.index')->with('ok', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($request->user()->podeEditarUsuario($usuario), 403);

        if ($usuario->is($request->user())) {
            return back()->withErrors(['usuario' => 'Você não pode excluir a própria conta.']);
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('ok', 'Usuário removido.');
    }

    // ------------------------------------------------------------- validação

    private function validado(Request $request, ?User $usuario): array
    {
        $id = $usuario?->id;

        return $request->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'usuario'  => ['required', 'string', 'max:60', Rule::unique('users', 'usuario')->ignore($id)],
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8'],
            // Impede o ADM de criar um Master mexendo no HTML do formulário.
            'perfil'   => ['required', Rule::in($request->user()->perfisQuePodeAtribuir())],
        ], [
            'usuario.unique'   => 'Já existe alguém com esse nome de usuário.',
            'email.unique'     => 'Esse e-mail já está cadastrado.',
            'password.min'     => 'A senha precisa de pelo menos 8 caracteres.',
            'perfil.in'        => 'Seu perfil não pode atribuir esse nível de acesso.',
        ]);
    }
}
