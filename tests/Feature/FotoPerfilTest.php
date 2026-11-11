<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoPerfilTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->cliente = User::factory()->create(['nome' => 'Itaúna MG', 'password' => 'senha-segura-1']);
    }

    public function test_sem_foto_o_avatar_mostra_as_iniciais(): void
    {
        $this->actingAs($this->cliente)->get(route('conta.edit'))
            ->assertOk()
            ->assertSee('Adicionar foto')
            ->assertDontSee(route('usuarios.foto', $this->cliente))
            ->assertSee('<span class="avatar">IM</span>', false);
    }

    public function test_envia_a_foto_grava_inteira_e_mostra_no_painel(): void
    {
        $this->actingAs($this->cliente)
            ->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('eu.png', 900, 600)])
            ->assertRedirect(route('conta.edit'))
            ->assertSessionHas('ok', 'Foto de perfil atualizada.');

        $this->cliente->refresh();
        $this->assertNotNull($this->cliente->foto);
        Storage::disk('local')->assertExists($this->cliente->foto);

        // Regravada como JPEG, inteira: mesma largura e altura, sem corte quadrado.
        [$largura, $altura, $tipo] = getimagesizefromstring(Storage::disk('local')->get($this->cliente->foto));
        $this->assertSame([900, 600, IMAGETYPE_JPEG], [$largura, $altura, $tipo]);

        // Aparece no cartão de perfil, na barra lateral e no topo do celular.
        $pagina = $this->actingAs($this->cliente)->get(route('conta.edit'))->assertOk()->assertSee('Trocar foto');
        $this->assertSame(3, substr_count($pagina->getContent(), '<img src="' . e($this->cliente->fotoUrl()) . '"'));
    }

    public function test_foto_grande_e_reduzida_sem_cortar(): void
    {
        // [enviada] => [gravada]: o maior lado cai para 1600px e a proporção se mantém.
        $casos = [
            'horizontal' => [[3200, 1600], [1600, 800]],
            'vertical'   => [[1000, 2500], [640, 1600]],
            'quadrada'   => [[2000, 2000], [1600, 1600]],
            'pequena'    => [[300, 200], [300, 200]],
        ];

        foreach ($casos as $nome => [[$l, $a], $esperado]) {
            $this->actingAs($this->cliente)
                ->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image("{$nome}.jpg", $l, $a)])
                ->assertSessionHas('ok');

            [$largura, $altura] = getimagesizefromstring(Storage::disk('local')->get($this->cliente->fresh()->foto));
            $this->assertSame($esperado, [$largura, $altura], "Foto {$nome}");
        }
    }

    public function test_foto_do_perfil_abre_ampliada_e_os_avatares_continuam_como_eram(): void
    {
        // Sem foto não há o que ampliar: o avatar do perfil não é um botão.
        $this->actingAs($this->cliente)->get(route('conta.edit'))
            ->assertOk()
            ->assertDontSee('Ampliar a foto de perfil')
            ->assertSee('<span class="avatar avatar-g" data-foto-previa>IM</span>', false);

        $this->actingAs($this->cliente)
            ->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('eu.jpg', 1200, 500)]);
        $url = e($this->cliente->fresh()->fotoUrl());

        $html = $this->actingAs($this->cliente)->get(route('conta.edit'))->assertOk()->getContent();

        // No perfil, a foto vira um botão que abre a janela com a imagem inteira.
        $this->assertMatchesRegularExpression(
            '/<button type="button" class="avatar avatar-g" data-foto-previa[^>]*aria-label="Ampliar a foto de perfil">'
            . '<img src="' . preg_quote($url, '/') . '" alt=""><\/button>/s',
            $html
        );
        $this->assertStringContainsString('id="modal-foto"', $html);
        $this->assertStringContainsString('data-foto-ampliada', $html);
        $this->assertMatchesRegularExpression('/<div class="modal modal-foto".*?data-fecha aria-label="Fechar"/s', $html);

        // Barra lateral e topo do celular: o mesmo avatar de antes, que leva a Minha conta.
        $this->assertStringContainsString('<span class="avatar"><img src="' . $url . '" alt=""></span>', $html);
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('conta.edit'), '/') . '" class="avatar"[^>]*><img src="' . preg_quote($url, '/') . '" alt=""><\/a>/s',
            $html
        );
    }

    public function test_a_foto_continua_depois_de_sair_e_entrar_de_novo(): void
    {
        $this->actingAs($this->cliente)
            ->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('eu.jpg', 400, 400)]);
        $url = $this->cliente->fresh()->fotoUrl();

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.entrar'), ['usuario' => $this->cliente->usuario, 'senha' => 'senha-segura-1'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($this->cliente);

        $this->get(route('painel.inicio'))->assertOk()->assertSee($url, false);
        $resposta = $this->get($url);
        $resposta->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringStartsWith("\xFF\xD8", $resposta->streamedContent());
    }

    public function test_trocar_a_foto_apaga_a_anterior(): void
    {
        $this->actingAs($this->cliente)->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $antiga = $this->cliente->fresh()->foto;

        $this->actingAs($this->cliente)->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('b.webp', 300, 300)]);
        $nova = $this->cliente->fresh()->foto;

        $this->assertNotSame($antiga, $nova);
        Storage::disk('local')->assertMissing($antiga);
        Storage::disk('local')->assertExists($nova);
    }

    public function test_remover_a_foto_volta_para_as_iniciais(): void
    {
        $this->actingAs($this->cliente)->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $caminho = $this->cliente->fresh()->foto;

        $this->actingAs($this->cliente)->delete(route('conta.foto.remover'))
            ->assertRedirect(route('conta.edit'))
            ->assertSessionHas('ok', 'Foto de perfil removida.');

        $this->assertNull($this->cliente->fresh()->foto);
        Storage::disk('local')->assertMissing($caminho);
        $this->actingAs($this->cliente)->get(route('conta.edit'))->assertSee('Adicionar foto');
    }

    public function test_rejeita_arquivo_que_nao_e_imagem_e_imagem_grande(): void
    {
        $this->actingAs($this->cliente)->from(route('conta.edit'))
            ->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['foto' => 'A foto precisa ser uma imagem JPG, PNG ou WebP.']);

        $this->actingAs($this->cliente)->from(route('conta.edit'))
            ->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('enorme.jpg', 800, 800)->size(3000)])
            ->assertSessionHasErrors(['foto' => 'A foto pode ter no máximo 2 MB.']);

        // SVG pode carregar script: não é aceito.
        $this->actingAs($this->cliente)->from(route('conta.edit'))
            ->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')])
            ->assertSessionHasErrors('foto');

        $this->assertNull($this->cliente->fresh()->foto);
    }

    public function test_quem_ve_a_foto(): void
    {
        $this->actingAs($this->cliente)->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $outroCliente = User::factory()->create();
        $adm = User::factory()->create(['perfil' => User::ADM]);

        $this->actingAs($outroCliente)->get(route('usuarios.foto', $this->cliente))->assertForbidden();
        $this->actingAs($adm)->get(route('usuarios.foto', $this->cliente))->assertOk();
        // Usuário sem foto: 404, e o avatar fica com as iniciais.
        $this->actingAs($adm)->get(route('usuarios.foto', $outroCliente))->assertNotFound();
    }

    public function test_sem_login_nao_ve_nem_envia(): void
    {
        $this->get(route('usuarios.foto', $this->cliente))->assertRedirect(route('login'));
        $this->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('a.jpg')])->assertRedirect(route('login'));
    }

    public function test_excluir_o_usuario_apaga_a_foto(): void
    {
        $this->actingAs($this->cliente)->post(route('conta.foto.salvar'), ['foto' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $caminho = $this->cliente->fresh()->foto;
        $master = User::factory()->create(['perfil' => User::MASTER]);

        $this->actingAs($master)->delete(route('usuarios.destroy', $this->cliente))->assertRedirect(route('usuarios.index'));

        Storage::disk('local')->assertMissing($caminho);
    }
}
