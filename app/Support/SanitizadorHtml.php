<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpa o HTML que vem do editor antes de gravar no banco.
 *
 * Sem isso, qualquer pessoa com perfil ADM conseguiria injetar <script> numa
 * publicação e executá-lo no navegador de quem abrisse a página — inclusive no
 * do Master. O editor é confiável; o HTML que chega pela requisição não é,
 * porque nada impede alguém de montar o POST na mão.
 *
 * A abordagem é lista de permissão: tudo que não está listado é descartado.
 * strip_tags() não serve aqui, porque ele preserva atributos — deixaria passar
 * um <p onclick="..."> inteiro.
 */
class SanitizadorHtml
{
    /** Tags que o editor produz e que fazem sentido numa publicação. */
    private const TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote', 'a',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'code',
    ];

    /** Atributos aceitos, por tag. */
    private const ATRIBUTOS = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    /** Só estes esquemas de URL passam. */
    private const ESQUEMAS = ['http', 'https', 'mailto', 'tel'];

    /**
     * Destas tags o conteúdo também morre, em vez de virar texto solto.
     * Desembrulhar um <script> deixaria "alert(1)" escrito na publicação.
     */
    private const DESCARTAR_CONTEUDO = ['script', 'style', 'iframe', 'object', 'embed', 'template'];

    public static function limpar(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '' || $html === '<p><br></p>') {
            return '';
        }

        $doc = new DOMDocument();

        // LIBXML_NOENT não: manter as entidades como vieram.
        // O meta force-utf8 evita que acentos virem mojibake.
        $anterior = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="raiz">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $doc->getElementById('raiz');

        if (! $raiz) {
            return '';
        }

        self::percorrer($raiz);

        $saida = '';
        foreach ($raiz->childNodes as $filho) {
            $saida .= $doc->saveHTML($filho);
        }

        return trim($saida);
    }

    /** Visita a árvore de baixo para cima, para poder remover nós com segurança. */
    private static function percorrer(DOMNode $no): void
    {
        // Cópia da lista: remover durante a iteração pularia irmãos.
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho instanceof DOMElement) {
                self::percorrer($filho);
                self::tratarElemento($filho);
            } elseif ($filho->nodeType === XML_COMMENT_NODE) {
                $filho->parentNode->removeChild($filho);
            }
        }
    }

    private static function tratarElemento(DOMElement $el): void
    {
        $tag = strtolower($el->nodeName);

        if (in_array($tag, self::DESCARTAR_CONTEUDO, true)) {
            $el->parentNode->removeChild($el);

            return;
        }

        if (! in_array($tag, self::TAGS, true)) {
            // Tag não permitida: o conteúdo de texto fica, a tag some.
            // Assim um <div> vira texto solto em vez de sumir com o parágrafo.
            while ($el->firstChild) {
                $el->parentNode->insertBefore($el->firstChild, $el);
            }
            $el->parentNode->removeChild($el);

            return;
        }

        $permitidos = self::ATRIBUTOS[$tag] ?? [];

        foreach (iterator_to_array($el->attributes) as $attr) {
            if (! in_array(strtolower($attr->name), $permitidos, true)) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a') {
            self::tratarLink($el);
        }
    }

    private static function tratarLink(DOMElement $el): void
    {
        $href = trim($el->getAttribute('href'));

        // Sem esquema explícito é caminho relativo — aceitável.
        $esquema = parse_url($href, PHP_URL_SCHEME);

        if ($esquema !== null && ! in_array(strtolower($esquema), self::ESQUEMAS, true)) {
            $el->removeAttribute('href');

            return;
        }

        // "javascript:alert(1)" com espaços ou entidades no meio não é pego pelo
        // parse_url; esta checagem fecha a brecha.
        $normalizado = strtolower(preg_replace('/[\s\x00-\x1F]/', '', $href));

        if (str_starts_with($normalizado, 'javascript:') || str_starts_with($normalizado, 'data:')) {
            $el->removeAttribute('href');

            return;
        }

        // Link externo abrindo em nova aba precisa de rel, senão a página
        // aberta ganha acesso a window.opener.
        if ($el->getAttribute('target') !== '') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }
}
