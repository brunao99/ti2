/* Algoritmos e Dados — página inicial: menu do celular e linha do topo ao rolar. */
(function () {
  'use strict';

  var topo = document.querySelector('[data-topo]');
  var botao = document.querySelector('[data-menu-botao]');
  var menu = document.getElementById('menu');

  // Linha sob o topo só depois que a página sai do início.
  if (topo) {
    var marcar = function () { topo.classList.toggle('rolou', window.scrollY > 8); };
    marcar();
    window.addEventListener('scroll', marcar, { passive: true });
  }

  // Mostrar ou esconder a senha no acesso ao painel.
  var verSenha = document.querySelector('[data-ver-senha]');
  if (verSenha) {
    verSenha.addEventListener('click', function () {
      var campo = document.getElementById(verSenha.getAttribute('aria-controls'));
      var mostrar = campo.type === 'password';
      campo.type = mostrar ? 'text' : 'password';
      verSenha.textContent = mostrar ? 'Ocultar' : 'Mostrar';
      verSenha.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
      campo.focus();
    });
  }

  // [PARTE 3 - Gabriel] carrossel dos objetivos

  if (!botao || !menu) return;

  function abrir(sim) {
    botao.setAttribute('aria-expanded', sim ? 'true' : 'false');
    menu.classList.toggle('aberto', sim);
    document.body.classList.toggle('menu-aberto', sim);
    if (sim) {
      var primeiro = menu.querySelector('a');
      if (primeiro) primeiro.focus();
    }
  }

  botao.addEventListener('click', function () {
    abrir(botao.getAttribute('aria-expanded') !== 'true');
  });

  // Escolher uma seção fecha o menu.
  menu.addEventListener('click', function (e) {
    if (e.target.closest('a')) abrir(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && botao.getAttribute('aria-expanded') === 'true') {
      abrir(false);
      botao.focus();
    }
  });

  // Ao voltar para a tela larga, o menu volta ao normal.
  window.matchMedia('(min-width: 861px)').addEventListener('change', function (m) {
    if (m.matches) abrir(false);
  });
})();
