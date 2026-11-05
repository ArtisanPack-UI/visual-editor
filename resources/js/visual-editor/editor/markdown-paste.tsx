/**
 * Markdown paste from any source (#1045).
 *
 * `pasteHandler` only converts Markdown when the clipboard has no HTML, or
 * HTML with no formatting. Code editors, notes apps and Google Docs put
 * their own markup around the text, so `# Title` lands as a literal
 * paragraph, and upstream has no option to change that.
 *
 * This runs ahead of upstream's listeners, on the window's capture phase,
 * and rewrites the HTML they will read from the event. Everything after
 * that stays upstream's: the same conversion, the same choice between
 * block and inline paste, and one undo step. Fields that paste literally,
 * such as the Code block, still read the plain text and aren't affected.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { useEffect, useRef } from 'react';
import type { ReactElement } from 'react';

import { store as blockEditorStore } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { useRegistry } from '@wordpress/data';

import { normalizeCodeLanguage } from '../blocks/code/languages';
import { IN_TREE_CANVAS_ROOT } from '../support/canvas-scoped-styles';
import { offerPastedImageImport } from './pasted-image-import';

const FENCE           = /^ {0,3}(`{3,}|~{3,})/;
const HEADING         = /^ {0,3}#{1,6}[ \t]+\S/;
const LIST_ITEM       = /^\s*(?:[-*+•]|\d{1,3}[.)])[ \t]+\S/;
const QUOTE           = /^ {0,3}>[ \t]?\S/;
const TABLE_DELIMITER = /^ {0,3}\|?[ \t]*:?-+:?[ \t]*(?:\|[ \t]*:?-+:?[ \t]*)+\|?$/;
const SOURCE_CODE     = /^\s*(?:\/\*|\*\/|<\?php|#!)/;
const INLINE_MARKUP   = /(?:^|[^\w*])\*\*[^\s*](?:[^*\n]*[^\s*])?\*\*(?![\w*])|!?\[[^[\]\n]+\]\((?:https?:\/\/|mailto:|\/|\.)[^[)\s]+\)/g;

/** Past this size, detection isn't worth the time on a paste that isn't Markdown. */
const MAX_DETECTED_LENGTH = 100_000;

/** A fenced code block, exactly as the upstream converter matches one. */
const CONVERTED_FENCE = /(?:^|\n)(?: {0,3})(```+|~~~+)(?: *)([^\s`~]*)\n([\s\S]*?)\n(?: {0,3})\1/g;

/**
 * What the upstream converter would pass through as live markup: a raw
 * tag, a character reference or an escape that could spell a scheme, or a
 * script scheme itself.
 */
const UNSAFE_MARKUP = /<[a-z/!?][\w-]*(?:[\s/>]|$)|&(?:#|[a-z][a-z0-9]*;)|(?:java|vb)script[\s\\]*:|data[\s\\]*:[\s\\]*text\//i;

/** Markup that only a rendered page has, which upstream maps to real blocks. */
const RENDERED_CONTENT = 'h1,h2,h3,h4,h5,h6,ul,ol,table,blockquote,img,strong,em,pre code,[class*="highlight"] pre';

/**
 * An inline code span, exactly as the upstream converter matches one, and
 * a link definition, which it reads before it looks for spans.
 */
const CONVERTED_CODE_SPAN = /(^|[^\\])(`+)([^\r]*?[^`])\2(?!`)/gm;
const LINK_DEFINITION     = /^ {0,3}\[[^\]]+\]:/;

/** A line of a YAML mapping, which a list of `key: value` items also is. */
const YAML_LINE = /^(?:---\s*$|\s*(?:-\s+)?[\w.-]+:(?:\s|$))/;

/** A fence marker, anywhere. */
const LEFTOVER_FENCE = /`{3}|~{3}/;

/** The clipboard type VS Code writes, whose JSON names the language. */
const VS_CODE_CLIPBOARD_TYPE = 'vscode-editor-data';

/** Editor languages whose text can be Markdown. */
const MARKDOWN_LANGUAGES = new Set( [ 'markdown', 'mdx', 'plaintext' ] );

/** Link text that's only a URL, which is how apps auto-link what was typed. */
const BARE_URL = /^\s*(?:https?:\/\/|mailto:|www\.)\S*\s*$/i;

const HIGHLIGHTED_CODE = '[class*="highlight-source-"] pre,[class*="highlight-text-"] pre,.highlight pre';
const HIGHLIGHT_SCOPE  = /highlight-(?:source|text)-([\w+-]+)/;

/**
 * Whether plain text is clearly Markdown rather than prose or source code.
 *
 * Deliberately conservative, because a miss leaves the text as it was and
 * a false positive rewrites it. One signal isn't enough unless it can't be
 * anything else, so a stray `*` or a single `- ` changes nothing. A heading
 * has to stand apart from the next line, which is what separates it from a
 * shell or Python comment above a line of code.
 *
 * @since 1.20.0
 *
 * @param text Plain text from the clipboard.
 */
export function looksLikeMarkdown( text: string ): boolean {
    if ( text.length > MAX_DETECTED_LENGTH ) {
        return false;
    }

    const lines       = text.split( /\r?\n/ );
    let openFence     = '';
    let headings      = 0;
    let tightHeadings = 0;
    let listItems     = 0;
    let quotes        = 0;

    for ( let index = 0; index < lines.length; index++ ) {
        const line = lines[ index ];
        const next = lines[ index + 1 ] ?? '';

        if ( '' !== openFence ) {
            const closing = line.trim();

            if ( closing.length >= openFence.length && closing === openFence[ 0 ].repeat( closing.length ) ) {
                return true;
            }

            continue;
        }

        const fence = FENCE.exec( line );

        if ( null !== fence ) {
            openFence = fence[ 1 ];
        } else if ( SOURCE_CODE.test( line ) ) {
            return false;
        } else if ( line.includes( '|' ) && TABLE_DELIMITER.test( next.trimEnd() ) ) {
            return true;
        } else if ( HEADING.test( line ) ) {
            if ( '' === next.trim() ) {
                headings++;
            } else {
                tightHeadings++;
            }
        } else if ( LIST_ITEM.test( line ) ) {
            listItems++;
        } else if ( QUOTE.test( line ) ) {
            quotes++;
        }
    }

    if ( listItems > 0 && lines.every( ( line ) => '' === line.trim() || YAML_LINE.test( line ) ) ) {
        return false;
    }

    const inlineMarkup = text.match( INLINE_MARKUP )?.length ?? 0;
    const signalKinds  = [ tightHeadings, listItems, quotes, inlineMarkup ].filter( ( count ) => count > 0 ).length;

    return headings > 0 || listItems > 1 || quotes > 1 || inlineMarkup > 1 || signalKinds > 1;
}

/**
 * Whether converting plain text could produce live markup.
 *
 * This matters because the HTML arriving here is inert: a document showing
 * `<img onerror=…>` or `[a](javascript:…)` as literal text is safe until it
 * converts, and the converter keeps both, so the block's output would run
 * them. Upstream takes the same risk for a plain-text clipboard, but
 * there it's the clipboard that's untrusted, not a document's visible
 * text. Text like this stays literal. Code fences and inline code are
 * exempt because the converter escapes them, which is why this matches
 * its patterns exactly: anything it doesn't treat as code is checked here.
 *
 * @since 1.20.0
 * @since 1.20.0 Exempts inline code (#1065).
 *
 * @param text Plain text from the clipboard.
 */
export function hasUnsafeMarkup( text: string ): boolean {
    const normalized = text.replace( /\r\n?/g, '\n' );

    return UNSAFE_MARKUP.test( withoutCode( normalized ) );
}

/**
 * Text with its code removed, or unchanged when the fences can't be known
 * for certain. The converter tidies whitespace before it looks for fences,
 * so a marker followed by a tab can open a fence for it and not here, and
 * that pairs every later marker differently. A marker left behind means
 * that may have happened.
 */
function withoutCode( text: string ): string {
    const withoutFences = text.replace( CONVERTED_FENCE, '' );

    return LEFTOVER_FENCE.test( withoutFences ) ? text : withoutCodeSpans( withoutFences );
}

/**
 * Text with its inline code removed, or unchanged when the spans can't be
 * known for certain.
 *
 * The converter looks for spans block by block, and this can't see where
 * its blocks end. So a backtick left over on one line could pair with one
 * on another, which shifts every span after it and puts text that looks
 * like code back outside. Spans only count when every line's backticks
 * pair up on that line, because then they're the same spans wherever the
 * blocks fall. An escaped backtick is left over like any other, and link
 * definitions are read before spans, so either keeps the whole text
 * checked.
 */
function withoutCodeSpans( text: string ): string {
    const lines = text.split( '\n' );

    if ( lines.some( ( line ) => LINK_DEFINITION.test( line ) ) ) {
        return text;
    }

    // The stand-in keeps a `<` before a span from passing as text.
    const stripped = lines.map( ( line ) => line.replace( CONVERTED_CODE_SPAN, '$1x' ) );

    return stripped.some( ( line ) => line.includes( '`' ) ) ? text : stripped.join( '\n' );
}

/**
 * The language a code editor said it copied, when it said. Only VS Code
 * does, and only browsers built on Chromium pass its clipboard type on.
 *
 * @since 1.20.0
 *
 * @param data The clipboard.
 */
export function pastedEditorLanguage( data: DataTransfer ): string | undefined {
    try {
        const { mode } = JSON.parse( data.getData( VS_CODE_CLIPBOARD_TYPE ) ) as { mode?: unknown };

        return 'string' === typeof mode ? mode : undefined;
    } catch {
        return undefined;
    }
}

/**
 * Whether upstream would read plain text as LaTeX. This mirrors its
 * `isLatexMathMode()`, which isn't exported: a run of six letters rules
 * LaTeX out, unless it's a command name or a command's argument, and then
 * a command with an argument or any two softer clues rule it in.
 *
 * @since 1.20.0
 *
 * @param text Plain text from the clipboard.
 */
export function readsAsLatex( text: string ): boolean {
    for ( const match of text.matchAll( /[\p{L}\s]+/gu ) ) {
        const previous = text[ match.index - 1 ];
        const run      = '\\' === previous ? match[ 0 ].replace( /^[a-zA-Z]+/, '' ) : match[ 0 ];

        if ( '{' !== previous && run.length >= 6 ) {
            return false;
        }
    }

    if ( /\\[a-zA-Z]+\s*\{/.test( text ) ) {
        return true;
    }

    const clues = [
        text.includes( '^' ) && ! text.startsWith( '^' ),
        /[=+\-/*]/.test( text ),
        /\\[a-zA-Z]+/.test( text ),
    ];

    return clues.filter( Boolean ).length >= 2;
}

/**
 * Whether upstream will read the clipboard's HTML as plain text: no
 * elements once its wrappers are gone, or a lone `<span>` of text.
 */
function upstreamReadsAsPlain( doc: Document ): boolean {
    const { children } = doc.body;
    const onlyBreaks   = Array.from( doc.body.querySelectorAll( '*' ) ).every(
        ( element ) => 'BR' === element.tagName || element === children[ 0 ],
    );

    return onlyBreaks && ( 'SPAN' === children[ 0 ]?.tagName || Array.from( children ).every( ( { tagName } ) => 'BR' === tagName ) );
}

/**
 * Plain text as HTML that keeps upstream away from `core/math` (#1067).
 *
 * Upstream sends LaTeX-looking plain text straight to a hard-coded
 * `core/math` block, including text in a wrapper it ignores, such as the
 * `<meta>` and `<span>` a browser adds. It isn't registered here, so
 * `x^2 + y^2 = z^2` would fail the whole paste. HTML with formatting skips
 * that step, so this hands over the same text as a paragraph.
 */
function plainTextAsParagraph( text: string ): string {
    const holder = document.createElement( 'p' );

    holder.textContent = text;

    return holder.outerHTML.replace( /\r?\n/g, '<br>' );
}

/**
 * Whether the clipboard's HTML only decorates its plain text, so nothing
 * would be lost by converting the plain text instead. Whitespace is
 * ignored because editors put each line in its own element, which leaves
 * no line breaks in the HTML's text.
 */
function isDecoratedPlainText( doc: Document, plainText: string ): boolean {
    const withoutWhitespace = ( text: string ): string => text.replace( /\s+/g, '' ).normalize();

    const hasRealLink = Array.from( doc.querySelectorAll( 'a[href]' ) ).some(
        ( link ) => ! BARE_URL.test( link.textContent ?? '' ),
    );

    return (
        ! hasRealLink &&
        null === doc.querySelector( RENDERED_CONTENT ) &&
        withoutWhitespace( doc.body.textContent ?? '' ) === withoutWhitespace( plainText )
    );
}

/**
 * The language of a syntax-highlighted block from its wrapper's classes:
 * `highlight-source-php`, or `highlight-text-html-php` for a nested scope.
 */
function highlightedCodeLanguage( pre: Element ): string | undefined {
    const scope = HIGHLIGHT_SCOPE.exec( pre.closest( '[class*="highlight"]' )?.className ?? '' );
    const parts = scope?.[ 1 ].split( '-' ) ?? [];

    return parts.reverse().map( normalizeCodeLanguage ).find( Boolean );
}

/**
 * Rewrites highlighted `<pre>` blocks, which have no `<code>` and so would
 * become Preformatted blocks, into the `<pre><code>` the Code block takes.
 *
 * @return The rewritten HTML, or `null` when there's nothing to rewrite.
 */
function withHighlightedCodeAsCode( doc: Document ): string | null {
    const blocks = Array.from( doc.querySelectorAll( HIGHLIGHTED_CODE ) ).filter(
        ( pre ) => null === pre.querySelector( 'code' ),
    );

    for ( const pre of blocks ) {
        const code     = doc.createElement( 'code' );
        const language = highlightedCodeLanguage( pre );

        code.textContent = pre.textContent;

        if ( undefined !== language ) {
            code.className = `language-${ language }`;
        }

        pre.replaceChildren( code );
    }

    return 0 === blocks.length ? null : doc.body.innerHTML;
}

/**
 * What the paste pipeline should read as the clipboard's HTML.
 *
 * @since 1.20.0
 *
 * @param html      The clipboard's `text/html`.
 * @param plainText The clipboard's `text/plain`.
 * @param language  The language the source editor reported, if any.
 *
 * @return The replacement HTML, which is empty when the plain text should
 *         convert as Markdown, or `null` to leave the paste alone.
 */
export function resolvePastedHtml( html: string, plainText: string, language?: string ): string | null {
    if ( html.includes( '<!-- wp:' ) || plainText.includes( '<!-- wp:' ) ) {
        return null;
    }

    const doc = new DOMParser().parseFromString( html, 'text/html' );

    const readsAsMath = undefined === getBlockType( 'core/math' ) && readsAsLatex( plainText );

    if ( readsAsMath && upstreamReadsAsPlain( doc ) ) {
        return plainTextAsParagraph( plainText );
    }

    if ( '' === html ) {
        return null;
    }

    if (
        ( undefined === language || MARKDOWN_LANGUAGES.has( language ) ) &&
        looksLikeMarkdown( plainText ) &&
        ! hasUnsafeMarkup( plainText ) &&
        isDecoratedPlainText( doc, plainText )
    ) {
        return readsAsMath ? plainTextAsParagraph( plainText ) : '';
    }

    return withHighlightedCodeAsCode( doc );
}

/**
 * Serves `html` as the event's HTML from here on. `clipboardData` is an
 * accessor on the event's prototype, so this shadows it on the instance
 * and every later listener reads the replacement.
 */
function replaceClipboardHtml( event: ClipboardEvent, data: DataTransfer, html: string ): void {
    const clipboardData = new Proxy( data, {
        get( target, property ) {
            const value = Reflect.get( target, property );

            if ( 'getData' === property ) {
                return ( format: string ): string =>
                    'text/html' === format ? html : target.getData( format );
            }

            return 'function' === typeof value ? value.bind( target ) : value;
        },
    } );

    Object.defineProperty( event, 'clipboardData', { configurable: true, value: clipboardData } );
}

/**
 * Applies {@link resolvePastedHtml} to a paste event. Content copied from
 * the editor itself, and pastes that carry files, keep their usual path.
 *
 * @since 1.20.0
 *
 * @param event The paste event, before upstream's listeners have seen it.
 */
export function rewritePasteEvent( event: ClipboardEvent ): void {
    const data = event.clipboardData;

    if ( null === data || 'true' === data.getData( 'rich-text' ) || data.files.length > 0 ) {
        return;
    }

    const html = resolvePastedHtml( data.getData( 'text/html' ), data.getData( 'text/plain' ), pastedEditorLanguage( data ) );

    if ( null !== html ) {
        replaceClipboardHtml( event, data, html );
    }
}

/**
 * Watches paste for the canvas it renders in. Render it inside the canvas:
 * its hidden marker locates the window, which for the post editor is the
 * iframe's.
 *
 * @since 1.20.0
 * @since 1.20.0 Offers to import pasted external images (#1066).
 */
export function MarkdownPasteBridge(): ReactElement {
    const markerRef = useRef< HTMLSpanElement >( null );
    const registry  = useRegistry();

    useEffect( () => {
        const marker = markerRef.current;
        const view   = marker?.ownerDocument.defaultView;

        if ( null === marker || null === view || undefined === view ) {
            return undefined;
        }

        const root = marker.closest( IN_TREE_CANVAS_ROOT ) ?? marker.ownerDocument.body;

        const onPaste = ( event: ClipboardEvent ): void => {
            if ( ! root.contains( event.target as Node ) ) {
                return;
            }

            rewritePasteEvent( event );

            const before = new Set< string >( registry.select( blockEditorStore ).getClientIdsWithDescendants() );

            // Upstream's listeners run after this one, in the same task.
            view.setTimeout( () => offerPastedImageImport( registry, before, view.location.origin ), 0 );
        };

        view.addEventListener( 'paste', onPaste, true );

        return () => view.removeEventListener( 'paste', onPaste, true );
    }, [ registry ] );

    return <span ref={ markerRef } hidden />;
}
