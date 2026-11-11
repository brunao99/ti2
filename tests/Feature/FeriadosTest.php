<?php

namespace Tests\Feature;

use App\Models\AgendaEvento;
use App\Models\Feriado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeriadosTest extends TestCase
{
    use RefreshDatabase;

    private User $adm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', AgendaEvento::FUSO));
        $this->adm = User::factory()->create(['perfil' => User::ADM]);
    }

    public function test_calendario_vem_com_a_carga_inicial_agrupado_por_mes(): void
    {
        $this->actingAs($this->adm)->get(route('feriados.index'))
            ->assertOk()
            ->assertSee('Janeiro 2026')
            ->assertSee('Confraternização Universal')
            ->assertSee('Expediente a partir das 14h')
            ->assertSee('Municipal · Belo Horizonte')
            ->assertSee('Só em 2026')
            ->assertSee('Todo ano')
            ->assertSeeInOrder(['Proclamação da República', 'Próximo'])
            ->assertSee('Copiar para 2027');
    }

    public function test_filtros_e_busca(): void
    {
        $this->actingAs($this->adm)->get(route('feriados.index', ['tipo' => 'ponto_facultativo']))
            ->assertSee('Carnaval')->assertDontSee('Tiradentes');

        $this->actingAs($this->adm)->get(route('feriados.index', ['abrangencia' => 'municipal']))
            ->assertSee('Imaculada Conceição')->assertDontSee('Tiradentes');

        $this->actingAs($this->adm)->get(route('feriados.index', ['busca' => 'consciencia']))
            ->assertSee('Dia Nacional de Zumbi e da Consciência Negra')->assertDontSee('Natal');

        // Em 2027, as datas que repetem valem; as móveis de 2026, não.
        $this->actingAs($this->adm)->get(route('feriados.index', ['ano' => 2027]))
            ->assertSee('25/12/2027')->assertDontSee('Quarta-feira de Cinzas');
    }

    public function test_cadastra_feriado_municipal_e_ele_aparece_na_agenda(): void
    {
        $this->actingAs($this->adm)->postJson(route('feriados.salvar'), [
            'descricao' => 'Aniversário de Belo Horizonte', 'data' => '2026-12-12', 'tipo' => 'ponto_facultativo',
            'abrangencia' => 'municipal', 'local' => 'Belo Horizonte/MG', 'expediente' => '', 'repete_todo_ano' => '1',
        ])->assertOk()->assertJson(['ir' => route('feriados.index', ['ano' => 2026])]);

        $feriado = Feriado::where('descricao', 'Aniversário de Belo Horizonte')->sole();
        $this->assertTrue($feriado->repete_todo_ano);

        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2027-12-12']))
            ->assertSee('Aniversário de Belo Horizonte — ponto facultativo');
    }

    public function test_validacoes_do_feriado(): void
    {
        $this->actingAs($this->adm)->postJson(route('feriados.salvar'), [
            'descricao' => '', 'data' => '2026-12-12', 'tipo' => 'feriado', 'abrangencia' => 'municipal', 'local' => '',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'descricao' => 'Informe a descrição do feriado.',
            'local' => 'Escolha o município.',
        ]);

        $this->actingAs($this->adm)->postJson(route('feriados.salvar'), [
            'descricao' => 'Data magna', 'data' => '2026-07-16', 'tipo' => 'feriado', 'abrangencia' => 'estadual', 'local' => 'Minas',
        ])->assertStatus(422)->assertJsonValidationErrors(['local' => 'Escolha um estado da lista.']);

        // Mesmo dia e mesma abrangência: Natal já está cadastrado e repete todo ano.
        $this->actingAs($this->adm)->postJson(route('feriados.salvar'), [
            'descricao' => 'Natal de novo', 'data' => '2027-12-25', 'tipo' => 'feriado', 'abrangencia' => 'nacional', 'repete_todo_ano' => '1',
        ])->assertStatus(422)->assertJsonValidationErrors(['data' => 'Já existe uma data nesse dia com a mesma abrangência: Natal.']);
    }

    public function test_editar_e_excluir(): void
    {
        $natal = Feriado::where('descricao', 'Natal')->sole();

        $this->actingAs($this->adm)->get(route('feriados.editar', $natal), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Editar feriado');

        $this->actingAs($this->adm)->put(route('feriados.atualizar', $natal), [
            'descricao' => 'Natal', 'data' => '2026-12-25', 'tipo' => 'feriado', 'abrangencia' => 'nacional',
            'expediente' => 'Sem expediente', 'repete_todo_ano' => '1',
        ])->assertRedirect(route('feriados.index', ['ano' => 2026]));
        $this->assertSame('Sem expediente', $natal->fresh()->expediente);

        $this->actingAs($this->adm)->delete(route('feriados.excluir', $natal), ['ano' => 2026])->assertRedirect();
        $this->assertNull(Feriado::find($natal->id));
    }

    public function test_copiar_para_o_proximo_ano_leva_so_as_datas_moveis(): void
    {
        $total = Feriado::count();

        $this->actingAs($this->adm)->post(route('feriados.copiar'), ['ano' => 2026])
            ->assertRedirect(route('feriados.index', ['ano' => 2027]))
            ->assertSessionHas('ok');

        // Carnaval (2), Cinzas, Paixão de Cristo e Corpus Christi.
        $this->assertSame($total + 5, Feriado::count());
        $this->assertTrue(Feriado::whereDate('data', '2027-02-16')->where('descricao', 'Carnaval')->exists());

        // Copiar de novo não duplica.
        $this->actingAs($this->adm)->post(route('feriados.copiar'), ['ano' => 2026]);
        $this->assertSame($total + 5, Feriado::count());
    }

    public function test_cliente_nao_mexe_nos_feriados(): void
    {
        $cliente = User::factory()->create(['perfil' => User::CLIENT]);
        $natal = Feriado::where('descricao', 'Natal')->sole();

        $this->actingAs($cliente)->delete(route('feriados.excluir', $natal))->assertForbidden();
        $this->actingAs($cliente)->post(route('feriados.copiar'))->assertForbidden();
        $this->assertNotNull($natal->fresh());
    }
}
