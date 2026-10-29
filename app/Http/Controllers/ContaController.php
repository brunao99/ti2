<?php

namespace App\Http\Controllers;

use App\Models\AlertDispatch;
use App\Models\User;
use App\Support\FotoPerfil;
use App\Support\Telefone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Minha conta — os dados da pessoa e o que ela decide sobre a própria conta:
 * alertas por WhatsApp, tema do painel e sair. Abre ao clicar no nome dela,
 * na barra lateral (ou no avatar, no mobile).
 *
 * O consentimento dos alertas mora aqui, e não no cadastro do ADM, porque a
 * Meta exige opt-in de quem recebe: o registro tem de dizer que a pessoa
 * aceitou, não que alguém disse que ela aceitou.
 */
class ContaController extends Controller
{
    public function edit(Request $request): View
    {
        $eu = $request->user();

        return view('conta.edit', [
            'eu' => $eu,
            // Números de atividade só fazem sentido para quem publica e dispara.
            'atividade' => $eu->podeGerenciarPostagens() ? [
                'publicacoes' => $eu->postagens()->count(),
                'alertas'     => AlertDispatch::where('user_id', $eu->id)->count(),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $eu = $request->user();

        $request->merge(['telefone' => Telefone::normalizar($request->input('telefone'))]);

        $dados = $request->validate([
            'telefone' => [$request->boolean('autorizo') ? 'required' : 'nullable', 'regex:' . Telefone::FORMATO],
            'autorizo' => ['boolean'],
        ], [
            'telefone.required' => 'Para receber alertas, informe o número de WhatsApp.',
            'telefone.regex'    => 'Informe o WhatsApp com DDD, por exemplo (31) 99999-0001.',
        ]);

        $trocouTelefone = $dados['telefone'] !== $eu->telefone;
        $autorizo = $request->boolean('autorizo') && $dados['telefone'] !== null;

        // A data só muda quando a autorização começa ou passa a valer para
        // outro número; salvar de novo sem mudar nada mantém a data original.
        $consentimento = match (true) {
            ! $autorizo      => null,
            $trocouTelefone  => now(),
            default          => $eu->alerta_consentimento_em ?? now(),
        };

        $eu->forceFill([
            'telefone'                => $dados['telefone'],
            'alerta_consentimento_em' => $consentimento,
        ])->save();

        return redirect()->route('conta.edit')->with('ok', $autorizo
            ? 'Pronto. Você vai receber os alertas no WhatsApp ' . $eu->telefoneFormatado() . '.'
            : 'Pronto. Você não vai receber alertas por WhatsApp.');
    }

    // ------------------------------------------------------- foto de perfil

    public function salvarFoto(Request $request): RedirectResponse
    {
        $request->validate([
            'foto' => ['required', 'file', 'image', 'mimes:' . implode(',', FotoPerfil::TIPOS), 'max:' . FotoPerfil::MAX_KB],
        ], [
            'foto.required' => 'Escolha uma imagem para a foto de perfil.',
            'foto.uploaded' => 'Não foi possível enviar a imagem. Ela pode ser maior que o limite de 2 MB.',
            'foto.file'     => 'Não foi possível enviar a imagem. Tente de novo.',
            'foto.image'    => 'A foto precisa ser uma imagem JPG, PNG ou WebP.',
            'foto.mimes'    => 'A foto precisa ser uma imagem JPG, PNG ou WebP.',
            'foto.max'      => 'A foto pode ter no máximo 2 MB.',
        ]);

        try {
            FotoPerfil::salvar($request->user(), $request->file('foto'));
        } catch (RuntimeException) {
            return redirect()->route('conta.edit')
                ->withErrors(['foto' => 'Não foi possível ler a imagem. Tente outro arquivo JPG, PNG ou WebP.']);
        }

        return redirect()->route('conta.edit')->with('ok', 'Foto de perfil atualizada.');
    }

    public function removerFoto(Request $request): RedirectResponse
    {
        FotoPerfil::remover($request->user());

        return redirect()->route('conta.edit')->with('ok', 'Foto de perfil removida.');
    }

    /**
     * Entrega a foto. Cada um vê a própria; quem administra usuários vê a de
     * todos. O disco é privado, então a foto só sai por aqui, com login.
     */
    public function foto(Request $request, User $usuario): StreamedResponse
    {
        $eu = $request->user();
        abort_unless($eu->is($usuario) || $eu->podeGerenciarUsuarios(), 403);
        abort_unless($usuario->temFoto(), 404);

        return FotoPerfil::disco()->response($usuario->foto, null, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
