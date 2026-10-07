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
        var t = abre.getAttribute("data-titulo");
        var slot = alvo.querySelector("[data-slot-titulo]");
        if (t && slot) slot.textContent = t;
        abrirModal(alvo, abre);
      }
    }
    if (e.target.closest("[data-fecha]")) {
      var m = e.target.closest(".modal");
      if (m) { e.preventDefault(); fecharModal(m); }
    }
    if (e.target.classList.contains("modal")) fecharModal(e.target);

    // ---- enviar alerta numa janela sobre a página ---------------------------
    // O link continua apontando para a página de envio: se o pedido falhar
    // (alerta já enviado, sessão expirada), segue o link normalmente.
    var envio = e.target.closest("a[data-modal-envio]");
    if (envio && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
      e.preventDefault();
      abrirEnvio(envio);
    }

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
      document.querySelectorAll(".modal.show").forEach(fecharModal);
    }
  });

  // ---- janelas (modal) ------------------------------------------------------
  var origemModal = null;

  function abrirModal(modal, origem) {
    origemModal = origem || document.activeElement;
    modal.classList.add("show");
    document.body.classList.add("modal-aberta");
    // Primeiro campo do formulário; o X só se nada mais houver.
    var foco = modal.querySelector(".modal-body select, .modal-body input:not([type=hidden]), .modal-body textarea")
      || modal.querySelector("button");
    if (foco) foco.focus();
  }

  function fecharModal(modal) {
    modal.classList.remove("show");
    if (!document.querySelector(".modal.show")) document.body.classList.remove("modal-aberta");
    if (modal.hasAttribute("data-dinamica")) modal.innerHTML = "";
    if (origemModal && origemModal.focus) origemModal.focus();
    origemModal = null;
  }

  function abrirEnvio(link) {
    if (link.getAttribute("aria-busy") === "true") return;
    link.setAttribute("aria-busy", "true");

    fetch(link.href, { headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "text/html" } })
      .then(function (r) {
        if (!r.ok) throw new Error(r.status);
        return r.text();
      })
      .then(function (html) {
        var modal = document.getElementById("modal-dinamica");
        if (!modal) {
          modal = document.createElement("div");
          modal.id = "modal-dinamica";
          modal.className = "modal";
          modal.setAttribute("data-dinamica", "");
          document.body.appendChild(modal);
        }
        modal.innerHTML = html;
        atualizarPreview(modal.querySelector("[data-envio]"));
        abrirModal(modal, link);
      })
      .catch(function () { window.location.href = link.href; })
      .then(function () { link.removeAttribute("aria-busy"); });
  }

  // ---- prévia da mensagem do alerta ---------------------------------------
  // Mesmas variáveis que o servidor substitui em AlertaController::montarMensagem.
  function atualizarPreview(form) {
    if (!form) return;
    var select = form.querySelector("[name=alert_template_id]");
    var alvo = form.querySelector(".envio-preview");
    if (!select || !alvo) return;
    var op = select.options[select.selectedIndex];
    alvo.value = (op && op.getAttribute("data-mensagem") || "")
      .split("{{titulo}}").join(form.getAttribute("data-titulo"))
      .split("{{tipo}}").join(form.getAttribute("data-tipo"))
      .split("{{data_publicacao}}").join(form.getAttribute("data-data"));
  }

  document.addEventListener("change", function (e) {
    if (e.target.matches("[data-envio] [name=alert_template_id]")) {
      atualizarPreview(e.target.closest("[data-envio]"));
    }
  });

  // Página de reserva: a prévia já nasce preenchida se houver modelo escolhido.
  document.querySelectorAll("[data-envio]").forEach(atualizarPreview);

  // Evita duplo clique no disparo e no reenvio: o servidor também protege.
  document.addEventListener("submit", function (e) {
    var form = e.target.closest("[data-envio], [data-reenviar]");
    if (!form) return;
    var btn = form.querySelector("button[type=submit]");
    if (btn) { btn.disabled = true; btn.textContent = "Enviando…"; }
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
