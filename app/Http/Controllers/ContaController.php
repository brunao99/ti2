<?php

namespace App\Http\Controllers;

use App\Support\Telefone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Minha conta — o que a própria pessoa decide sobre a conta dela.
 *
 * Por ora, só os alertas por WhatsApp. O consentimento mora aqui, e não no
 * cadastro do ADM, porque a Meta exige opt-in de quem recebe: o registro
 * tem de dizer que a pessoa aceitou, não que alguém disse que ela aceitou.
 */
class ContaController extends Controller
{
    public function edit(Request $request): View
    {
        return view('conta.edit', ['eu' => $request->user()]);
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
