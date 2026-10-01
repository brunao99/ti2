<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Habilita ->middleware('perfil:master,adm') nas rotas.
        $middleware->alias([
            'perfil' => \App\Http\Middleware\VerificaPerfil::class,
        ]);

        /*
         * Railway (e cPanel com proxy, e qualquer PaaS) encerra o HTTPS na
         * borda e entrega HTTP para a aplicação. Sem confiar no proxy, o
         * Laravel enxerga uma requisição http e gera todo link e asset com
         * http://, o que o navegador bloqueia como mixed content.
         *
         * O '*' é necessário porque o IP do proxy do Railway é dinâmico e não
         * há faixa fixa para listar. O risco de confiar em qualquer proxy é
         * alguém forjar X-Forwarded-*, e isso só é possível para quem alcança
         * o container direto — no Railway o tráfego só entra pela borda deles.
         * Numa VPS com a aplicação exposta, troque pelo IP real do proxy.
         */
        // Quem já está logado e abre /login vai para o painel. Sem isso o
        // Laravel manda para "/", que por sua vez mandava de volta ao login.
        $middleware->redirectUsersTo('/painel');

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
