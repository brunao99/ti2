<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'usuario' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'perfil' => User::CLIENT,
            'ativo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /** Tem WhatsApp e autorizou receber alertas. */
    public function recebeAlertas(): static
    {
        return $this->comTelefone()->state(fn (array $attributes) => [
            'alerta_consentimento_em' => now(),
        ]);
    }

    /** Tem WhatsApp, mas não autorizou. */
    public function comTelefone(): static
    {
        return $this->state(fn (array $attributes) => [
            'telefone' => '5531' . fake()->unique()->numerify('9########'),
        ]);
    }
}
