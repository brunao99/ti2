<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Relatorios\RelatorioPrefeitura;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Pedido de geração do relatório. Sem JavaScript, um erro volta para a
 * página do relatório (e não para Publicações, onde a janela estaria fechada).
 */
class GerarRelatorioRequest extends FormRequest
{
    protected $redirectRoute = 'relatorios.index';

    private ?User $cliente = null;

    public function rules(): array
    {
        return [
            'cliente' => ['required', 'integer', Rule::exists('users', 'id')->where('perfil', User::CLIENT)],
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'formato' => ['required', Rule::in(['xlsx', 'pdf'])],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente.required' => 'Selecione o cliente.',
            'cliente.integer' => 'Selecione um cliente da lista.',
            'cliente.exists' => 'Selecione um cliente da lista.',
            'inicio.required' => 'Informe a data inicial do período.',
            'inicio.date_format' => 'A data inicial é inválida.',
            'fim.required' => 'Informe a data final do período.',
            'fim.date_format' => 'A data final é inválida.',
            'fim.after_or_equal' => 'A data final não pode ser anterior à inicial.',
            'formato.required' => 'Escolha o formato do relatório.',
            'formato.in' => 'Escolha planilha (XLSX) ou PDF.',
        ];
    }

    /** O contrato vai no cabeçalho: sem ele o relatório não sai. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('cliente')) {
                    return;
                }

                if (blank($this->cliente()->contrato)) {
                    $validator->errors()->add('cliente', 'Este cliente não tem contrato cadastrado. Preencha o contrato no cadastro do usuário.');
                }
            },
        ];
    }

    public function cliente(): User
    {
        return $this->cliente ??= User::findOrFail($this->integer('cliente'));
    }

    public function inicio(): CarbonImmutable
    {
        return RelatorioPrefeitura::dia($this->validated('inicio'));
    }

    public function fim(): CarbonImmutable
    {
        return RelatorioPrefeitura::dia($this->validated('fim'));
    }
}
