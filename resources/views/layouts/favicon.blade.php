{{--
  Favicon de todas as páginas (login e painel). O .ico atende navegadores
  antigos e quem pede /favicon.ico direto; o SVG é o principal; o PNG é o
  ícone da tela inicial do iPhone. O ?v= (Asset::url) muda quando o arquivo
  muda, para o navegador trocar o ícone guardado em cache.
--}}
<link rel="icon" href="{{ \App\Support\Asset::url('favicon.ico') }}" sizes="16x16 32x32 48x48">
<link rel="icon" href="{{ \App\Support\Asset::url('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ \App\Support\Asset::url('apple-touch-icon.png') }}">
