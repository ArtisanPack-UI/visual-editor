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
        });

        expect(result?.css).toBe(
            `.${result?.className}{opacity:0.8;overflow-x:hidden;overflow-y:auto;` +
                '-webkit-backdrop-filter:blur(12px) saturate(180%);backdrop-filter:blur(12px) saturate(180%);' +
                'mix-blend-mode:multiply;isolation:isolate;z-index:5;clip-path:circle(50% at 50% 50%);' +
                '-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}' +
                `:where(.${result?.className}){position:relative}`
        );
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
    ])('rejects %j', (input) => {
        expect(impl.sanitizeCustomCss(input)).toBeNull();
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
