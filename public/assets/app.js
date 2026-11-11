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

    // ---- enviar alerta e gerar relatório numa janela sobre a página --------
    // O link continua apontando para a página inteira: se o pedido falhar
    // (alerta já enviado, sessão expirada), segue o link normalmente.
    var remota = e.target.closest("a[data-modal-envio], a[data-modal-relatorio], a[data-modal-agenda]");
    if (remota && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
      e.preventDefault();
      abrirRemota(remota);
    }

    // ---- atalhos de período do relatório --------------------------------
    var atalho = e.target.closest("[data-relatorio] [data-periodo]");
    if (atalho) {
      var formRel = atalho.closest("[data-relatorio]");
      formRel.querySelector("[name=inicio]").value = atalho.getAttribute("data-inicio");
      formRel.querySelector("[name=fim]").value = atalho.getAttribute("data-fim");
      marcarAtalho(formRel);
      atualizarRelatorio(formRel);
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
      fecharPop();
      document.querySelectorAll("details.ag-novo[open]").forEach(function (d) { d.open = false; });
    }
  });

  // ---- janelas (modal) ------------------------------------------------------
  var origemModal = null;

  function abrirModal(modal, origem) {
    origemModal = origem || document.activeElement;
    modal.classList.add("show");
    document.body.classList.add("modal-aberta");
    // Primeiro campo do formulário; o X só se nada mais houver.
    var foco = modal.querySelector(".modal-body [aria-current], .modal-body select, .modal-body input:not([type=hidden]), .modal-body textarea")
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

  function abrirRemota(link) {
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
        // Uma janela por vez: quem abre outra (o + do celular, o detalhe) fecha a anterior.
        fecharPop();
        document.querySelectorAll(".modal.show").forEach(function (m) { if (m !== modal) m.classList.remove("show"); });
        modal.innerHTML = html;
        atualizarPreview(modal.querySelector("[data-envio]"));
        iniciarRelatorio(modal.querySelector("[data-relatorio]"));
        iniciarEvento(modal.querySelector("[data-evento]"));
        iniciarFeriado(modal.querySelector("[data-feriado]"));
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

  // ---- relatório da prefeitura ---------------------------------------------
  // Atalho marcado = o período nos campos é exatamente o dele.
  function marcarAtalho(form) {
    var de = form.querySelector("[name=inicio]").value;
    var ate = form.querySelector("[name=fim]").value;
    form.querySelectorAll("[data-periodo]").forEach(function (b) {
      var on = b.getAttribute("data-inicio") === de && b.getAttribute("data-fim") === ate;
      b.classList.toggle("on", on);
      b.setAttribute("aria-pressed", on ? "true" : "false");
    });
  }

  function camposRelatorio(form) {
    return {
      cliente: form.querySelector("[name=cliente]").value,
      inicio: form.querySelector("[name=inicio]").value,
      fim: form.querySelector("[name=fim]").value,
      formatos: Array.prototype.map.call(form.querySelectorAll("[name='formatos[]']:checked"), function (i) { return i.value; })
    };
  }

  // "Gerar e baixar" só fica ativo com cliente e período válidos; a prévia
  // é pedida ao servidor um instante depois da última mudança.
  var esperaPrevia = null;
  function atualizarRelatorio(form) {
    if (!form) return;
    var c = camposRelatorio(form);
    form.querySelector("[data-gerar]").disabled = !(c.cliente && c.inicio && c.fim && c.inicio <= c.fim && c.formatos.length);
    clearTimeout(esperaPrevia);
    esperaPrevia = setTimeout(function () { buscarPrevia(form); }, 250);
  }

  function buscarPrevia(form) {
    var c = camposRelatorio(form);
    var pedido = form.pedidoPrevia = (form.pedidoPrevia || 0) + 1;
    var params = new URLSearchParams({ cliente: c.cliente, inicio: c.inicio, fim: c.fim });
    c.formatos.forEach(function (f) { params.append("formatos[]", f); });
    fetch(form.getAttribute("data-previa") + "?" + params.toString(), {
      headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }
    })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (d) {
        if (pedido !== form.pedidoPrevia) return; // chegou depois de uma resposta mais nova
        form.querySelector("[data-previa-publicacoes]").textContent = d.publicacoes === null ? "—" : d.publicacoes;
        form.querySelector("[data-previa-alertas]").textContent = d.alertas === null ? "—" : d.alertas;
        form.querySelector("[data-previa-arquivo]").textContent = d.arquivos.length ? d.arquivos.join("\n")
          : d.publicacoes === null ? "Confira o período: a data final vem antes da inicial."
          : !c.formatos.length ? "Marque pelo menos um formato."
          : "Selecione o cliente para ver o nome do arquivo.";
      })
      .catch(function () {});
  }

  function iniciarRelatorio(form) {
    if (!form) return;
    marcarAtalho(form);
    atualizarRelatorio(form);
  }

  function mostrarErros(form, erros) {
    var caixa = form.querySelector("[data-erros]");
    caixa.innerHTML = "";
    erros.forEach(function (m) {
      var p = document.createElement("p");
      p.textContent = m;
      caixa.appendChild(p);
    });
    caixa.hidden = erros.length === 0;
    if (erros.length) caixa.scrollIntoView({ block: "nearest" });
  }

  // Nome do arquivo pelo cabeçalho da resposta; a forma filename* guarda
  // os acentos ("Relatório Itaúna-MG ...").
  function nomeDoArquivo(resposta) {
    var cd = resposta.headers.get("Content-Disposition") || "";
    var utf = /filename\*=utf-8''([^;]+)/i.exec(cd);
    if (utf) { try { return decodeURIComponent(utf[1]); } catch (e5) {} }
    var simples = /filename="?([^";]+)"?/i.exec(cd);
    return simples ? simples[1] : "relatorio";
  }

  function baixar(blob, nome) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  document.addEventListener("change", function (e) {
    var rel = e.target.closest("[data-relatorio]");
    if (!rel) return;
    if (e.target.type === "date") marcarAtalho(rel);
    atualizarRelatorio(rel);
  });

  // Gera um formato: devolve o arquivo ou os erros de validação.
  function gerarArquivo(form, formato) {
    var dados = new FormData(form);
    dados.delete("formatos[]");
    dados.append("formatos[]", formato);
    return fetch(form.action, {
      method: "POST",
      body: dados,
      headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }
    }).then(function (r) {
      if (r.status === 422) {
        return r.json().then(function (d) {
          var erros = [];
          Object.keys(d.errors || {}).forEach(function (k) { erros = erros.concat(d.errors[k]); });
          throw { erros: erros.length ? erros : [d.message] };
        });
      }
      if (r.status === 419) throw { erros: ["A sessão expirou. Recarregue a página e tente de novo."] };
      if (!r.ok) throw { erros: ["Não foi possível gerar o relatório. Tente de novo em instantes."] };
      return r.blob().then(function (blob) { return { blob: blob, nome: nomeDoArquivo(r) }; });
    });
  }

  // Cada formato marcado vira um arquivo baixado separado, sem sair da tela.
  // Os dois são gerados antes de qualquer download: se um falhar, nenhum sai
  // pela metade. Erro de validação aparece dentro da janela. Sem fetch, o
  // formulário segue o envio normal.
  document.addEventListener("submit", function (e) {
    var form = e.target.closest("[data-relatorio]");
    if (!form || !window.fetch || !window.URL || !URL.createObjectURL) return;
    e.preventDefault();

    var botao = form.querySelector("[data-gerar]");
    if (botao.disabled) return;
    var formatos = camposRelatorio(form).formatos;
    var rotulo = botao.textContent;
    botao.disabled = true;
    botao.textContent = formatos.length > 1 ? "Gerando os 2 arquivos…" : "Gerando…";
    mostrarErros(form, []);

    Promise.all(formatos.map(function (f) { return gerarArquivo(form, f); }))
      .then(function (arquivos) {
        // Um pequeno intervalo entre os downloads: alguns navegadores
        // ignoram o segundo quando os dois saem no mesmo instante.
        arquivos.forEach(function (a, i) {
          setTimeout(function () { baixar(a.blob, a.nome); }, i * 600);
        });
        var modal = form.closest(".modal");
        if (modal) setTimeout(function () { fecharModal(modal); }, (arquivos.length - 1) * 600 + 100);
      })
      .catch(function (falha) {
        mostrarErros(form, falha && falha.erros
          ? falha.erros
          : ["Não foi possível gerar o relatório. Verifique a conexão e tente de novo."]);
      })
      .then(function () {
        if (!document.body.contains(form)) return; // a janela já fechou
        botao.textContent = rotulo;
        atualizarRelatorio(form);
      });
  });

  // ---- foto de perfil (Minha conta) -----------------------------------------
  // Mesmos tipos e limite do servidor (App\Support\FotoPerfil). Foto grande de
  // celular é reduzida aqui antes do envio, para caber nos 2 MB.
  var TIPOS_FOTO = ["image/jpeg", "image/png", "image/webp"];
  var MAX_FOTO = 2 * 1024 * 1024;
  var MAX_ORIGINAL = 20 * 1024 * 1024;

  function erroFoto(form, msg) {
    var p = form.querySelector("[data-foto-erro]");
    p.textContent = msg;
    p.hidden = !msg;
  }

  function cancelarFoto(form) {
    var previa = form.querySelector("[data-foto-previa]");
    if (previa.dataset.original !== undefined) previa.innerHTML = previa.dataset.original;
    if (form.urlPrevia) { URL.revokeObjectURL(form.urlPrevia); form.urlPrevia = null; }
    form.querySelector("[data-foto-arquivo]").value = "";
    form.querySelector("[data-foto-acoes]").hidden = true;
  }

  // Imagem inteira, sem corte, com o maior lado em até 1600px (o mesmo limite
  // do servidor), em JPEG. O navegador já aplica a rotação da foto (EXIF) ao
  // desenhar. Sem suporte, segue o arquivo original.
  var LADO_FOTO = 1600;

  function reduzirFoto(arquivo, url) {
    if (!window.DataTransfer || !HTMLCanvasElement.prototype.toBlob) return Promise.resolve(arquivo);
    return new Promise(function (pronto) {
      var img = new Image();
      img.onload = function () {
        var escala = Math.min(1, LADO_FOTO / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement("canvas");
        c.width = Math.max(1, Math.round(img.naturalWidth * escala));
        c.height = Math.max(1, Math.round(img.naturalHeight * escala));
        var ctx = c.getContext("2d");
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
          pronto(blob ? new File([blob], "foto.jpg", { type: "image/jpeg" }) : arquivo);
        }, "image/jpeg", 0.9);
      };
      img.onerror = function () { pronto(arquivo); };
      img.src = url;
    });
  }

  document.addEventListener("change", function (e) {
    var entrada = e.target.closest("[data-foto-arquivo]");
    if (!entrada) return;
    var form = entrada.closest("[data-foto]");
    var arquivo = entrada.files && entrada.files[0];
    erroFoto(form, "");
    if (!arquivo) { cancelarFoto(form); return; }

    if (TIPOS_FOTO.indexOf(arquivo.type) === -1) {
      cancelarFoto(form);
      erroFoto(form, "A foto precisa ser uma imagem JPG, PNG ou WebP.");
      return;
    }
    if (arquivo.size > MAX_ORIGINAL) {
      cancelarFoto(form);
      erroFoto(form, "Essa imagem é grande demais. Escolha uma de até 20 MB.");
      return;
    }

    var previa = form.querySelector("[data-foto-previa]");
    if (previa.dataset.original === undefined) previa.dataset.original = previa.innerHTML;
    if (form.urlPrevia) URL.revokeObjectURL(form.urlPrevia);
    form.urlPrevia = URL.createObjectURL(arquivo);
    var img = new Image();
    img.alt = "Prévia da nova foto";
    img.src = form.urlPrevia;
    previa.innerHTML = "";
    previa.appendChild(img);

    reduzirFoto(arquivo, form.urlPrevia).then(function (final) {
      if (final.size > MAX_FOTO) {
        cancelarFoto(form);
        erroFoto(form, "A foto pode ter no máximo 2 MB.");
        return;
      }
      if (final !== arquivo) {
        var dt = new DataTransfer();
        dt.items.add(final);
        entrada.files = dt.files;
      }
      form.querySelector("[data-foto-acoes]").hidden = false;
    });
  });

  document.addEventListener("click", function (e) {
    var cancelar = e.target.closest("[data-foto-cancelar]");
    if (cancelar) cancelarFoto(cancelar.closest("[data-foto]"));

    // Ampliar: abre a imagem que está no avatar (a foto salva ou a prévia),
    // inteira. Sem foto, o avatar só tem as iniciais e nada acontece.
    var avatar = e.target.closest("[data-foto-previa]");
    var foto = avatar && avatar.querySelector("img");
    var janela = document.getElementById("modal-foto");
    if (foto && janela) {
      janela.querySelector("[data-foto-ampliada]").src = foto.currentSrc || foto.src;
      abrirModal(janela, avatar);
    }
  });

  document.addEventListener("submit", function (e) {
    if (e.target.closest("[data-foto-remover]") && !window.confirm("Remover a foto de perfil?")) {
      e.preventDefault();
      return;
    }
    var form = e.target.closest("[data-foto]");
    if (!form) return;
    var btn = form.querySelector("[data-foto-acoes] button[type=submit]");
    if (btn) { btn.disabled = true; btn.textContent = "Salvando…"; }
  });

  // Página do relatório (fora da janela).
  document.querySelectorAll("[data-relatorio]").forEach(iniciarRelatorio);

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

  // ==== agenda ==============================================================

  function guardar(chave, valor) { try { localStorage.setItem(chave, JSON.stringify(valor)); } catch (e6) {} }
  function ler(chave, padrao) {
    try { var v = JSON.parse(localStorage.getItem(chave)); return v === null ? padrao : v; } catch (e7) { return padrao; }
  }

  // ---- confirmação dos formulários que apagam ou enviam -------------------
  document.addEventListener("submit", function (e) {
    var form = e.target.closest("form[data-confirmar]");
    if (form && !window.confirm(form.getAttribute("data-confirmar"))) e.preventDefault();
  }, true);

  // ---- "Mostrar": liga e desliga cada tipo; fica salvo neste navegador -----
  var agenda = document.querySelector("[data-agenda]");
  if (agenda) {
    var ocultos = ler("agenda.ocultos", []);
    agenda.querySelectorAll("[data-mostrar]").forEach(function (caixa) {
      var tipo = caixa.getAttribute("data-mostrar");
      caixa.checked = ocultos.indexOf(tipo) === -1;
      agenda.classList.toggle("sem-" + tipo, !caixa.checked);
      caixa.addEventListener("change", function () {
        agenda.classList.toggle("sem-" + tipo, !caixa.checked);
        var lista = ler("agenda.ocultos", []).filter(function (t) { return t !== tipo; });
        if (!caixa.checked) lista.push(tipo);
        guardar("agenda.ocultos", lista);
      });
    });
  }

  // ---- período: o seletor de data leva direto ao dia escolhido -------------
  document.addEventListener("change", function (e) {
    var ir = e.target.closest("[data-ir-para]");
    if (ir && ir.value) window.location.href = ir.getAttribute("data-ir-para").replace("__DATA__", ir.value);

    var filtro = e.target.closest("form[data-filtro-auto]");
    if (filtro && e.target.tagName === "SELECT") filtro.submit();
  });
  document.addEventListener("click", function (e) {
    var periodo = e.target.closest(".ag-periodo input[type=date]");
    if (periodo && periodo.showPicker) { try { periodo.showPicker(); } catch (e8) {} }

    // Menu "Novo": fecha ao clicar fora ou ao escolher um tipo.
    document.querySelectorAll("details.ag-novo[open]").forEach(function (d) {
      if (!d.contains(e.target) || e.target.closest("a")) d.open = false;
    });
  });

  // ---- detalhe do evento ---------------------------------------------------
  // Computador: cartão ao lado do evento. Celular: janela que sobe de baixo.
  var pop = document.querySelector("[data-agenda-pop]");
  var origemPop = null;

  function fecharPop() {
    if (!pop || pop.hidden) return;
    pop.hidden = true;
    pop.innerHTML = "";
    if (origemPop && origemPop.focus) origemPop.focus();
    origemPop = null;
  }

  function posicionarPop(alvo) {
    var r = alvo.getBoundingClientRect();
    var largura = pop.offsetWidth, altura = pop.offsetHeight, folga = 12;
    var esquerda = r.right + folga;
    if (esquerda + largura > window.innerWidth - folga) esquerda = r.left - largura - folga;
    if (esquerda < folga) esquerda = Math.max(folga, (window.innerWidth - largura) / 2);
    var topo = Math.min(Math.max(folga, r.top - 20), window.innerHeight - altura - folga);
    pop.style.left = esquerda + "px";
    pop.style.top = Math.max(folga, topo) + "px";
  }

  document.addEventListener("click", function (e) {
    var link = e.target.closest("a[data-agenda-detalhe]");
    if (link && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
      e.preventDefault();
      var celular = !pop || window.matchMedia("(max-width: 900px)").matches;
      if (celular) {
        fetch(link.href, { headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "text/html" } })
          .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
          .then(function (html) {
            var modal = document.getElementById("modal-dinamica");
            if (!modal) {
              modal = document.createElement("div");
              modal.id = "modal-dinamica";
              modal.className = "modal";
              modal.setAttribute("data-dinamica", "");
              document.body.appendChild(modal);
            }
            modal.innerHTML = '<div class="modal-box ag-det-caixa">' + html + "</div>";
            abrirModal(modal, link);
          })
          .catch(function () { window.location.href = link.href; });
        return;
      }
      fetch(link.href, { headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "text/html" } })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(function (html) {
          fecharPop();
          origemPop = link;
          pop.innerHTML = html;
          pop.hidden = false;
          posicionarPop(link);
          var x = pop.querySelector("[data-fecha-pop]");
          if (x) x.focus();
        })
        .catch(function () { window.location.href = link.href; });
      return;
    }

    if (e.target.closest("[data-fecha-pop]")) {
      var m = e.target.closest(".modal");
      if (m) fecharModal(m); else fecharPop();
      return;
    }
    if (pop && !pop.hidden && !pop.contains(e.target)) fecharPop();
  });
  window.addEventListener("resize", fecharPop);

  // ---- janelas de formulário (evento e feriado) ----------------------------
  // Envio por fetch: erro de validação aparece dentro da janela; dando certo,
  // segue para o endereço devolvido. Sem fetch, o envio normal continua.
  document.addEventListener("submit", function (e) {
    var form = e.target.closest("form[data-janela-form]");
    if (!form || e.defaultPrevented || !window.fetch || !window.FormData) return;
    e.preventDefault();

    var botao = form.querySelector("[data-salvar]");
    if (botao.disabled) return;
    var rotulo = botao.innerHTML;
    botao.disabled = true;
    botao.textContent = "Salvando…";
    mostrarErros(form, []);

    fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, dados: d }; });
    }).then(function (res) {
      if (res.dados && res.dados.ir) { window.location.href = res.dados.ir; return; }
      var erros = [];
      Object.keys((res.dados && res.dados.errors) || {}).forEach(function (k) { erros = erros.concat(res.dados.errors[k]); });
      if (!erros.length) erros = [res.status === 419
        ? "A sessão expirou. Recarregue a página e tente de novo."
        : "Não foi possível salvar. Verifique a conexão e tente de novo."];
      mostrarErros(form, erros);
      botao.disabled = false;
      botao.innerHTML = rotulo;
    }).catch(function () {
      mostrarErros(form, ["Não foi possível salvar. Verifique a conexão e tente de novo."]);
      botao.disabled = false;
      botao.innerHTML = rotulo;
    });
  });

  // ---- janela "Novo evento" -------------------------------------------------
  function tipoDoEvento(form) {
    var marcado = form.querySelector("[name=tipo]:checked");
    return marcado ? marcado.value : "compromisso";
  }

  // Campos de outro tipo ficam escondidos e desligados: não vão no envio.
  function aplicarTipo(form) {
    var tipo = tipoDoEvento(form);
    form.setAttribute("data-tipo", tipo);
    var titulo = form.querySelector("[name=titulo]");
    if (titulo && titulo.getAttribute("data-ph-" + tipo)) titulo.placeholder = titulo.getAttribute("data-ph-" + tipo);
    form.querySelectorAll("[data-so]").forEach(function (bloco) {
      var vale = (" " + bloco.getAttribute("data-so") + " ").indexOf(" " + tipo + " ") !== -1;
      bloco.querySelectorAll("input, select, textarea").forEach(function (c) {
        if (c.closest("[data-so]") === bloco) c.disabled = !vale;
      });
    });
    aplicarDiaInteiro(form);
    aplicarTermina(form);
  }

  // Compromisso de dia inteiro não tem Início nem Fim.
  function aplicarDiaInteiro(form) {
    var tipo = tipoDoEvento(form);
    var caixa = form.querySelector("[data-ag-dia-inteiro]");
    var semHora = tipo === "compromisso" && caixa && caixa.checked;
    form.querySelectorAll("[data-hora]").forEach(function (bloco) {
      var vale = (" " + bloco.getAttribute("data-so") + " ").indexOf(" " + tipo + " ") !== -1;
      if (!vale) return; // escondido pelo tipo
      bloco.hidden = semHora;
      bloco.querySelectorAll("input").forEach(function (i) { i.disabled = semHora; });
    });
  }

  function aplicarTermina(form) {
    var repetir = form.querySelector("[data-ag-repetir]");
    var campo = form.querySelector("[data-termina-campo]");
    var termina = form.querySelector("[data-ag-termina]");
    var repete = repetir.value !== "nao";
    campo.hidden = !repete;
    termina.disabled = !repete;
    form.querySelectorAll("[data-termina-so]").forEach(function (el) {
      var vale = repete && el.getAttribute("data-termina-so") === termina.value;
      el.hidden = !vale;
      (el.matches("input") ? [el] : Array.prototype.slice.call(el.querySelectorAll("input"))).forEach(function (i) { i.disabled = !vale; });
    });
    var unidades = { compromisso: "reuniões", alerta: "envios", lembrete: "lembretes", data_importante: "datas" };
    var unidade = form.querySelector("[data-ag-unidade]");
    if (unidade) unidade.textContent = unidades[tipoDoEvento(form)];
  }

  function atualizarPrevia(form) {
    var postagem = form.querySelector("[data-ag-postagem]");
    var modelo = form.querySelector("[data-ag-modelo]");
    var texto = form.querySelector("[data-ag-previa-texto]");
    var hora = form.querySelector("[data-ag-previa-hora]");
    if (!texto) return;
    var p = postagem && postagem.options[postagem.selectedIndex];
    var m = modelo && modelo.options[modelo.selectedIndex];
    hora.textContent = (form.querySelector("[name=hora_inicio]") || {}).value || "";
    if (!p || !p.value || !m || !m.value) { texto.textContent = ""; return; }
    texto.textContent = (m.getAttribute("data-mensagem") || "")
      .split("{{titulo}}").join(p.getAttribute("data-titulo"))
      .split("{{tipo}}").join(p.getAttribute("data-tipo"))
      .split("{{data_publicacao}}").join(p.getAttribute("data-data"));
  }

  var esperaResumo = null;
  function atualizarResumo(form) {
    clearTimeout(esperaResumo);
    esperaResumo = setTimeout(function () {
      var params = new URLSearchParams();
      params.append("tipo", tipoDoEvento(form));
      ["data", "hora_inicio", "hora_fim", "repetir", "termina", "termina_em", "termina_vezes"].forEach(function (n) {
        var c = form.querySelector("[name=" + n + "]");
        if (c && !c.disabled) params.append(n, c.value);
      });
      var dia = form.querySelector("[data-ag-dia-inteiro]");
      if (dia && !dia.disabled && dia.checked) params.append("dia_inteiro", "1");

      fetch(form.getAttribute("data-resumo") + "?" + params.toString(), { headers: { "Accept": "application/json" } })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (d) {
          var caixa = form.querySelector("[data-ag-frase]");
          caixa.hidden = !d.frase;
          caixa.querySelector("span").innerHTML = d.frase || "";
          if (d.dicas) {
            form.querySelectorAll("[data-ag-repetir] option").forEach(function (op) {
              var dica = d.dicas[op.value];
              op.textContent = op.getAttribute("data-rotulo") + (dica ? " — " + dica : "");
            });
          }
        })
        .catch(function () {});
    }, 200);
  }

  function iniciarEvento(form) {
    if (!form) return;
    aplicarTipo(form);
    atualizarPrevia(form);
    atualizarResumo(form);
  }

  document.addEventListener("change", function (e) {
    var form = e.target.closest("[data-evento]");
    if (!form) return;
    if (e.target.name === "tipo") aplicarTipo(form);
    if (e.target.matches("[data-ag-dia-inteiro]")) aplicarDiaInteiro(form);
    if (e.target.matches("[data-ag-repetir], [data-ag-termina]")) aplicarTermina(form);
    if (e.target.matches("[data-ag-postagem], [data-ag-modelo], [name=hora_inicio]")) atualizarPrevia(form);
    if (e.target.matches("[data-ag-resumo], [name=tipo]")) atualizarResumo(form);
  });
  document.addEventListener("input", function (e) {
    var form = e.target.closest("[data-evento]");
    if (form && e.target.matches("[name=termina_vezes], [name=hora_inicio], [name=hora_fim]")) {
      atualizarResumo(form);
      atualizarPrevia(form);
    }
  });

  // ---- janela "Novo feriado": estado ou município conforme a abrangência --
  function aplicarAbrangencia(form) {
    var valor = form.querySelector("[data-fer-abrangencia]").value;
    form.querySelectorAll("[data-fer-local]").forEach(function (bloco) {
      var vale = bloco.getAttribute("data-fer-local") === valor;
      bloco.hidden = !vale;
      bloco.querySelectorAll("input, select").forEach(function (c) { c.disabled = !vale; });
    });
  }
  function iniciarFeriado(form) { if (form) aplicarAbrangencia(form); }
  document.addEventListener("change", function (e) {
    if (e.target.matches("[data-fer-abrangencia]")) aplicarAbrangencia(e.target.closest("[data-feriado]"));
  });

  // Páginas de reserva (sem a janela) também ganham o comportamento.
  document.querySelectorAll(".envio-pagina [data-evento]").forEach(iniciarEvento);
  document.querySelectorAll(".envio-pagina [data-feriado]").forEach(iniciarFeriado);

  // ---- avisos da agenda ----------------------------------------------------
  // Compromisso chegando (Lembrar antes), lembrete no horário e prazo próximo
  // (Avisar antes). Cada aviso aparece uma vez por navegador.
  var urlAvisos = document.body.getAttribute("data-avisos-agenda");
  var icones = { compromisso: "calendario", alerta: "enviar", lembrete: "sino", data_importante: "bandeira" };

  function mostrarAviso(a) {
    var caixa = document.querySelector(".ag-avisos");
    if (!caixa) {
      caixa = document.createElement("div");
      caixa.className = "ag-avisos";
      caixa.setAttribute("role", "status");
      caixa.setAttribute("aria-live", "polite");
      document.body.appendChild(caixa);
    }
    if (caixa.querySelector('[data-aviso="' + a.id + '"]')) return;

    var el = document.createElement("div");
    el.className = "ag-aviso t-" + (a.tipo === "data_importante" ? "data" : a.tipo);
    el.setAttribute("data-aviso", a.id);
    var ico = document.createElement("span");
    ico.className = "ag-tipo-ico";
    ico.innerHTML = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + { calendario: '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
          enviar: '<path d="M21 3 10.5 13.5M21 3l-6.5 18-4-7.5L3 9.5z"/>',
          sino: '<path d="M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z"/><path d="M10 20a2.2 2.2 0 0 0 4 0"/>',
          bandeira: '<path d="M5.5 21V4M5.5 4.5h12l-2.5 4 2.5 4h-12"/>' }[icones[a.tipo] || "calendario"] + "</svg>";
    var corpo = document.createElement("div");
    var b = document.createElement("b"); b.textContent = a.titulo;
    var p = document.createElement("p"); p.textContent = a.texto;
    var link = document.createElement("a"); link.href = a.url; link.textContent = "Abrir na agenda";
    corpo.appendChild(b); corpo.appendChild(p); corpo.appendChild(link);
    var x = document.createElement("button");
    x.type = "button"; x.setAttribute("aria-label", "Dispensar aviso"); x.textContent = "✕";
    x.addEventListener("click", function () {
      el.remove();
      var vistos = ler("agenda.avisos", []);
      vistos.push(a.id);
      guardar("agenda.avisos", vistos.slice(-200));
    });
    link.addEventListener("click", function () { x.click(); });
    el.appendChild(ico); el.appendChild(corpo); el.appendChild(x);
    caixa.appendChild(el);
  }

  function buscarAvisos() {
    fetch(urlAvisos, { headers: { "Accept": "application/json" } })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (d) {
        var vistos = ler("agenda.avisos", []);
        (d.avisos || []).forEach(function (a) { if (vistos.indexOf(a.id) === -1) mostrarAviso(a); });
      })
      .catch(function () {});
  }

  if (urlAvisos && window.fetch) {
    buscarAvisos();
    setInterval(buscarAvisos, 60000);
  }
})();
