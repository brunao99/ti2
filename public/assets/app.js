/* Protótipo de homologação — comportamento de navegação.
   Sem backend: os dados são fixos e a "sessão" vive no sessionStorage. */

(function () {
  // ---- tema claro/escuro -------------------------------------------------
  var KEY = "ad-tema";
  function aplicarTema(t) {
    document.documentElement.setAttribute("data-theme", t);
    try { localStorage.setItem(KEY, t); } catch (e) {}
  }
  try {
    var salvo = localStorage.getItem(KEY);
    if (salvo) document.documentElement.setAttribute("data-theme", salvo);
  } catch (e) {}

  document.addEventListener("click", function (e) {
    // .toggle e a bolinha da barra; .toggle-tema e a linha inteira na tela "Mais"
    var tg = e.target.closest(".toggle, .toggle-tema");
    if (tg) {
      var atual = document.documentElement.getAttribute("data-theme");
      if (!atual) {
        // sem preferência salva: parte do que o sistema do visitante usa
        var sistemaEscuro = window.matchMedia &&
          window.matchMedia("(prefers-color-scheme: dark)").matches;
        atual = sistemaEscuro ? "dark" : "light";
      }
      aplicarTema(atual === "dark" ? "light" : "dark");
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
