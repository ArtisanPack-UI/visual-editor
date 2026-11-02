/**
 * Clipboard payloads for each paste source (#1045).
 *
 * These are modeled on the HTML each app is known to write. They aren't
 * captures: the markup is written by hand to match each app's shape. The
 * one real capture is `github-chromium.json`, a selection copied from a
 * rendered README on github.com.
 */

export interface PasteFixture {
    readonly source: string;
    readonly html: string;
    readonly plainText: string;
}

const MARKDOWN = '# Title\n\nSome **bold** text.\n\n- One\n- Two\n\n> Quoted\n\n```php\necho 1;\n```';

const lines = MARKDOWN.split( '\n' );

/** Each line in its own element, as code editors and notes apps write it. */
const wrapLines = ( open: string, close: string ): string =>
    lines.map( ( line ) => `${ open }${ '' === line ? '<br>' : line.replace( />/g, '&gt;' ) }${ close }` ).join( '' );

/** Sources whose HTML only decorates the Markdown text. */
export const DECORATED_SOURCES: readonly PasteFixture[] = [
    {
        source: 'VS Code',
        html: `<meta charset='utf-8'><div style="color: #d4d4d4;background-color: #1e1e1e;font-family: Menlo, monospace;white-space: pre;">${ wrapLines( '<div><span style="color: #569cd6;">', '</span></div>' ) }</div>`,
        plainText: MARKDOWN,
    },
    {
        source: 'PhpStorm',
        html: `<html><head><style type="text/css">.s0 { color: #cc7832; }</style></head><body><pre style="background-color:#2b2b2b;color:#a9b7c6;font-family:'JetBrains Mono',monospace"><span class="s0">${ MARKDOWN.replace( />/g, '&gt;' ) }</span></pre></body></html>`,
        plainText: MARKDOWN,
    },
    {
        source: 'Notes',
        html: `<meta charset='utf-8'><div style="font-family: -apple-system;">${ wrapLines( '<div>', '</div>' ) }</div>`,
        plainText: MARKDOWN,
    },
    {
        source: 'TextEdit',
        html: `<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><style type="text/css">p.p1 {margin: 0.0px 0.0px 0.0px 0.0px; font: 12.0px Helvetica}</style></head><body>${ wrapLines( '<p class="p1">', '</p>' ) }</body></html>`,
        plainText: MARKDOWN,
    },
    {
        source: 'Google Docs',
        html: `<meta charset='utf-8'><meta charset="utf-8"><b style="font-weight:normal;" id="docs-internal-guid-7f3a"><div dir="ltr" style="margin-left:0pt;" align="left">${ wrapLines( '<p dir="ltr" style="line-height:1.38;margin-top:0pt;"><span style="font-size:11pt;font-family:Arial,sans-serif;">', '</span></p>' ) }</div></b>`,
        plainText: MARKDOWN,
    },
];

/** Sources whose HTML is already rendered, so it should paste as rich content. */
export const RENDERED_SOURCES: readonly PasteFixture[] = [
    {
        source: 'GitHub',
        html: '<h1 dir="auto">Title</h1><p dir="auto">Some <strong>bold</strong> text.</p><ul dir="auto"><li>One</li><li>Two</li></ul><blockquote><p dir="auto">Quoted</p></blockquote><div class="highlight highlight-text-html-php notranslate position-relative overflow-auto" dir="auto"><pre><span class="pl-k">echo</span> <span class="pl-c1">1</span>;</pre></div>',
        plainText: 'Title\n\nSome bold text.\n\nOne\nTwo\n\nQuoted\n\necho 1;',
    },
    {
        source: 'ChatGPT',
        html: '<h1>Title</h1><p>Some <strong>bold</strong> text.</p><ul><li><p>One</p></li><li><p>Two</p></li></ul><blockquote><p>Quoted</p></blockquote><pre><code class="hljs language-php"><span class="hljs-keyword">echo</span> <span class="hljs-number">1</span>;</code></pre>',
        plainText: MARKDOWN,
    },
    {
        source: 'Claude',
        html: '<h1 class="font-bold">Title</h1><p class="whitespace-pre-wrap">Some <strong>bold</strong> text.</p><ul class="list-disc"><li>One</li><li>Two</li></ul><blockquote class="border-l-4"><p>Quoted</p></blockquote><pre class="code-block"><code class="language-php">echo 1;</code></pre>',
        plainText: 'Title\nSome bold text.\nOne\nTwo\nQuoted\necho 1;',
    },
];

/** A plain-text-only clipboard, as a Copy button writes. */
export const PLAIN_MARKDOWN: PasteFixture = { source: 'Copy button', html: '', plainText: MARKDOWN };
