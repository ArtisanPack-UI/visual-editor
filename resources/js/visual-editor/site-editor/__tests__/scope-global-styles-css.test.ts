/**
 * #679 — the site editor mounts inline, so the global-styles emitter's
 * `:root` rules land on the host document's `<html>`. These tests pin
 * the rewrite that scopes them to the canvas surface instead.
 */

import { describe, expect, it } from 'vitest';

import {
    CANVAS_SCOPE_SELECTOR,
    scopeGlobalStylesCss,
} from '../scope-global-styles-css';

describe('scopeGlobalStylesCss', () => {
    it('rewrites a root rule carrying theme padding to the canvas scope', () => {
        const css = ':root {\n    padding: 2rem 1.5rem;\n}';

        const scoped = scopeGlobalStylesCss(css);

        expect(scoped).toBe(
            `${CANVAS_SCOPE_SELECTOR} {\n    padding: 2rem 1.5rem;\n}`
        );
        expect(scoped).not.toContain(':root');
    });

    it('keeps preset tokens resolvable by moving them to the canvas scope', () => {
        const css =
            ':root { --wp--preset--color--primary: #2563eb; }\n' +
            '.wp-block-button { color: var(--wp--preset--color--primary); }';

        expect(scopeGlobalStylesCss(css)).toBe(
            `${CANVAS_SCOPE_SELECTOR} { --wp--preset--color--primary: #2563eb; }\n` +
                '.wp-block-button { color: var(--wp--preset--color--primary); }'
        );
    });

    it('rewrites every occurrence, including inside at-rules and selector lists', () => {
        const css =
            '@media (min-width: 600px) { :root { padding: 3rem; } }\n' +
            ':root, .wp-site-blocks { margin: 0; }\n' +
            ':root .wp-block-group { gap: 1rem; }';

        const scoped = scopeGlobalStylesCss(css);

        expect(scoped).toBe(
            `@media (min-width: 600px) { ${CANVAS_SCOPE_SELECTOR} { padding: 3rem; } }\n` +
                `${CANVAS_SCOPE_SELECTOR}, .wp-site-blocks { margin: 0; }\n` +
                `${CANVAS_SCOPE_SELECTOR} .wp-block-group { gap: 1rem; }`
        );
    });

    it('leaves `:root` mentions inside comments and strings alone', () => {
        const css =
            '/* tokens declared on :root by the emitter */\n' +
            '.wp-block-note::before { content: ":root"; }\n' +
            ".wp-block-hero { background: url('/img/:root.png'); }";

        expect(scopeGlobalStylesCss(css)).toBe(css);
    });

    it('leaves an unquoted data URI carrying its own `:root` styles alone', () => {
        const css =
            '.wp-block-hero { background: url(data:image/svg+xml,<svg><style>:root{fill:red}</style></svg>); }';

        expect(scopeGlobalStylesCss(css)).toBe(css);
    });

    it('still rewrites selectors that follow a url() value', () => {
        const css =
            '.a { background: url(/img/a.png); }\n:root { padding: 1rem; }';

        expect(scopeGlobalStylesCss(css)).toBe(
            `.a { background: url(/img/a.png); }\n${CANVAS_SCOPE_SELECTOR} { padding: 1rem; }`
        );
    });

    it('handles a quoted url() containing a closing paren', () => {
        const css =
            '.a { background: url( "/img/a(1).png" ); }\n:root { padding: 1rem; }';

        expect(scopeGlobalStylesCss(css)).toBe(
            `.a { background: url( "/img/a(1).png" ); }\n${CANVAS_SCOPE_SELECTOR} { padding: 1rem; }`
        );
    });

    it('does not touch identifiers that merely start with `:root`', () => {
        const css = ':rooted { color: red; }';

        expect(scopeGlobalStylesCss(css)).toBe(css);
    });

    it('scopes a compound `:root.dark` to an html ancestor so dark tokens still reach the canvas', () => {
        const css = ':root.dark { --wp--preset--color--bg: #0f172a; }';

        expect(scopeGlobalStylesCss(css)).toBe(
            `:where(html.dark) ${CANVAS_SCOPE_SELECTOR} { --wp--preset--color--bg: #0f172a; }`
        );
    });

    it('scopes a compound `:root[data-theme="dark"]` including a quoted attribute selector', () => {
        const css = ':root[data-theme="dark"] { color: white; }';

        expect(scopeGlobalStylesCss(css)).toBe(
            `:where(html[data-theme="dark"]) ${CANVAS_SCOPE_SELECTOR} { color: white; }`
        );
    });

    it('matches `:root` case-insensitively', () => {
        const css = ':ROOT { padding: 1rem; }';

        expect(scopeGlobalStylesCss(css)).toBe(`${CANVAS_SCOPE_SELECTOR} { padding: 1rem; }`);
    });

    it('does not treat a `url(` inside a longer ident as a url() value', () => {
        // `blur(` must not start the url() skipper, so a following `:root`
        // is still rewritten.
        const css = '.a { filter: blur(2px); }\n:root { padding: 1rem; }';

        expect(scopeGlobalStylesCss(css)).toBe(
            `.a { filter: blur(2px); }\n${CANVAS_SCOPE_SELECTOR} { padding: 1rem; }`
        );
    });

    it('honours an escaped paren inside an unquoted url()', () => {
        const css = '.a { background: url(/img/a\\).png); }\n:root { padding: 1rem; }';

        expect(scopeGlobalStylesCss(css)).toBe(
            `.a { background: url(/img/a\\).png); }\n${CANVAS_SCOPE_SELECTOR} { padding: 1rem; }`
        );
    });

    it('returns empty CSS untouched', () => {
        expect(scopeGlobalStylesCss('')).toBe('');
    });

    it('tolerates an unterminated string without dropping input', () => {
        const css = '.a { content: "unterminated';

        expect(scopeGlobalStylesCss(css)).toBe(css);
    });

    it('tolerates an unterminated comment without dropping input', () => {
        const css = '.a { color: red; } /* trailing :root note';

        expect(scopeGlobalStylesCss(css)).toBe(css);
    });
});
