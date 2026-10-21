<?php

namespace App\Http\Controllers;

use App\Models\Postagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PainelController extends Controller
{
    /** Tela 04 — Início do painel, com os indicadores e as últimas publicações. */
    public function inicio(Request $request): View
    {
        $usuario = $request->user();
        $mesAtual = now()->format('Y-m');

        $ultimas = Postagem::query()
            ->with('autor')
            ->when($usuario->ehClient(), fn ($q) => $q->publicadas())
            ->orderByDesc('data_publicacao')
            ->limit(5)
            ->get();

        return view('painel.inicio', [
            'saudacao'   => $this->saudacao(),
            'ultimas'    => $ultimas,
            'indicadores' => $usuario->ehClient() ? null : $this->indicadores($mesAtual),
        ]);
    }

    /**
     * Tela "Mais" — só existe no mobile.
     *
     * A barra inferior comporta três itens; o resto dos módulos vive aqui,
     * agrupado. No desktop a barra lateral já mostra tudo, então esta tela
     * não é usada lá.
     *
     * Itens sem rota são módulos ainda não construídos: aparecem apagados e
     * marcados "em breve", em vez de sumir. Assim o grupo enxerga o que falta
     * e ninguém procura uma tela que não existe.
     */
    public function mais(Request $request): View
    {
        $usuario = $request->user();

        $grupos = [];

        // Quem dispara alerta é o painel, não a prefeitura que o recebe —
        // por isso o grupo de módulos só aparece para Master e ADM.
        if ($usuario->podeGerenciarPostagens()) {
            $grupos[] = [
                'titulo' => 'Módulos',
                'itens'  => [
                    ['rotulo' => 'Central de alertas', 'rota' => 'alertas.index',   'desc' => 'Disparos e histórico'],
                    ['rotulo' => 'Relatórios',         'rota' => 'relatorios.index', 'desc' => 'Publicações e alertas por período'],
                    ['rotulo' => 'Modelos de mensagem', 'rota' => 'alertas.modelos', 'desc' => 'Textos padrão dos alertas'],
                ],
            ];
        }

        // Client não administra nada: o grupo inteiro some, em vez de
        // aparecer desabilitado — mostrar o que a pessoa nunca poderá usar
        // só gera dúvida.
        if ($usuario->podeGerenciarUsuarios()) {
            $grupos[] = [
                'titulo' => 'Administração',
                'itens'  => [
                    ['rotulo' => 'Usuários', 'rota' => 'usuarios.index', 'desc' => 'Cadastro e permissões'],
                    ['rotulo' => 'Contas',   'rota' => null,             'desc' => 'Prefeituras atendidas'],
                ],
            ];
        }

        $grupos[] = [
            'titulo' => 'Minha conta',
            'itens'  => [
                ['rotulo' => 'Minha conta', 'rota' => 'conta.edit', 'desc' => 'Dados, alertas no WhatsApp, tema e sair'],
            ],
        ];

        return view('painel.mais', ['grupos' => $grupos]);
    }

    /**
     * Os "insights" do ADM. Client não vê nada disso.
     */
    private function indicadores(string $mesAtual): array
    {
        return [
            'publicacoes_mes'   => Postagem::where('mes_referencia', $mesAtual)->count(),
            'alertas_enviados'  => Postagem::where('mes_referencia', $mesAtual)
                                        ->where('alerta_enviado', true)->count(),
            'rascunhos'         => Postagem::where('situacao', 'rascunho')->count(),
            'usuarios_ativos'   => User::where('ativo', true)->count(),
        ];
    }

    private function saudacao(): string
    {
        $hora = now()->hour;

        return match (true) {
            $hora < 12 => 'Bom dia',
            $hora < 18 => 'Boa tarde',
            default    => 'Boa noite',
        };
    }
}
