<?php

namespace Tests\Feature;

use App\Models\Postagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Campos que alimentam o relatório da prefeitura: contrato e link da fonte. */
class CamposRelatorioTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- contrato

    public function test_cliente_e_cadastrado_com_contrato(): void
    {
        $this->actingAs($this->adm())->post(route('usuarios.store'), $this->usuario([
            'perfil' => User::CLIENT,
            'contrato' => ' 225/2025 ',
        ]))->assertRedirect(route('usuarios.index'));

        $this->assertSame('225/2025', User::where('usuario', 'itauna.mg')->value('contrato'));
    }

    public function test_cliente_sem_contrato_e_recusado(): void
    {
        $this->actingAs($this->adm())->post(route('usuarios.store'), $this->usuario([
            'perfil' => User::CLIENT,
            'contrato' => '',
        ]))->assertSessionHasErrors('contrato');

        $this->assertDatabaseMissing('users', ['usuario' => 'itauna.mg']);
    }

    public function test_outros_perfis_nao_guardam_contrato(): void
    {
        $this->actingAs($this->adm())->post(route('usuarios.store'), $this->usuario([
            'perfil' => User::ADM,
            'contrato' => '999/2025',
        ]))->assertRedirect(route('usuarios.index'));

        $this->assertNull(User::where('usuario', 'itauna.mg')->value('contrato'));
    }

    public function test_formulario_de_usuario_tem_o_campo_contrato(): void
    {
        $this->actingAs($this->adm())
            ->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee('name="contrato"', false);
    }

    // ------------------------------------------------------------ link da fonte

    public function test_publicacao_guarda_o_link_da_fonte(): void
    {
        $this->actingAs($this->adm())
            ->post(route('postagens.store'), $this->publicacao(['link' => 'https://www.gov.br/mec/noticia']))
            ->assertRedirect();

        $postagem = Postagem::latest('id')->firstOrFail();
        $this->assertSame('https://www.gov.br/mec/noticia', $postagem->link);

        $this->actingAs($this->adm())
            ->get(route('postagens.show', $postagem))
            ->assertSee('href="https://www.gov.br/mec/noticia"', false);
    }

    public function test_link_sem_protocolo_ganha_https(): void
    {
        $this->actingAs($this->adm())
            ->post(route('postagens.store'), $this->publicacao(['link' => 'instagram.com/p/DX9OkdWn3z1/']));

        $this->assertSame('https://instagram.com/p/DX9OkdWn3z1/', Postagem::latest('id')->value('link'));
    }

    public function test_link_e_opcional_e_link_invalido_e_recusado(): void
    {
        $this->actingAs($this->adm())
            ->post(route('postagens.store'), $this->publicacao(['link' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull(Postagem::latest('id')->value('link'));

        $this->actingAs($this->adm())
            ->post(route('postagens.store'), $this->publicacao(['link' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('link');
    }

    // ------------------------------------------------------------------- apoio

    private function adm(): User
    {
        return User::factory()->create(['perfil' => User::ADM]);
    }

    private function usuario(array $extra): array
    {
        return $extra + [
            'nome' => 'Itaúna/MG',
            'usuario' => 'itauna.mg',
            'email' => 'itauna@teste.gov.br',
            'password' => '12345678',
            'ativo' => '1',
        ];
    }

    private function publicacao(array $extra): array
    {
        return $extra + [
            'tipo' => 'ministerios_editais',
            'titulo' => 'Edital de teste',
            'texto' => '<p>Conteúdo</p>',
            'data_publicacao' => '2026-10-14',
            'mes_referencia' => '2026-10',
            'situacao' => 'publicado',
        ];
    }
}
