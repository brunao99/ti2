<?php

namespace Tests\Unit;

use App\Support\Recorrencia;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RecorrenciaTest extends TestCase
{
    private function datas(Recorrencia $r, string $de, string $ate): array
    {
        return array_values(array_map(fn ($d) => $d->format('Y-m-d'),
            $r->entre(CarbonImmutable::parse($de), CarbonImmutable::parse($ate))));
    }

    public function test_sem_repeticao_tem_uma_data_so(): void
    {
        $r = new Recorrencia(CarbonImmutable::parse('2026-11-04'));

        $this->assertSame(1, $r->total());
        $this->assertSame(['2026-11-04'], $this->datas($r, '2026-11-01', '2026-12-31'));
        $this->assertSame([], $this->datas($r, '2026-11-05', '2026-12-31'));
    }

    public function test_semanal_ate_uma_data(): void
    {
        $r = new Recorrencia(CarbonImmutable::parse('2026-11-04'), 'semanal', 'data', CarbonImmutable::parse('2026-11-27'));

        $this->assertSame(4, $r->total());
        $this->assertSame(['2026-11-04', '2026-11-11', '2026-11-18', '2026-11-25'], $this->datas($r, '2026-10-01', '2027-01-31'));
        $this->assertSame('2026-11-25', $r->ultima()->format('Y-m-d'));
    }

    public function test_quinzenal_apos_um_numero_de_vezes(): void
    {
        $r = new Recorrencia(CarbonImmutable::parse('2026-11-10'), 'quinzenal', 'vezes', null, 6);

        $this->assertSame(6, $r->total());
        $this->assertSame('2027-01-19', $r->ultima()->format('Y-m-d'));
        $this->assertSame(['2026-12-08', '2026-12-22'], $this->datas($r, '2026-12-01', '2026-12-31'));
    }

    public function test_mensal_no_dia_31_cai_no_ultimo_dia_dos_meses_curtos(): void
    {
        $r = new Recorrencia(CarbonImmutable::parse('2027-01-31'), 'mensal');

        $this->assertNull($r->total());
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'], $this->datas($r, '2027-01-01', '2027-04-30'));
    }

    public function test_bimestral_e_trimestral_sem_fim(): void
    {
        $bi = new Recorrencia(CarbonImmutable::parse('2026-11-30'), 'bimestral');
        $tri = new Recorrencia(CarbonImmutable::parse('2026-11-30'), 'trimestral');

        $this->assertSame(['2027-01-30', '2027-03-30', '2027-05-30'], $this->datas($bi, '2026-12-01', '2027-06-30'));
        $this->assertSame(['2027-02-28', '2027-05-30'], $this->datas($tri, '2026-12-01', '2027-06-30'));
    }

    public function test_intervalo_distante_comeca_perto_da_data_pedida(): void
    {
        $r = new Recorrencia(CarbonImmutable::parse('2026-01-07'), 'semanal');

        $this->assertSame(['2030-06-05', '2030-06-12'], $this->datas($r, '2030-06-01', '2030-06-14'));
        $this->assertSame(['2030-06-05', '2030-06-12', '2030-06-19'],
            array_values(array_map(fn ($d) => $d->format('Y-m-d'), $r->proximas(CarbonImmutable::parse('2030-06-01'), 3))));
    }
}
