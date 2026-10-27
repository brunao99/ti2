<?php

namespace Tests\Feature;

use App\Models\AlertDispatch;
use App\Models\AlertDispatchRecipient;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private User $adm;

    private User $cliente;

    private AlertTemplate $modelo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adm = User::factory()->create(['perfil' => User::ADM]);
        $this->cliente = User::factory()->create(['nome' => 'Itaúna/MG', 'contrato' => '225/2025']);
        $this->modelo = AlertTemplate::create(['nome' => 'Teste', 'mensagem' => 'Nova publicação: {{titulo}}']);
    }

    // ------------------------------------------------------------- acesso

    public function test_cliente_nao_gera_relatorio(): void
    {
        $this->actingAs($this->cliente)->get(route('relatorios.index'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('relatorios.previa'))->assertForbidden();
        $this->actingAs($this->cliente)->post(route('relatorios.gerar'), $this->pedido())->assertForbidden();
    }

    public function test_publicacoes_tem_o_botao_e_a_janela_abre_com_os_clientes(): void
    {
        $semContrato = User::factory()->create(['nome' => 'Pará de Minas/MG', 'contrato' => null]);

        $this->actingAs($this->adm)->get(route('postagens.index'))
            ->assertOk()
            ->assertSee('href="' . route('relatorios.index') . '" data-modal-relatorio', false);

        // Pedido da janela: só o formulário, sem o layout.
        $this->actingAs($this->adm)
            ->get(route('relatorios.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('data-relatorio', false)
            ->assertSee('Itaúna/MG — Contrato 225/2025')
            ->assertSee('<option value="' . $semContrato->id . '" disabled', false)
            ->assertSee('Mês anterior');

        // Direto pelo endereço: a página inteira.
        $this->actingAs($this->adm)->get(route('relatorios.index'))
            ->assertOk()
            ->assertSee('envio-pagina', false)
            ->assertSee('Gerar relatório');
    }

    public function test_periodo_padrao_e_o_mes_anterior(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-04-15 10:00:00', 'America/Sao_Paulo'));

        $this->actingAs($this->adm)->get(route('relatorios.index'))
            ->assertSee('name="inicio" value="2030-03-01"', false)
            ->assertSee('name="fim" value="2030-03-31"', false);
    }

    public function test_menu_mais_leva_ao_relatorio(): void
    {
        $this->actingAs($this->adm)->get(route('painel.mais'))
            ->assertOk()
            ->assertSee(route('relatorios.index'));
    }

    // -------------------------------------------------------------- dados

    public function test_previa_conta_so_o_que_entra_no_relatorio(): void
    {
        $this->cenario();

        $this->actingAs($this->adm)
            ->getJson(route('relatorios.previa', [
                'cliente' => $this->cliente->id, 'inicio' => '2030-03-01', 'fim' => '2030-03-31', 'formato' => 'pdf',
            ]))
            ->assertOk()
            ->assertExactJson([
                'publicacoes' => 3,
                'alertas' => 3,
                'arquivo' => 'Relatório Itaúna-MG 01-03-2030 a 31-03-2030.pdf',
            ]);

        // Sem cliente: as publicações já aparecem; alertas e arquivo, não.
        $this->actingAs($this->adm)
            ->getJson(route('relatorios.previa', ['inicio' => '2030-03-01', 'fim' => '2030-03-31']))
            ->assertExactJson(['publicacoes' => 3, 'alertas' => null, 'arquivo' => null]);

        // Período invertido não conta nada.
        $this->actingAs($this->adm)
            ->getJson(route('relatorios.previa', ['cliente' => $this->cliente->id, 'inicio' => '2030-03-31', 'fim' => '2030-03-01']))
            ->assertExactJson(['publicacoes' => null, 'alertas' => null, 'arquivo' => null]);
    }

    public function test_gera_a_planilha_com_cabecalho_publicacoes_e_alertas(): void
    {
        $this->cenario();

        $resposta = $this->actingAs($this->adm)->post(route('relatorios.gerar'), $this->pedido());

        $resposta->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            "filename*=utf-8''" . rawurlencode('Relatório Itaúna-MG 01-03-2030 a 31-03-2030.xlsx'),
            $resposta->headers->get('Content-Disposition')
        );

        $aba = IOFactory::load($this->arquivo($resposta))->getSheet(0);
        $celulas = $this->textos($aba);

        // Cabeçalho no modelo do cliente: contrato e município na 1ª linha, período na 2ª.
        $this->assertSame(
            'CD ALGORITMOS E DADOS LTDA - CNPJ 58.502.042/0001-00 - CONTRATO 225/2025 - MUNICÍPIO: Itaúna/MG',
            $aba->getCell('A1')->getValue()
        );
        $this->assertStringEndsWith('Período: 01/03/2030 a 31/03/2030', $aba->getCell('A2')->getValue());
        $this->assertSame(['Nº', 'DATA', 'LINK', 'CATEGORIAS', 'CONTEÚDO'], $aba->rangeToArray('A3:E3')[0]);
        $this->assertNotNull($this->linhaCom($aba, 'ALERTAS POR WHATSAPP'));

        // Publicações: só as publicadas do período, em ordem de data, em maiúsculas.
        $titulos = array_values(array_filter($celulas, fn ($t) => str_starts_with($t, 'EDITAL ')));
        $this->assertSame(['EDITAL DO PRIMEIRO DIA', 'EDITAL DO MEIO DO MÊS', 'EDITAL DO ÚLTIMO DIA'], $titulos);
        $this->assertNotContains('EDITAL EM RASCUNHO', $celulas);
        $this->assertNotContains('EDITAL DE ABRIL', $celulas);

        // Alertas: a numeração continua (4, 5, 6) e o conteúdo cita a publicação.
        $linha = $this->linhaCom($aba, 'Comunicado importante referente a Edital do primeiro dia');
        $this->assertSame(4, $aba->getCell("A{$linha}")->getValue());
        $this->assertSame('01/03/2030', $aba->getCell("B{$linha}")->getFormattedValue());
        $this->assertSame('https://www.gov.br/edital-1', $aba->getCell("C{$linha}")->getHyperlink()->getUrl());

        // O alerta das 23h30 do dia 31 (já dia 1º em UTC) entra em março.
        $this->assertNotNull($this->linhaCom($aba, 'Comunicado importante referente a Edital do último dia'));
        $this->assertSame(3, count(array_filter($celulas, fn ($t) => str_starts_with($t, 'Comunicado importante'))));

        $this->assertNotEmpty(array_filter($celulas, fn ($t) => str_starts_with($t, 'Belo Horizonte, ')));
    }

    public function test_titulo_que_parece_formula_entra_como_texto(): void
    {
        $this->publicar('=HYPERLINK("http://exemplo.com","clique")', '2030-03-10');

        $aba = IOFactory::load($this->arquivo(
            $this->actingAs($this->adm)->post(route('relatorios.gerar'), $this->pedido())
        ))->getSheet(0);

        $linha = $this->linhaCom($aba, '=HYPERLINK("HTTP://EXEMPLO.COM","CLIQUE")');
        $this->assertSame(DataType::TYPE_STRING, $aba->getCell("E{$linha}")->getDataType());
    }

    public function test_gera_o_pdf(): void
    {
        $this->cenario();

        $resposta = $this->actingAs($this->adm)->post(route('relatorios.gerar'), $this->pedido(['formato' => 'pdf']));

        $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            "filename*=utf-8''" . rawurlencode('Relatório Itaúna-MG 01-03-2030 a 31-03-2030.pdf'),
            $resposta->headers->get('Content-Disposition')
        );
        $this->assertStringStartsWith('%PDF-', file_get_contents($this->arquivo($resposta)));
    }

    public function test_periodo_sem_nada_ainda_gera_o_relatorio(): void
    {
        $aba = IOFactory::load($this->arquivo(
            $this->actingAs($this->adm)->post(route('relatorios.gerar'), $this->pedido())
        ))->getSheet(0);

        $celulas = $this->textos($aba);
        $this->assertContains('Nenhuma publicação no período.', $celulas);
        $this->assertContains('Nenhum alerta entregue no período.', $celulas);
    }

    // ---------------------------------------------------------- validação

    public function test_cliente_sem_contrato_nao_gera(): void
    {
        $semContrato = User::factory()->create(['contrato' => null]);

        $this->actingAs($this->adm)
            ->postJson(route('relatorios.gerar'), $this->pedido(['cliente' => $semContrato->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente' => 'não tem contrato']);
    }

    public function test_pedido_invalido_volta_para_a_pagina_do_relatorio(): void
    {
        $this->actingAs($this->adm)
            ->from(route('postagens.index'))
            ->post(route('relatorios.gerar'), $this->pedido(['inicio' => '2030-03-31', 'fim' => '2030-03-01', 'formato' => 'docx']))
            ->assertRedirect(route('relatorios.index'))
            ->assertSessionHasErrors(['fim', 'formato']);

        // A página mostra o erro e mantém o que foi digitado.
        $this->actingAs($this->adm)
            ->followingRedirects()
            ->post(route('relatorios.gerar'), $this->pedido(['fim' => '2030-02-01']))
            ->assertOk()
            ->assertSee('A data final não pode ser anterior à inicial.')
            ->assertSee('name="fim" value="2030-02-01"', false);

        // Só cliente de verdade: um ADM não vira município.
        $this->actingAs($this->adm)
            ->postJson(route('relatorios.gerar'), $this->pedido(['cliente' => $this->adm->id]))
            ->assertJsonValidationErrors(['cliente']);
    }

    // ------------------------------------------------------------ apoio

    /**
     * Março de 2030: três publicações no período (uma no primeiro e outra no
     * último dia), um rascunho e uma de abril, que ficam de fora; e alertas
     * para todas as situações que o relatório precisa separar.
     */
    private function cenario(): void
    {
        $primeiro = $this->publicar('Edital do primeiro dia', '2030-03-01', 'https://www.gov.br/edital-1');
        $meio = $this->publicar('Edital do meio do mês', '2030-03-15');
        $ultimo = $this->publicar('Edital do último dia', '2030-03-31');
        $this->publicar('Edital em rascunho', '2030-03-20', situacao: 'rascunho');
        $abril = $this->publicar('Edital de abril', '2030-04-01');

        $outroCliente = User::factory()->create(['contrato' => '1/2030']);

        // Entram: simulado (sem wamid), Meta entregue e Meta lido.
        $this->alertar($primeiro, '2030-03-01 12:00:00', $this->cliente);
        $this->alertar($meio, '2030-03-15 12:00:00', $this->cliente, wamid: 'wamid.1', statusMeta: 'delivered');
        $this->alertar($ultimo, '2030-04-01 02:30:00', $this->cliente, wamid: 'wamid.2', statusMeta: 'read'); // 23h30 do dia 31 em Brasília

        // Ficam de fora: falha, Meta ainda sem confirmação, outro cliente e fora do período.
        $this->alertar($meio, '2030-03-15 12:00:00', $this->cliente, status: 'falha');
        $this->alertar($meio, '2030-03-16 12:00:00', $this->cliente, wamid: 'wamid.3', statusMeta: 'sent');
        $this->alertar($meio, '2030-03-15 12:00:00', $outroCliente);
        $this->alertar($abril, '2030-04-01 12:00:00', $this->cliente);
        $this->alertar($primeiro, '2030-03-01 02:00:00', $this->cliente); // 23h de 28/02 em Brasília
    }

    private function publicar(string $titulo, string $data, ?string $link = null, string $situacao = 'publicado'): Postagem
    {
        return Postagem::create([
            'tipo' => 'ministerios_editais',
            'titulo' => $titulo,
            'texto' => '<p>Texto</p>',
            'data_publicacao' => $data,
            'mes_referencia' => substr($data, 0, 7),
            'situacao' => $situacao,
            'user_id' => $this->adm->id,
            'link' => $link,
        ]);
    }

    private function alertar(
        Postagem $postagem,
        string $quandoUtc,
        User $para,
        string $status = 'enviado',
        ?string $wamid = null,
        ?string $statusMeta = null,
    ): void {
        $disparo = AlertDispatch::create([
            'postagem_id' => $postagem->id,
            'alert_template_id' => $this->modelo->id,
            'user_id' => $this->adm->id,
            'mensagem' => 'Nova publicação',
            'status' => $status === 'enviado' ? 'sucesso' : 'falha',
        ]);
        $disparo->forceFill(['created_at' => CarbonImmutable::parse($quandoUtc, 'UTC')])->save();

        AlertDispatchRecipient::create([
            'alert_dispatch_id' => $disparo->id,
            'user_id' => $para->id,
            'nome' => $para->nome,
            'telefone' => '5531999990001',
            'status' => $status,
            'wamid' => $wamid,
            'status_meta' => $statusMeta,
        ]);
    }

    private function pedido(array $outros = []): array
    {
        return $outros + [
            'cliente' => $this->cliente->id,
            'inicio' => '2030-03-01',
            'fim' => '2030-03-31',
            'formato' => 'xlsx',
        ];
    }

    /** Caminho do arquivo gerado; apagado ao fim do teste. */
    private function arquivo(TestResponse $resposta): string
    {
        $resposta->assertOk();
        $caminho = $resposta->baseResponse->getFile()->getPathname();
        $this->beforeApplicationDestroyed(fn () => @unlink($caminho));

        return $caminho;
    }

    /** @return array<int, string> */
    private function textos($aba): array
    {
        $textos = [];
        foreach ($aba->getRowIterator() as $linha) {
            foreach ($linha->getCellIterator() as $celula) {
                if (is_string($celula->getValue()) && $celula->getValue() !== '') {
                    $textos[] = $celula->getValue();
                }
            }
        }

        return $textos;
    }

    private function linhaCom($aba, string $texto): ?int
    {
        foreach ($aba->getRowIterator() as $linha) {
            foreach ($linha->getCellIterator() as $celula) {
                if ($celula->getValue() === $texto) {
                    return $linha->getRowIndex();
                }
            }
        }

        return null;
    }
}
