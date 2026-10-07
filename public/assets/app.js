/* Protótipo de homologação — comportamento de navegação.
   Sem backend: os dados são fixos e a "sessão" vive no sessionStorage. */

(function () {
  // ---- tema claro/escuro -------------------------------------------------
  // Escolhido só na tela Minha conta e salvo neste navegador. Sem escolha
  // salva ("auto"), o CSS segue o prefers-color-scheme do aparelho.
  var KEY = "ad-tema";
  function temaSalvo() {
    try { return localStorage.getItem(KEY) || "auto"; } catch (e) { return "auto"; }
  }
  function aplicarTema(t) {
    if (t === "light" || t === "dark") {
      document.documentElement.setAttribute("data-theme", t);
    } else {
      document.documentElement.removeAttribute("data-theme");
    }
  }
  function marcarTema(t) {
    document.querySelectorAll("[data-tema]").forEach(function (b) {
      var on = b.getAttribute("data-tema") === t;
      b.classList.toggle("on", on);
      b.setAttribute("aria-checked", on ? "true" : "false");
    });
  }
  aplicarTema(temaSalvo());
  marcarTema(temaSalvo());

  document.addEventListener("click", function (e) {
    var tema = e.target.closest("[data-tema]");
    if (tema) {
      var t = tema.getAttribute("data-tema");
      try {
        if (t === "auto") localStorage.removeItem(KEY); else localStorage.setItem(KEY, t);
      } catch (e4) {}
      aplicarTema(t);
      marcarTema(t);
    }

    // ---- modal ----------------------------------------------------------
    var abre = e.target.closest("[data-abre]");
    if (abre) {
      e.preventDefault();
      var alvo = document.getElementById(abre.getAttribute("data-abre"));
      if (alvo) {
        alvo.classList.add("show");
        var t = abre.getAttribute("data-titulo");
        var slot = alvo.querySelector("[data-slot-titulo]");
        if (t && slot) slot.textContent = t;
      }
    }
    if (e.target.closest("[data-fecha]")) {
      e.preventDefault();
      var m = e.target.closest(".modal");
      if (m) m.classList.remove("show");
    }
    if (e.target.classList.contains("modal")) e.target.classList.remove("show");

    // ---- grupos de botões (pills / seg) ---------------------------------
    var p = e.target.closest("[data-grupo]");
    if (p) {
      var grupo = p.getAttribute("data-grupo");
      document.querySelectorAll('[data-grupo="' + grupo + '"]').forEach(function (b) {
        b.classList.remove("on");
      });
      p.classList.add("on");
      if (grupo === "tipo") filtrar();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal.show").forEach(function (m) { m.classList.remove("show"); });
    }
  });

  // ---- filtro da listagem de publicações ---------------------------------
  var busca = document.getElementById("busca");
  if (busca) busca.addEventListener("input", filtrar);

  function filtrar() {
    var termo = (busca && busca.value || "").toLowerCase().trim();
    var ativo = document.querySelector('[data-grupo="tipo"].on');
    var tipo = ativo ? ativo.getAttribute("data-valor") : "todos";
    var visiveis = 0;

    document.querySelectorAll("[data-linha]").forEach(function (tr) {
      var okTipo = tipo === "todos" || tr.getAttribute("data-tipo") === tipo;
      var okTermo = !termo || tr.getAttribute("data-titulo").toLowerCase().indexOf(termo) > -1;
      var mostra = okTipo && okTermo;
      tr.style.display = mostra ? "" : "none";
      if (mostra) visiveis++;
    });

    // esconde o cabeçalho de um mês que ficou sem nenhuma publicação
    document.querySelectorAll("[data-mes]").forEach(function (bloco) {
      var linhas = bloco.querySelectorAll("[data-linha]");
      var algum = Array.prototype.some.call(linhas, function (tr) {
        return tr.style.display !== "none";
      });
      bloco.style.display = algum ? "" : "none";
    });

    var vazio = document.getElementById("sem-resultado");
    if (vazio) vazio.classList.toggle("show", visiveis === 0);
  }

  // ---- "login" ------------------------------------------------------------
  var form = document.getElementById("form-login");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var u = document.getElementById("usuario").value.trim();
      var s = document.getElementById("senha").value.trim();
      var erro = document.getElementById("erro-login");
      if (!u || !s) {
        erro.textContent = "Informe usuário e senha para continuar.";
        erro.classList.add("show");
        return;
      }
      try { sessionStorage.setItem("ad-usuario", u); } catch (e2) {}
      window.location.href = "painel.html";
    });
  }

  // ---- saudação conforme o horário ---------------------------------------
  var saud = document.getElementById("saudacao");
  if (saud) {
    var h = new Date().getHours();
    var periodo = h < 12 ? "Bom dia" : h < 18 ? "Boa tarde" : "Boa noite";
    var nome = "Maria";
    try {
      var u2 = sessionStorage.getItem("ad-usuario");
      if (u2) nome = u2.split(/[.@\s]/)[0];
    } catch (e3) {}
    saud.textContent = periodo + ", " + nome;
  }

  // ---- contador de palavras do editor ------------------------------------
  var ta = document.getElementById("texto");
  var cont = document.getElementById("palavras");
  if (ta && cont) {
    ta.addEventListener("input", function () {
      var n = ta.value.trim() ? ta.value.trim().split(/\s+/).length : 0;
      cont.textContent = n + (n === 1 ? " palavra" : " palavras");
    });
  }

  // ---- contagem de prefeituras no modal de alerta ------------------------
  var lista = document.getElementById("prefeituras");
  if (lista) {
    lista.addEventListener("change", atualizaSel);
    atualizaSel();
    var todas = document.getElementById("sel-todas");
    if (todas) todas.addEventListener("click", function () {
      var marcar = lista.querySelectorAll("input:checked").length < lista.querySelectorAll("input").length;
      lista.querySelectorAll("input").forEach(function (i) { i.checked = marcar; });
      atualizaSel();
    });
  }
  function atualizaSel() {
    var n = lista.querySelectorAll("input:checked").length;
    var alvo = document.getElementById("qtd-sel");
    if (alvo) alvo.textContent = n + (n === 1 ? " prefeitura selecionada" : " prefeituras selecionadas");
  }
})();
