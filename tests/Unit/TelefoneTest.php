<?php

namespace Tests\Unit;

use App\Support\Telefone;
use PHPUnit\Framework\TestCase;

class TelefoneTest extends TestCase
{
    public function test_normaliza_o_que_a_pessoa_digitar(): void
    {
        $this->assertSame('5531999990001', Telefone::normalizar('(31) 99999-0001'));
        $this->assertSame('5531999990001', Telefone::normalizar('+55 31 99999-0001'));
        $this->assertSame('5531999990001', Telefone::normalizar('5531999990001'));
        $this->assertSame('553132220001', Telefone::normalizar('(31) 3222-0001'));
        $this->assertNull(Telefone::normalizar('  '));
    }

    public function test_valida_e_formata(): void
    {
        $this->assertTrue(Telefone::valido('5531999990001'));
        $this->assertFalse(Telefone::valido('551234'));
        $this->assertFalse(Telefone::valido(null));

        $this->assertSame('+55 (31) 99999-0001', Telefone::formatar('5531999990001'));
        $this->assertSame('+55 (31) 3222-0001', Telefone::formatar('553132220001'));
        $this->assertSame('(31) 99999-0001', Telefone::formatar('5531999990001', comDdi: false));
    }
}
