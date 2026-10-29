{{-- Conteúdo do avatar: a foto de perfil, se houver; senão, as iniciais. --}}
@php($fotoUrl = $usuario->fotoUrl())@if ($fotoUrl)<img src="{{ $fotoUrl }}" alt="">@else{{ $usuario->iniciais() }}@endif