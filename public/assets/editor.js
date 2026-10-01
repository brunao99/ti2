/* Editor de texto com formatação, sem biblioteca externa.
 *
 * Por que não usar Quill/TinyMCE: os dois exigem CDN ou um passo de build.
 * O deploy é num cPanel sem SSH, e a rede da faculdade pode bloquear CDN —
 * qualquer um dos dois deixaria o editor quebrado sem aviso. O escopo aqui
 * (negrito, listas, links) não justifica esse risco.
 *
 * Usa document.execCommand, que está marcado como deprecated mas continua
 * funcionando em todos os navegadores atuais e não tem substituto padronizado.
 * Se um dia parar, o textarea por baixo continua funcionando.
 */
(function () {
  var caixa = document.getElementById('editor-caixa');
  if (!caixa) return;

  var campo = document.getElementById('texto');
  var area = document.getElementById('editor-area');
  var barra = document.getElementById('editor-barra');
  var contador = document.getElementById('palavras');
  var estado = document.getElementById('editor-estado');

  function contar(texto) {
    var limpo = (texto || '').replace(/\s+/g, ' ').trim();
    var n = limpo ? limpo.split(' ').length : 0;
    contador.textContent = n + (n === 1 ? ' palavra' : ' palavras');
  }

  // Navegador sem suporte: o textarea assume e nada se perde.
  if (!area || !document.execCommand || !('contentEditable' in document.body)) {
    caixa.classList.add('sem-editor');
    campo.addEventListener('input', function () { contar(campo.value); });
    contar(campo.value);
    return;
  }

  var BOTOES = [
    { cmd: 'formatBlock', valor: 'h2', rotulo: 'T1', titulo: 'Título' },
    { cmd: 'formatBlock', valor: 'h3', rotulo: 'T2', titulo: 'Subtítulo' },
    { cmd: 'formatBlock', valor: 'p', rotulo: '¶', titulo: 'Parágrafo' },
    { separador: true },
    { cmd: 'bold', rotulo: 'B', titulo: 'Negrito (Ctrl+B)', estilo: 'font-weight:700' },
    { cmd: 'italic', rotulo: 'I', titulo: 'Itálico (Ctrl+I)', estilo: 'font-style:italic' },
    { cmd: 'underline', rotulo: 'U', titulo: 'Sublinhado (Ctrl+U)', estilo: 'text-decoration:underline' },
    { cmd: 'strikeThrough', rotulo: 'S', titulo: 'Riscado', estilo: 'text-decoration:line-through' },
    { separador: true },
    { cmd: 'insertUnorderedList', rotulo: '• Lista', titulo: 'Lista com marcadores' },
    { cmd: 'insertOrderedList', rotulo: '1. Lista', titulo: 'Lista numerada' },
    { cmd: 'formatBlock', valor: 'blockquote', rotulo: '“ Citação', titulo: 'Citação' },
    { separador: true },
    { cmd: 'link', rotulo: 'Link', titulo: 'Inserir link' },
    { cmd: 'unlink', rotulo: 'Tirar link', titulo: 'Remover link' },
    { separador: true },
    { cmd: 'removeFormat', rotulo: 'Limpar', titulo: 'Limpar formatação' },
  ];

  BOTOES.forEach(function (b) {
    if (b.separador) {
      var sep = document.createElement('span');
      sep.className = 'editor-sep';
      barra.appendChild(sep);
      return;
    }
    var bt = document.createElement('button');
    bt.type = 'button';               // sem isso, cada clique envia o formulário
    bt.textContent = b.rotulo;
    bt.title = b.titulo;
    if (b.estilo) bt.setAttribute('style', b.estilo);
    bt.addEventListener('mousedown', function (e) {
      e.preventDefault();             // preserva a seleção dentro da área
    });
    bt.addEventListener('click', function () { executar(b); });
    barra.appendChild(bt);
  });

  function executar(b) {
    area.focus();

    if (b.cmd === 'link') {
      var url = prompt('Endereço do link:', 'https://');
      if (!url) return;
      if (!/^(https?:|mailto:|tel:)/i.test(url)) url = 'https://' + url.replace(/^\/+/, '');
      document.execCommand('createLink', false, url);
    } else if (b.cmd === 'formatBlock') {
      document.execCommand('formatBlock', false, '<' + b.valor + '>');
    } else {
      document.execCommand(b.cmd, false, null);
    }

    sincronizar();
    marcarAtivos();
  }

  /** Acende os botões conforme a formatação sob o cursor. */
  function marcarAtivos() {
    var botoes = barra.querySelectorAll('button');
    BOTOES.filter(function (b) { return !b.separador; }).forEach(function (b, i) {
      var ativo = false;
      try {
        if (b.cmd === 'formatBlock') {
          ativo = (document.queryCommandValue('formatBlock') || '').toLowerCase() === b.valor;
        } else if (b.cmd !== 'link' && b.cmd !== 'unlink' && b.cmd !== 'removeFormat') {
          ativo = document.queryCommandState(b.cmd);
        }
      } catch (e) { /* comando não suportado; segue sem destaque */ }
      botoes[i].classList.toggle('on', ativo);
    });
  }

  /** O textarea é o campo que vai no POST; ele precisa espelhar a área. */
  function sincronizar() {
    var html = area.innerHTML.trim();
    if (html === '<br>' || html === '<p><br></p>' || area.textContent.trim() === '') html = '';
    campo.value = html;
    contar(area.textContent);
  }

  // Colar: entra como texto puro, senão vem CSS e tags do Word junto.
  area.addEventListener('paste', function (e) {
    e.preventDefault();
    var texto = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, texto);
  });

  area.addEventListener('input', sincronizar);
  area.addEventListener('keyup', marcarAtivos);
  area.addEventListener('mouseup', marcarAtivos);

  // Rede de segurança: no envio, o que está na área vale.
  campo.form.addEventListener('submit', sincronizar);

  area.contentEditable = 'true';
  area.setAttribute('role', 'textbox');
  area.setAttribute('aria-multiline', 'true');
  area.setAttribute('aria-label', 'Texto da publicação');
  if (area.innerHTML.trim() === '') area.innerHTML = '<p><br></p>';

  caixa.classList.add('com-editor');
  estado.textContent = 'Editor com formatação';
  sincronizar();
})();
