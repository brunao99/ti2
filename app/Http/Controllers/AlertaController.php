<?php

namespace App\Http\Controllers;

use App\Models\AlertContact;
use App\Models\AlertDispatch;
use App\Models\AlertDispatchRecipient;
use App\Models\AlertList;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Services\Alertas\AlertSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertaController extends Controller
{
    public function index(): View
    {
        $disparos = AlertDispatch::with(['postagem', 'modelo', 'lista', 'administrador'])
            ->latest()
            ->get();

        return view('alertas.index', compact('disparos'));
    }

    public function enviar(Postagem $postagem): View
    {
        abort_unless($postagem->situacao === 'publicado', 404);
        abort_if($postagem->alerta_enviado, 409, 'Esta publicação já possui um alerta enviado.');

        return view('alertas.enviar', [
            'postagem' => $postagem,
            'modelos' => AlertTemplate::orderBy('nome')->get(),
            'listas' => AlertList::withCount(['contatos', 'contatos as consentidos_count' => fn ($q) => $q->where('consentimento', true)])->orderBy('nome')->get(),
        ]);
    }

    public function preview(Request $request, Postagem $postagem): RedirectResponse
    {
        abort_unless($postagem->situacao === 'publicado', 404);
        abort_if($postagem->alerta_enviado, 409, 'Esta publicação já possui um alerta enviado.');

        $dados = $request->validate([
            'alert_template_id' => ['required', 'exists:alert_templates,id'],
            'alert_list_id' => ['required', 'exists:alert_lists,id'],
        ]);

        return redirect()
            ->route('alertas.enviar', $postagem)
            ->withInput($dados);
    }

    public function disparar(Request $request, Postagem $postagem, AlertSender $sender): RedirectResponse
    {
        abort_unless($postagem->situacao === 'publicado', 404);
        abort_if($postagem->alerta_enviado, 409, 'Esta publicação já possui um alerta enviado.');

        $dados = $request->validate([
            'alert_template_id' => ['required', 'exists:alert_templates,id'],
            'alert_list_id' => ['required', 'exists:alert_lists,id'],
        ]);

        $modelo = AlertTemplate::findOrFail($dados['alert_template_id']);
        $lista = AlertList::with('contatos')->findOrFail($dados['alert_list_id']);

        $contatos = $lista->contatos;
        $aptos = $contatos->where('consentimento', true)->values();

        if ($aptos->isEmpty()) {
            return back()->withErrors([
                'alerta' => 'A lista não possui contatos com consentimento de recebimento.',
            ])->withInput();
        }

        $mensagem = $this->montarMensagem($modelo->mensagem, $postagem);

        $disparo = DB::transaction(function () use (
            $postagem, $modelo, $lista, $aptos, $contatos, $mensagem, $request, $sender
        ) {
            $disparo = AlertDispatch::create([
                'postagem_id' => $postagem->id,
                'alert_template_id' => $modelo->id,
                'alert_list_id' => $lista->id,
                'user_id' => $request->user()->id,
                'mensagem' => $mensagem,
                'status' => 'sucesso',
                'total_destinatarios' => $aptos->count(),
                'total_enviados' => 0,
                'total_falhas' => 0,
            ]);

            $enviados = 0;
            $falhas = 0;

            foreach ($contatos as $contato) {
                if (! $contato->consentimento) {
                    AlertDispatchRecipient::create([
                        'alert_dispatch_id' => $disparo->id,
                        'alert_contact_id' => $contato->id,
                        'nome' => $contato->nome,
                        'telefone' => $contato->telefone,
                        'status' => 'ignorado',
                        'resultado' => 'Sem consentimento de recebimento.',
                    ]);
                    continue;
                }

                $resultado = $sender->send($contato->telefone, $mensagem);

                if ($resultado['status'] === 'enviado') {
                    $enviados++;
                } else {
                    $falhas++;
                }

                AlertDispatchRecipient::create([
                    'alert_dispatch_id' => $disparo->id,
                    'alert_contact_id' => $contato->id,
                    'nome' => $contato->nome,
                    'telefone' => $contato->telefone,
                    'status' => $resultado['status'],
                    'resultado' => $resultado['resultado'],
                ]);
            }

            $status = $falhas === 0 ? 'sucesso' : ($enviados > 0 ? 'parcial' : 'falha');

            $disparo->update([
                'status' => $status,
                'total_enviados' => $enviados,
                'total_falhas' => $falhas,
            ]);

            $postagem->update(['alerta_enviado' => true]);

            return $disparo;
        });

        return redirect()
            ->route('alertas.index')
            ->with('ok', "Alerta enviado e registrado. {$disparo->total_enviados} destinatário(s) processado(s).");
    }

    public function modelos(): View
    {
        return view('alertas.modelos.index', [
            'modelos' => AlertTemplate::orderBy('nome')->get(),
        ]);
    }

    public function criarModelo(): View
    {
        return view('alertas.modelos.form', ['modelo' => new AlertTemplate()]);
    }

    public function salvarModelo(Request $request, ?AlertTemplate $modelo = null): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'mensagem' => ['required', 'string', 'max:4000'],
        ]);

        $modelo ? $modelo->update($dados) : AlertTemplate::create($dados);

        return redirect()->route('alertas.modelos')->with('ok', 'Modelo de mensagem salvo.');
    }

    public function listas(): View
    {
        return view('alertas.listas.index', [
            'listas' => AlertList::withCount(['contatos', 'contatos as consentidos_count' => fn ($q) => $q->where('consentimento', true)])->orderBy('nome')->get(),
        ]);
    }

    public function criarLista(): View
    {
        return view('alertas.listas.form', [
            'lista' => new AlertList(),
            'contatos' => AlertContact::orderBy('nome')->get(),
        ]);
    }

    public function salvarLista(Request $request, ?AlertList $lista = null): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'contatos' => ['nullable', 'array'],
            'contatos.*' => ['integer', 'exists:alert_contacts,id'],
        ]);

        $contatos = $dados['contatos'] ?? [];
        unset($dados['contatos']);

        if ($lista) {
            $lista->update($dados);
        } else {
            $lista = AlertList::create($dados);
        }

        $lista->contatos()->sync($contatos);

        return redirect()->route('alertas.listas')->with('ok', 'Lista de destinatários salva.');
    }

    public function contatos(): View
    {
        return view('alertas.contatos.index', [
            'contatos' => AlertContact::withCount('listas')->orderBy('nome')->get(),
        ]);
    }

    public function criarContato(): View
    {
        return view('alertas.contatos.form', ['contato' => new AlertContact()]);
    }

    public function salvarContato(Request $request, ?AlertContact $contato = null): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['required', 'string', 'max:30'],
            'consentimento' => ['boolean'],
        ]);

        $dados['consentimento'] = $request->boolean('consentimento');

        $contato ? $contato->update($dados) : AlertContact::create($dados);

        return redirect()->route('alertas.contatos')->with('ok', 'Contato salvo.');
    }

    public function removerContato(AlertContact $contato): RedirectResponse
    {
        $contato->delete();

        return redirect()->route('alertas.contatos')->with('ok', 'Contato removido.');
    }

    public function show(AlertDispatch $disparo): View
    {
        $disparo->load(['postagem', 'modelo', 'lista', 'administrador', 'destinatarios']);

        return view('alertas.show', compact('disparo'));
    }

    private function montarMensagem(string $modelo, Postagem $postagem): string
    {
        return strtr($modelo, [
            '{{titulo}}' => $postagem->titulo,
            '{{tipo}}' => $postagem->rotuloTipo(),
            '{{data_publicacao}}' => $postagem->data_publicacao->format('d/m/Y'),
        ]);
    }
}
