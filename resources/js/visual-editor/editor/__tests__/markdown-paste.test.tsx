/**
 * Markdown paste (#1045). Runs the real `pasteHandler` against the
 * registered blocks, so a transform or schema that upstream can't read
 * fails here.
 */

import { render } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { getBlockType, pasteHandler } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';

import { registerArtisanPackBlocks } from '../../blocks';
import {
    looksLikeMarkdown,
    MarkdownPasteBridge,
    hasUnsafeMarkup,
    resolvePastedHtml,
    rewritePasteEvent,
} from '../markdown-paste';
import githubCapture from './fixtures/github-chromium.json';
import { DECORATED_SOURCES, PLAIN_MARKDOWN, RENDERED_SOURCES } from './fixtures/paste-sources';
import type { PasteFixture } from './fixtures/paste-sources';

beforeAll( () => {
    vi.spyOn( window.console, 'log' ).mockImplementation( () => undefined );
    registerArtisanPackBlocks();
} );

/** Runs a payload through the same two steps a real paste takes. */
function paste( { html, plainText }: Pick< PasteFixture, 'html' | 'plainText' >, mode = 'BLOCKS' ): BlockInstance[] {
    const rewritten = resolvePastedHtml( html, plainText );

    return pasteHandler( {
        HTML: rewritten ?? html,
        plainText,
        mode,
    } as Parameters< typeof pasteHandler >[0] ) as BlockInstance[];
}

const names = ( blocks: BlockInstance[] ): string[] => blocks.map( ( { name } ) => name );

function fakeClipboard( data: Record< string, string >, files: File[] = [] ): DataTransfer {
    return {
        files,
        types: Object.keys( data ),
        getData: ( format: string ) => data[ format ] ?? '',
    } as unknown as DataTransfer;
}

function pasteEvent( clipboardData: DataTransfer ): ClipboardEvent {
    const event = new Event( 'paste', { bubbles: true, cancelable: true } );

    Object.defineProperty( event, 'clipboardData', { configurable: true, value: clipboardData } );

    return event as ClipboardEvent;
}

describe( 'looksLikeMarkdown', () => {
    it.each( [
        [ 'a heading and a paragraph', '# Title\n\nSome text.' ],
        [ 'a lone heading', '# Title' ],
        [ 'a closed fence', '```\ncode\n```' ],
        [ 'a closed tilde fence', 'Intro\n~~~js\ncode\n~~~' ],
        [ 'a longer fence around a shorter one', '````\n```\ncode\n````' ],
        [ 'a table', '| a | b |\n| --- | --- |\n| 1 | 2 |' ],
        [ 'a bulleted list', '- one\n- two' ],
        [ 'a numbered list', '1. one\n2. two' ],
        [ 'a nested list', '- one\n  - two' ],
        [ 'a pasted bullet character', '• one\n• two' ],
        [ 'a quote over two lines', '> one\n> two' ],
        [ 'a quote and emphasis', '> A quote\n\nSome **bold** text' ],
        [ 'two pieces of inline markup', 'Some **bold** and a [link](https://example.com)' ],
        [ 'a heading against a list', '## Steps\n- one' ],
        [ 'Windows line endings', '# Title\r\n\r\nSome text.' ],
    ] )( 'accepts %s', ( _label, text ) => {
        expect( looksLikeMarkdown( text ) ).toBe( true );
    } );

    it.each( [
        [ 'an empty string', '' ],
        [ 'ordinary prose', 'Just a sentence.\n\nAnd another one.' ],
        [ 'a stray asterisk', 'Terms apply* to all orders.\n\n* see below' ],
        [ 'arithmetic', '2 * 3 * 4 = 24' ],
        [ 'a lone dash line', '- one thing' ],
        [ 'a lone quote', '> one line' ],
        [ 'a lone bold word', 'Some **bold** text.' ],
        [ 'a lone link', 'See [the docs](https://example.com).' ],
        [ 'exponents', '2**3**4 and x**2**y' ],
        [ 'a bracketed aside', 'array[0](value) and items[1](other)' ],
        [ 'a hash tag', '#general is the channel\n\n#random is the other' ],
        [ 'a shell comment above a command', '# install\nnpm install\n# build\nnpm run build' ],
        [ 'a docblock', '/**\n * One thing\n * Another thing\n */' ],
        [ 'a shebang script', '#!/bin/bash\n\n# Comment\n\necho 1' ],
        [ 'PHP', '<?php\n\n# Comment\n\necho 1;' ],
        [ 'an unclosed fence', '```\nnever closed' ],
        [ 'a fence closed too short', '````\ncode\n```' ],
        [ 'a fence closed by the other marker', '```\ncode\n~~~' ],
        [ 'a pipe with no delimiter row', 'a | b\nc | d' ],
    ] )( 'rejects %s', ( _label, text ) => {
        expect( looksLikeMarkdown( text ) ).toBe( false );
    } );

    it.each( [
        [ 'brackets', '['.repeat( 90_000 ) ],
        [ 'link openers', '[a](/'.repeat( 18_000 ) ],
        [ 'a table delimiter with trailing text', `|-\n${ '|-|-'.repeat( 10 ) }${ ' '.repeat( 90_000 ) }x` ],
    ] )( 'stays fast on a long run of %s', ( _label, text ) => {
        const started = performance.now();

        looksLikeMarkdown( text );

        expect( performance.now() - started ).toBeLessThan( 500 );
    } );

    it( 'skips detection on a very large paste', () => {
        expect( looksLikeMarkdown( `# Title\n\n${ 'x'.repeat( 100_000 ) }` ) ).toBe( false );
    } );

    it( 'ignores markers inside a fence until it closes', () => {
        expect( looksLikeMarkdown( '```\n# not a heading\n\n- a\n- b' ) ).toBe( false );
    } );
} );

describe( 'hasUnsafeMarkup', () => {
    it.each( [
        [ 'an image with a handler', '<img src=x onerror=alert(1)>' ],
        [ 'a math wrapper', 'x <math><mtext><img src=x></mtext></math> y' ],
        [ 'an object', '<object data="javascript:alert(3)"></object>' ],
        [ 'a closing tag', '</style>' ],
        [ 'a comment', '<!-- x -->' ],
        [ 'a tag at the end of the text', 'text <svg' ],
        [ 'a script link', '[c](javascript:alert(2))' ],
        [ 'a mixed-case scheme', '[c](JaVaScRiPt:alert(2))' ],
        [ 'an escaped scheme', '[c](javascript\\:alert(2))' ],
        [ 'a scheme split by a numeric reference', '[c](java&#115;cript:alert(2))' ],
        [ 'a named reference for the colon', '[c](javascript&colon;alert(2))' ],
        [ 'a reference-style link', '[c][1]\n\n[1]: javascript:alert(2)' ],
        [ 'a VBScript link', '[c](vbscript:x)' ],
        [ 'an HTML data link', '[c](data:text/html,x)' ],
        [ 'a tag in an inline code span', '`<img src=x>`' ],
        [ 'a tag after an escaped backtick', '\\`<img src=x>\\`' ],
        [ 'a tag in a fence with a spaced info string', '```x y\n<img src=x>\n```' ],
        [ 'a tag after an unclosed fence', '```\n<img src=x>' ],
        [ 'a tag after a closed fence', '```\ncode\n```\n<img src=x>' ],
        [ 'a tag in a mismatched fence', '```\n<img src=x>\n~~~' ],
    ] )( 'flags %s', ( _label, text ) => {
        expect( hasUnsafeMarkup( text ) ).toBe( true );
    } );

    it.each( [
        [ 'plain Markdown', '# Title\n\n- [a](https://example.com)\n- **b**' ],
        [ 'an image', '![Alt](https://example.com/a.png)' ],
        [ 'an autolink', '<https://example.com>' ],
        [ 'a comparison', 'a < b and c > d' ],
        [ 'markup in a fence', '```html\n<script>alert(1)</script>\n```' ],
        [ 'markup in a tilde fence', '~~~\n<img src=x>\n~~~' ],
        [ 'markup in a fence with Windows line endings', '```html\r\n<img src=x>\r\n```' ],
    ] )( 'allows %s', ( _label, text ) => {
        expect( hasUnsafeMarkup( text ) ).toBe( false );
    } );
} );

describe( 'resolvePastedHtml', () => {
    it.each( DECORATED_SOURCES.map( ( fixture ) => [ fixture.source, fixture ] as const ) )(
        'hands %s over to the plain text',
        ( _source, { html, plainText } ) => {
            expect( resolvePastedHtml( html, plainText ) ).toBe( '' );
        },
    );

    it.each( [
        [ 'a live tag', '# Notes\n\nx <math><mtext><img src=x onerror=alert(1)></mtext></math> y' ],
        [ 'a script link', '# Notes\n\n[c](javascript:alert(2))' ],
    ] )( 'keeps %s from a document as inert text', ( _label, plainText ) => {
        const html = plainText
            .split( '\n' )
            .map( ( line ) => `<div>${ line.replace( /</g, '&lt;' ) }</div>` )
            .join( '' );

        expect( resolvePastedHtml( html, plainText ) ).toBeNull();

        const pasted = paste( { html, plainText } );

        expect( pasted.flatMap( ( { attributes } ) => String( attributes.content ) ).join( '' ) ).not.toMatch(
            /<img|<a /,
        );
    } );

    it( 'converts a document with markup only in a fence', () => {
        const plainText = '# Notes\n\n```html\n<img src=x onerror=alert(1)>\n```';
        const html      = plainText
            .split( '\n' )
            .map( ( line ) => `<div>${ line.replace( /</g, '&lt;' ) }</div>` )
            .join( '' );

        expect( resolvePastedHtml( html, plainText ) ).toBe( '' );
        expect( names( paste( { html, plainText } ) ) ).toEqual( [ 'artisanpack/heading', 'artisanpack/code' ] );
    } );

    it( 'leaves a clipboard with no HTML alone', () => {
        expect( resolvePastedHtml( '', '# Title' ) ).toBeNull();
    } );

    it( 'leaves decorated text that is not Markdown alone', () => {
        expect( resolvePastedHtml( '<div><span>Plain words</span></div>', 'Plain words' ) ).toBeNull();
    } );

    it( 'keeps HTML whose text differs from the plain text', () => {
        expect( resolvePastedHtml( '<div>Title</div><div>Other</div>', '# Title\n\nDifferent' ) ).toBeNull();
    } );

    it( 'keeps rendered content even when its text reads as Markdown', () => {
        expect( resolvePastedHtml( '<h2># Title</h2>', '# Title' ) ).toBeNull();
    } );

    it( 'keeps a rendered code sample of Markdown', () => {
        const plainText = '# Title\n\n- one\n- two';

        expect( resolvePastedHtml( `<pre><code>${ plainText }</code></pre>`, plainText ) ).toBeNull();
    } );

    it( 'keeps serialized blocks for the block parser', () => {
        const html = '<!-- wp:paragraph --><div># Title</div><!-- /wp:paragraph -->';

        expect( resolvePastedHtml( html, '# Title' ) ).toBeNull();
    } );

    it( 'adds a language to a highlighted block with none', () => {
        const html = '<div class="highlight highlight-source-js"><pre><span>let a;</span></pre></div>';

        expect( resolvePastedHtml( html, 'let a;' ) ).toContain(
            '<pre><code class="language-javascript">let a;</code></pre>',
        );
    } );

    it( 'wraps a highlighted block of an unknown language without a class', () => {
        const html = '<div class="highlight highlight-source-cobol"><pre>STOP RUN.</pre></div>';

        expect( resolvePastedHtml( html, 'STOP RUN.' ) ).toContain( '<pre><code>STOP RUN.</code></pre>' );
    } );
} );

describe( 'block mappings', () => {
    describe.each( [
        ...DECORATED_SOURCES.map( ( fixture ) => [ fixture.source, fixture ] as const ),
        [ PLAIN_MARKDOWN.source, PLAIN_MARKDOWN ] as const,
        ...RENDERED_SOURCES.map( ( fixture ) => [ fixture.source, fixture ] as const ),
    ] )( '%s', ( _source, fixture ) => {
        it( 'produces the right blocks', () => {
            const blocks = paste( fixture );

            expect( names( blocks ) ).toEqual( [
                'artisanpack/heading',
                'artisanpack/paragraph',
                'artisanpack/list',
                'artisanpack/quote',
                'artisanpack/code',
            ] );
            expect( blocks[ 0 ].attributes.level ).toBe( 1 );
            expect( String( blocks[ 0 ].attributes.content ) ).toBe( 'Title' );
            expect( String( blocks[ 1 ].attributes.content ) ).toBe( 'Some <strong>bold</strong> text.' );
            expect( names( blocks[ 2 ].innerBlocks ) ).toEqual( [ 'artisanpack/list-item', 'artisanpack/list-item' ] );
            expect( String( blocks[ 3 ].innerBlocks[ 0 ].attributes.content ) ).toBe( 'Quoted' );
            expect( String( blocks[ 4 ].attributes.content ) ).toBe( 'echo 1;' );
            expect( blocks[ 4 ].attributes.language ).toBe( 'php' );
        } );
    } );

    it( 'keeps the language of code copied from a rendered GitHub page', () => {
        const [ code ] = paste( githubCapture );

        expect( code.name ).toBe( 'artisanpack/code' );
        expect( code.attributes.language ).toBe( 'php' );
        expect( String( code.attributes.content ) ).toContain( '$client' );
    } );

    it( 'maps links and inline code inside a paragraph', () => {
        const [ block ] = paste( { html: '', plainText: 'A [link](https://example.com) and `code`.' } );

        expect( block.name ).toBe( 'artisanpack/paragraph' );
        expect( String( block.attributes.content ) ).toBe( 'A <a href="https://example.com">link</a> and <code>code</code>.' );
    } );

    it( 'maps a rule', () => {
        expect( names( paste( { html: '', plainText: 'One\n\n---\n\nTwo' } ) ) ).toContain( 'artisanpack/separator' );
    } );

    it( 'maps an image with its alt text', () => {
        const [ block ] = paste( { html: '', plainText: '![Alt text](https://example.com/a.png)\n\nAfter' } );

        expect( block.name ).toBe( 'artisanpack/image' );
        expect( block.attributes ).toMatchObject( { alt: 'Alt text', url: 'https://example.com/a.png' } );
    } );

    it( 'maps a table', () => {
        expect( names( paste( { html: '', plainText: '| a | b |\n| --- | --- |\n| 1 | 2 |' } ) ) ).toEqual( [
            'artisanpack/table',
        ] );
    } );

    it( 'maps a multi-line quote into the Quote block', () => {
        const [ block ] = paste( { html: '', plainText: '> One **bold**\n>\n> Two' } );

        expect( block.name ).toBe( 'artisanpack/quote' );
        expect( names( block.innerBlocks ) ).toEqual( [ 'artisanpack/paragraph', 'artisanpack/paragraph' ] );
    } );

    it( 'keeps block content nested in a rendered quote', () => {
        const [ block ] = paste( { html: '<blockquote><h2>Heading</h2><ul><li>One</li></ul></blockquote>', plainText: '' } );

        expect( names( block.innerBlocks ) ).toEqual( [ 'artisanpack/heading', 'artisanpack/list' ] );
    } );

    it.each( [
        [ 'php', 'php' ],
        [ 'js', 'javascript' ],
        [ 'TS', 'typescript' ],
        [ 'sh', 'bash' ],
        [ 'yml', 'yaml' ],
        [ 'text', 'plaintext' ],
    ] )( 'maps a %s fence to %s', ( fence, language ) => {
        const [ block ] = paste( { html: '', plainText: `\`\`\`${ fence }\nline one\n  line two\n\`\`\`\n\nAfter` } );

        expect( block.name ).toBe( 'artisanpack/code' );
        expect( block.attributes.language ).toBe( language );
        expect( String( block.attributes.content ) ).toBe( 'line one\n  line two' );
    } );

    it( 'does not keep other classes on code in a Preformatted block', () => {
        const [ block ] = paste( { html: '<pre>Text <code class="other language-php">x</code></pre><p>After</p>', plainText: '' } );

        expect( block.name ).toBe( 'artisanpack/preformatted' );
        expect( String( block.attributes.content ) ).not.toContain( 'other' );
    } );

    it( 'leaves an unknown fence language unset', () => {
        const [ block ] = paste( { html: '', plainText: '```cobol\nSTOP RUN.\n```\n\nAfter' } );

        expect( block.name ).toBe( 'artisanpack/code' );
        expect( block.attributes.language ).toBeUndefined();
    } );

    it( 'leaves a fence with no language unset', () => {
        const [ block ] = paste( { html: '', plainText: '```\ncode\n```\n\nAfter' } );

        expect( block.attributes.language ).toBeUndefined();
    } );

    it( 'keeps code markup as escaped text', () => {
        const [ block ] = paste( { html: '', plainText: '```html\n<script>alert(1)</script>\n```\n\nAfter' } );

        expect( String( block.attributes.content ) ).toBe( '&lt;script&gt;alert(1)&lt;/script&gt;' );
    } );
} );

describe( 'unchanged pastes', () => {
    it( 'leaves ordinary decorated text as a paragraph', () => {
        const html = '<div><span>Terms apply* to all orders.</span></div>';

        const [ block ] = paste( { html, plainText: 'Terms apply* to all orders.' } );

        expect( block.name ).toBe( 'artisanpack/paragraph' );
        expect( String( block.attributes.content ) ).toBe( 'Terms apply* to all orders.' );
    } );

    it( 'leaves rich paste to the HTML', () => {
        const blocks = paste( { html: '<h2>Title</h2><p><em>Rich</em> text</p>', plainText: 'Title\nRich text' } );

        expect( names( blocks ) ).toEqual( [ 'artisanpack/heading', 'artisanpack/paragraph' ] );
        expect( String( blocks[ 1 ].attributes.content ) ).toBe( '<em>Rich</em> text' );
    } );

    it( 'pastes a single decorated line inline', () => {
        const plainText = 'Some **bold** and a [link](https://example.com)';
        const content   = paste( { html: `<div><span>${ plainText }</span></div>`, plainText }, 'AUTO' );

        expect( content ).toBe( 'Some <strong>bold</strong> and a <a href="https://example.com">link</a>' );
    } );
} );

describe( 'the core/html fallback', () => {
    const TAGS = [
        'address', 'article', 'aside', 'audio', 'canvas', 'caption', 'center', 'dd', 'del', 'details',
        'dialog', 'div', 'dl', 'dt', 'embed', 'fieldset', 'figcaption', 'figure', 'font', 'footer',
        'form', 'header', 'hgroup', 'iframe', 'input', 'ins', 'label', 'legend', 'main', 'map',
        'mark', 'marquee', 'math', 'menu', 'meter', 'nav', 'noscript', 'object', 'optgroup', 'option',
        'output', 'picture', 'progress', 'ruby', 'script', 'search', 'section', 'select', 'slot',
        'source', 'style', 'summary', 'svg', 'template', 'textarea', 'track', 'unknown-tag', 'video',
    ];

    it( 'is not registered, so nothing may fall through to it', () => {
        expect( getBlockType( 'core/html' ) ).toBeUndefined();
    } );

    it.each( TAGS )( 'a pasted <%s> never reaches it', ( tag ) => {
        const html = `<${ tag }>Text</${ tag }><figure><${ tag }>Text</${ tag }></figure><p>After</p>`;

        const blocks = paste( { html, plainText: '' } );

        expect( names( blocks ).every( ( name ) => name.startsWith( 'artisanpack/' ) ) ).toBe( true );
    } );
} );

describe( 'rewritePasteEvent', () => {
    const decorated = { 'text/html': '<div># Title</div>', 'text/plain': '# Title' };

    it( 'hides the HTML from later listeners and keeps everything else', () => {
        const file  = fakeClipboard( { ...decorated, 'custom/type': 'kept' } );
        const event = pasteEvent( file );

        rewritePasteEvent( event );

        expect( event.clipboardData?.getData( 'text/html' ) ).toBe( '' );
        expect( event.clipboardData?.getData( 'text/plain' ) ).toBe( '# Title' );
        expect( event.clipboardData?.getData( 'custom/type' ) ).toBe( 'kept' );
        expect( event.clipboardData?.types ).toEqual( file.types );
    } );

    it( 'does not cancel the paste', () => {
        const event = pasteEvent( fakeClipboard( decorated ) );

        rewritePasteEvent( event );

        expect( event.defaultPrevented ).toBe( false );
    } );

    it( 'leaves text copied from the editor literal', () => {
        const clipboard = fakeClipboard( { ...decorated, 'rich-text': 'true' } );
        const event     = pasteEvent( clipboard );

        rewritePasteEvent( event );

        expect( event.clipboardData ).toBe( clipboard );
    } );

    it( 'leaves a paste with files alone', () => {
        const clipboard = fakeClipboard( decorated, [ new File( [ 'x' ], 'a.png', { type: 'image/png' } ) ] );
        const event     = pasteEvent( clipboard );

        rewritePasteEvent( event );

        expect( event.clipboardData ).toBe( clipboard );
    } );

    it( 'tolerates an event with no clipboard data', () => {
        const event = new Event( 'paste' ) as ClipboardEvent;

        Object.defineProperty( event, 'clipboardData', { value: null } );

        expect( () => rewritePasteEvent( event ) ).not.toThrow();
    } );
} );

describe( 'MarkdownPasteBridge', () => {
    const decorated = fakeClipboard( { 'text/html': '<div># Title</div>', 'text/plain': '# Title' } );

    it( 'rewrites the event before a bubbling listener reads it', () => {
        const { container } = render(
            <div className="editor-styles-wrapper">
                <MarkdownPasteBridge />
                <p contentEditable suppressContentEditableWarning>Text</p>
            </div>
        );
        const seen: string[] = [];
        const listener       = ( event: Event ): void => {
            seen.push( ( event as ClipboardEvent ).clipboardData?.getData( 'text/html' ) ?? 'none' );
        };

        document.addEventListener( 'paste', listener );
        container.querySelector( 'p' )?.dispatchEvent( pasteEvent( decorated ) );
        document.removeEventListener( 'paste', listener );

        expect( seen ).toEqual( [ '' ] );
    } );

    it( 'ignores a paste outside its canvas', () => {
        const { container } = render(
            <div>
                <div className="editor-styles-wrapper"><MarkdownPasteBridge /></div>
                <input aria-label="Outside" />
            </div>
        );
        const event = pasteEvent( decorated );

        container.querySelector( 'input' )?.dispatchEvent( event );

        expect( event.clipboardData ).toBe( decorated );
    } );

    it( 'stops listening when it unmounts', () => {
        const { container, unmount } = render(
            <div className="editor-styles-wrapper"><MarkdownPasteBridge /></div>
        );
        const target = container.querySelector( '.editor-styles-wrapper' ) as Element;

        unmount();
        document.body.appendChild( target );

        const event = pasteEvent( decorated );

        target.dispatchEvent( event );
        target.remove();

        expect( event.clipboardData ).toBe( decorated );
    } );
} );
