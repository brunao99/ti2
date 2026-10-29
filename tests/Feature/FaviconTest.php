<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    private function assertFavicon(string $html): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png'] as $arquivo) {
            $this->assertSame(1, substr_count($html, asset($arquivo) . '?v='), "{$arquivo} deve aparecer uma vez no <head>");
        }
        $this->assertStringContainsString('<link rel="icon" href="' . asset('favicon.svg') . '?v=', $html);
        $this->assertStringContainsString('type="image/svg+xml"', $html);
        $this->assertStringContainsString('<link rel="apple-touch-icon"', $html);
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'favicon.svg'));
    }

    public function test_primeira_tela_e_login_tem_favicon(): void
    {
        // A raiz leva ao login: é a primeira tela de quem acessa o site.
        $this->get('/')->assertRedirect(route('login'));
        $this->assertFavicon($this->get(route('login'))->assertOk()->getContent());
    }

    public function test_paginas_internas_tem_favicon(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        foreach (['painel.inicio', 'postagens.index', 'conta.edit', 'usuarios.index', 'alertas.index', 'relatorios.index', 'painel.mais'] as $rota) {
            $this->assertFavicon($this->actingAs($adm)->get(route($rota))->assertOk()->getContent());
        }
    }

    public function test_arquivos_do_favicon_sao_validos(): void
    {
        $svg = file_get_contents(public_path('favicon.svg'));
        $this->assertStringContainsString('viewBox="0 0 64 64"', $svg);
        $this->assertStringContainsString('#133049', $svg);
        $this->assertStringContainsString('#6fa8e0', $svg);

        // ICO com 3 tamanhos: cabeçalho reservado 0, tipo 1 (ícone), 3 imagens.
        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertSame([0, 1, 3], array_values(unpack('vreservado/vtipo/vqtd', substr($ico, 0, 6))));

        [$largura, $altura, $tipo] = getimagesize(public_path('apple-touch-icon.png'));
        $this->assertSame([180, 180, IMAGETYPE_PNG], [$largura, $altura, $tipo]);
    }
}
