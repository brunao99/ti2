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
