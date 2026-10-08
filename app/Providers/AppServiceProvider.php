<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\Alertas\AlertSender;
use App\Services\Alertas\MetaWhatsAppSender;
use App\Services\Alertas\SimulatedWhatsAppSender;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // WHATSAPP_DRIVER=meta envia de verdade; qualquer outro valor (o
        // padrão é "simulado") mantém o envio simulado. Resolvido a cada uso,
        // então trocar a variável não exige mexer em código.
        $this->app->bind(AlertSender::class, fn ($app) => config('services.whatsapp.driver') === 'meta'
            ? $app->make(MetaWhatsAppSender::class)
            : $app->make(SimulatedWhatsAppSender::class));
    }

    public function boot(): void
    {
        /*
         * Cinto e suspensório para o HTTPS.
         *
         * O trustProxies no bootstrap/app.php já resolve o caso normal, lendo
         * o X-Forwarded-Proto. Mas se o proxy não mandar esse cabeçalho, ou se
         * alguma URL for gerada fora do ciclo de uma requisição (fila, comando
         * de console, e-mail), o Laravel voltaria a escrever http://.
         *
         * A decisão vem do APP_URL: se o endereço configurado é https, todo
         * link gerado também é. Em desenvolvimento o APP_URL é http://localhost
         * e nada muda — forçar https localmente quebraria o php artisan serve.
         */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
