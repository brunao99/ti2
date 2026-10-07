<?php

namespace App\Http\Controllers;

use App\Models\AlertDispatch;
use App\Support\Telefone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
