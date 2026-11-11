{{-- Ícones da agenda, em traço, na cor do texto. Uso: @include('agenda._icone', ['nome' => 'sino']) --}}
<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($nome)
  @case('calendario')
    <rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>
    @break
  @case('feriados')
    <rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/>
    @break
  @case('enviar')
    <path d="M21 3 10.5 13.5M21 3l-6.5 18-4-7.5L3 9.5z"/>
    @break
  @case('sino')
    <path d="M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z"/><path d="M10 20a2.2 2.2 0 0 0 4 0"/>
    @break
  @case('bandeira')
    <path d="M5.5 21V4M5.5 4.5h12l-2.5 4 2.5 4h-12"/>
    @break
  @case('repetir')
    <path d="M17 2.5 20.5 6 17 9.5"/><path d="M3.5 11V9a3 3 0 0 1 3-3h14"/><path d="M7 21.5 3.5 18 7 14.5"/><path d="M20.5 13v2a3 3 0 0 1-3 3h-14"/>
    @break
  @case('esquerda')
    <path d="m14.5 6-6 6 6 6"/>
    @break
  @case('direita')
    <path d="m9.5 6 6 6-6 6"/>
    @break
  @case('baixo')
    <path d="m6 9.5 6 6 6-6"/>
    @break
  @case('mais')
    <path d="M12 5v14M5 12h14"/>
    @break
  @case('fechar')
    <path d="M6 6l12 12M18 6 6 18"/>
    @break
  @case('lapis')
    <path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-3-3L5 17z"/><path d="m14.5 7.5 3 3"/>
    @break
  @case('lixeira')
    <path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13M10 11v6M14 11v6"/>
    @break
  @case('copiar')
    <rect x="8.5" y="8.5" width="12" height="12" rx="2"/><path d="M15.5 8.5v-3a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h3"/>
    @break
  @case('info')
    <circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5h.01"/>
    @break
  @case('certo')
    <path d="m5 12.5 4.5 4.5L19 7.5"/>
    @break
@endswitch
</svg>
