/**
 * Generic responsive overrides (#886) and heading anchors / preset
 * classes (#888) — pins the React and Vue twins of
 * `BlockSupports::compileResponsive()` and `TocResolver` to the PHP
 * behaviour. Scope classes are pinned to
 * `substr( hash( 'xxh3', json_encode( $payload ) ), 0, 10 )`.
 */

import { describe, expect, it } from 'vitest';

import * as reactAnchors from '../src/support/headingAnchors';
import * as reactPresets from '../src/support/presetClasses';
import * as reactResponsive from '../src/support/responsive';
import * as vueAnchors from '../../visual-editor-renderer-vue/src/support/headingAnchors';
import * as vuePresets from '../../visual-editor-renderer-vue/src/support/presetClasses';
import * as vueResponsive from '../../visual-editor-renderer-vue/src/support/responsive';
import type { Block } from '../src/types';

function block(name: string, attributes: Record<string, unknown>, innerBlocks: Block[] = []): Block {
    return { name, attributes, innerBlocks } as unknown as Block;
}

describe.each([
    ['react', reactResponsive],
    ['vue', vueResponsive],
])('%s compileResponsive', (_name, impl) => {
    it('hashes the payload like PHP json_encode', () => {
        expect(impl.compileResponsive({ responsive: { 'style.spacing.padding': { md: '2rem' } } })?.className).toBe(
            've-r-e22d713cfb'
        );
    });

    // Same cases as the Blade `BlockSupportsResponsiveTest` R-13 dataset.
    it.each([
        [1e-8, '0px'],
        ['0.00001', '0px'],
        [0.00009999, '0.0001px'],
        [12.5, '12.5px'],
        [0.03125, '0.03125px'],
        [1e14, '100000000000000px'],
        ['40', '40px'],
    ])('never writes a unit value %s in exponent notation (R-13)', (value, expected) => {
        const css = impl.compileResponsive({ responsive: { height: { md: value } } })?.css ?? '';

        expect(css).toContain(`{height:${expected}!important}`);
        expect(css).not.toMatch(/\d[eE][+-]?\d/);
    });

    describe('legacy keys (#918)', () => {
        const scopeOf = (css: string): string => /\.(ve-r-[0-9a-f]+)\./.exec(css)?.[1] ?? '';

        it('renders a gap axis saved under a deeper key', () => {
            const scope = impl.compileResponsive(
                {
                    responsive: {
                        'style.spacing.blockGap.left': { tablet: '3em' },
                        'style.spacing.blockGap.top': { tablet: '1em', mobile: '0' },
                    },
                },
                impl.RESPONSIVE_CSS_PROPERTY_MAP,
                true
            );
            const selector = `.${scope?.className}.${scope?.className}.${scope?.className}`;

            expect(scope?.css).toContain(
                `@media (max-width:1023px){${selector}{row-gap:1em!important;column-gap:3em!important;--ap-columns-gap:3em!important}}`
            );
            expect(scope?.css).toContain(`@media (max-width:767px){${selector}{row-gap:0!important}}`);
        });

        it('renders padding and margin sides saved under deeper keys', () => {
            const css =
                impl.compileResponsive({
                    responsive: {
                        'style.spacing.padding.top': { tablet: '2rem' },
                        'style.spacing.margin.bottom': { mobile: '1rem' },
                    },
                })?.css ?? '';

            expect(css).toContain('{padding-top:2rem!important}');
            expect(css).toContain('{margin-bottom:1rem!important}');
        });

        it('splits a shallow spacing object into its properties', () => {
            const css =
                impl.compileResponsive({
                    responsive: {
                        'style.spacing': { tablet: { padding: '2rem', blockGap: { left: '3em' }, other: 'x' } },
                    },
                })?.css ?? '';

            expect(css).toContain('{padding:2rem!important}');
            expect(css).toContain('{column-gap:3em!important}');
            expect(css).not.toContain('other');
        });

        it('prefers the property path over a legacy key at the same breakpoint', () => {
            const css =
                impl.compileResponsive({
                    responsive: {
                        'style.spacing.blockGap': { tablet: { top: '1em' } },
                        'style.spacing.blockGap.left': { tablet: '3em', mobile: '4em' },
                    },
                })?.css ?? '';
            const scope = scopeOf(css);

            expect(css).toContain(`@media (max-width:1023px){.${scope}.${scope}.${scope}{row-gap:1em!important}}`);
            expect(css).toContain('{column-gap:4em!important}');
            expect(css).not.toContain('column-gap:3em');
        });

        it('spreads a scalar shallow value under a later side', () => {
            const css =
                impl.compileResponsive({
                    responsive: {
                        'style.spacing': { tablet: { padding: '2rem' } },
                        'style.spacing.padding.top': { tablet: '5rem' },
                    },
                })?.css ?? '';

            expect(css).toContain(
                '{padding-top:5rem!important;padding-right:2rem!important;padding-bottom:2rem!important;padding-left:2rem!important}'
            );
        });

        it('emits per-corner radius longhands', () => {
            const css =
                impl.compileResponsive({
                    responsive: {
                        'style.border.radius.topLeft': { tablet: '8px' },
                        'style.border.radius': { mobile: { bottomRight: '2px' } },
                    },
                })?.css ?? '';

            expect(css).toContain('{border-top-left-radius:8px!important}');
            expect(css).toContain('{border-bottom-right-radius:2px!important}');
        });
    });

    it('escapes slashes and non-ASCII before hashing', () => {
        const scope = impl.compileResponsive({
            responsive: {
                'style.spacing.margin': { mobile: 'calc(1rem/2)' },
                'style.border.color': { tablet: 'café' },
            },
        });

        expect(scope?.className).toBe('ve-r-906cc565a4');
        // `é` is outside the CSS-value whitelist, so it is stripped.
        expect(scope?.css).toContain('{border-color:caf!important}');
    });

    it('encodes an empty override map as a PHP list', () => {
        const scope = impl.compileResponsive({
            responsive: { 'style.spacing.padding': { mobile: { top: '1px' } }, width: [] },
        });

        expect(scope?.className).toBe('ve-r-c0b61df8c1');
    });

    it('folds the base value and unit into the hash for a unit-only override', () => {
        const scope = impl.compileResponsive({
            minHeight: 50,
            minHeightUnit: 'px',
            responsive: { minHeightUnit: { mobile: 'vh' } },
        });

        expect(scope?.className).toBe('ve-r-ef0150dcc3');
        expect(scope?.css).toBe(
            '@media (max-width:767px){.ve-r-ef0150dcc3.ve-r-ef0150dcc3.ve-r-ef0150dcc3{min-height:50vh!important}}'
        );
    });

    it('emits base, legacy and device rules in registry order', () => {
        const scope = impl.compileResponsive({
            responsive: { 'style.border.radius': { mobile: '2px', base: '8px', md: '4px', tablet: '3px' } },
        });
        const selector = `.${scope?.className}.${scope?.className}.${scope?.className}`;

        expect(scope?.css).toBe(
            `${selector}{border-radius:8px!important}` +
                `@media (min-width:768px){${selector}{border-radius:4px!important}}` +
                `@media (max-width:1023px){${selector}{border-radius:3px!important}}` +
                `@media (max-width:767px){${selector}{border-radius:2px!important}}`
        );
    });

    it('lets a later-stored twin win at the same breakpoint, but never a tombstone', () => {
        const laterWins = impl.compileResponsive({
            responsive: { 'style.typography.fontSize': { mobile: '1rem' }, fontSize: { mobile: 'small' } },
        });
        const tombstone = impl.compileResponsive({
            responsive: { fontSize: { mobile: 'small' }, 'style.typography.fontSize': { mobile: false } },
        });

        expect(laterWins?.css).toContain('{font-size:var(--wp--preset--font-size--small)!important}');
        expect(laterWins?.css).not.toContain('1rem');
        expect(tombstone?.css).toContain('{font-size:var(--wp--preset--font-size--small)!important}');
    });

    it('treats numeric spacer heights as pixels and drops hostile values', () => {
        expect(impl.compileResponsive({ responsive: { height: { mobile: 40 } } })?.css).toContain('{height:40px!important}');
        expect(impl.compileResponsive({ responsive: { 'style.spacing.margin': { mobile: '1rem /* x' } } })).toBeNull();
        expect(impl.compileResponsive({ responsive: { 'style.spacing.margin': { mobile: 'calc(1rem' } } })).toBeNull();
        expect(
            impl.compileResponsive({ responsive: { 'style.spacing.margin': { mobile: '1rem}</style><script>' } } })?.css
        ).toContain('{margin:1rem/stylescript!important}');
    });

    it('ignores unmapped paths and empty payloads', () => {
        expect(impl.compileResponsive({ responsive: { columnCount: { mobile: 1 } } })).toBeNull();
        expect(impl.compileResponsive({ responsive: {} })).toBeNull();
        expect(impl.compileResponsive({})).toBeNull();
    });

    it('stamps the scope onto className, dedupes rules and owns the spacer side channel', () => {
        const responsive = { 'style.spacing.padding': { mobile: '1rem' } };
        const { tree, css } = impl.stampResponsiveScopes([
            block('core/paragraph', { className: 'lead', responsive }),
            block('core/paragraph', { responsive }),
            block('core/spacer', { _veSpacerWidthScope: 'injected', responsive: { width: { mobile: '24px' } } }),
            block('core/paragraph', { _veSpacerWidthScope: 'injected' }),
        ]);
        const attrs = tree.map((node) => node.attributes as Record<string, unknown>);
        const scope = impl.compileResponsive({ responsive });

        expect(attrs[0].className).toBe(`lead ${scope?.className}`);
        expect(attrs[1].className).toBe(scope?.className);
        expect(css.split(scope?.className ?? '').length - 1).toBe(3);
        expect(attrs[2]._veSpacerWidthScope).toMatch(/^ve-r-[0-9a-f]{10}$/);
        expect(attrs[3]).not.toHaveProperty('_veSpacerWidthScope');
    });

    it('maps a per-axis blockGap override to row-gap / column-gap, not gap-top (#914)', () => {
        const css = impl.compileResponsive({
            responsive: { 'style.spacing.blockGap': { md: { top: '1rem', left: '2rem' } } },
        })?.css;

        expect(css).toContain('{row-gap:1rem!important;column-gap:2rem!important}');
        expect(css).not.toMatch(/gap-(top|left)|--ap-columns-gap/);
    });

    it.each([
        ['scalar', '2rem', 'gap:2rem!important;--ap-columns-gap:2rem!important'],
        [
            'per-axis',
            { top: '1rem', left: '3rem' },
            'row-gap:1rem!important;column-gap:3rem!important;--ap-columns-gap:3rem!important',
        ],
        [
            'preset',
            'var:preset|spacing|40',
            'gap:var(--wp--preset--spacing--40)!important;--ap-columns-gap:var(--wp--preset--spacing--40)!important',
        ],
        ['unitless zero', { left: '0' }, 'column-gap:0!important;--ap-columns-gap:0px!important'],
        ['normal', 'Normal', 'gap:Normal!important;--ap-columns-gap:0px!important'],
        ['keyword', 'inherit', 'gap:inherit!important'],
        ['top-only object', { top: '1rem' }, 'row-gap:1rem!important'],
    ])('keeps --ap-columns-gap in sync with a Columns block\'s %s responsive gap (#914)', (_label, gap, declarations) => {
        const scope = impl.compileResponsive(
            { responsive: { 'style.spacing.blockGap': { tablet: gap } } },
            impl.RESPONSIVE_CSS_PROPERTY_MAP,
            true
        );
        const selector = `.${scope?.className}.${scope?.className}.${scope?.className}`;

        expect(scope?.css).toBe(`@media (max-width:1023px){${selector}{${declarations}}}`);
    });

    it('stamps --ap-columns-gap on Columns only, under its own scope (#914)', () => {
        const responsive = { 'style.spacing.blockGap': { md: '2rem' } };
        const { tree, css } = impl.stampResponsiveScopes([
            block('core/group', { responsive }),
            block('core/columns', { responsive }),
            block('artisanpack/columns', { responsive }),
        ]);
        const [group, columns, apColumns] = tree.map((node) => (node.attributes as Record<string, unknown>).className);

        expect(columns).not.toBe(group);
        expect(apColumns).toBe(columns);
        expect(css).toContain(`.${group}{gap:2rem!important}`);
        expect(css).toContain(`.${columns}{gap:2rem!important;--ap-columns-gap:2rem!important}`);
    });

    it('leaves a Columns scope unchanged when no gap is overridden (#914)', () => {
        const attributes = { responsive: { 'style.spacing.padding': { md: '2rem' } } };

        expect(impl.compileResponsive(attributes, impl.RESPONSIVE_CSS_PROPERTY_MAP, true)).toEqual(
            impl.compileResponsive(attributes)
        );
    });
});

describe.each([
    ['react', reactAnchors],
    ['vue', vueAnchors],
])('%s heading anchors', (_name, impl) => {
    it('slugs heading text like TocResolver', () => {
        expect(impl.headingSlug('Café &amp; Crème&nbsp;Brûlée')).toBe('cafe-creme-brulee');
        expect(impl.headingSlug('<em>Hello</em>, World! &#8212; 2026')).toBe('hello-world-2026');
        expect(impl.headingSlug('Привет мир')).toBe('heading');
        expect(impl.headingSlug('<br>')).toBe('');
        expect(impl.headingSlug('Cr&egrave;me &frac12; &AElig;on &bigstar;')).toBe('creme-on-bigstar');
    });

    it('stamps unique anchors in document order, honouring author anchors', () => {
        const tree = impl.stampHeadingAnchors([
            block('core/heading', { content: 'Intro' }),
            block('core/group', {}, [
                block('artisanpack/heading', { content: 'Other', anchor: ' intro-1 ' }),
                block('core/heading', { content: 'Intro' }),
            ]),
            block('core/heading', { content: '' }),
            block('core/paragraph', { content: 'Intro' }),
        ]);
        const anchorOf = (node: Block): unknown => (node.attributes as Record<string, unknown>).anchor;
        const inner = tree[1].innerBlocks as Block[];

        expect(anchorOf(tree[0])).toBe('intro');
        expect(anchorOf(inner[0])).toBe(' intro-1 ');
        expect(anchorOf(inner[1])).toBe('intro-2');
        expect(anchorOf(tree[2])).toBeUndefined();
        expect(anchorOf(tree[3])).toBeUndefined();
    });

    it('claims the rendered author id, and replaces one that sanitizes to nothing', () => {
        const tree = impl.stampHeadingAnchors([
            block('core/heading', { content: 'Author', anchor: 'foo bar' }),
            block('core/heading', { content: 'Foobar' }),
            block('core/heading', { content: 'Quoted', anchor: '"<>"' }),
        ]);
        const anchors = tree.map((node) => (node.attributes as Record<string, unknown>).anchor);

        expect(anchors).toEqual(['foo bar', 'foobar-1', 'quoted']);
    });

    it('sanitizes the rendered id', () => {
        expect(impl.resolveAnchor('  bad" id="x y<z>  ')).toBe('badidxyz');
        expect(impl.resolveAnchor(42)).toBe('42');
        expect(impl.resolveAnchor('"<>')).toBeUndefined();
        expect(impl.resolveAnchor(null)).toBeUndefined();
    });
});

describe.each([
    ['react', reactPresets],
    ['vue', vuePresets],
])('%s preset classes', (_name, impl) => {
    it('emits the preset classes in Blade order, deduped', () => {
        expect(
            impl.presetClasses({
                fontFamily: 'Body Serif',
                fontSize: 'x-large',
                textColor: 'text',
                gradient: 'sunset',
                backgroundColor: ' Base ',
            })
        ).toEqual([
            'has-base-background-color',
            'has-background',
            'has-sunset-gradient-background',
            'has-text-color',
            'has-text-color-preset',
            'has-x-large-font-size',
            'has-defined-font-size',
            'has-body-serif-font-family',
        ]);
    });

    it('ignores custom values and non-string slugs', () => {
        expect(impl.presetClasses({ style: { color: { text: '#fff' } }, textColor: { slug: 'x' } })).toEqual([]);
    });
});
