/**
 * Duotone (F-4, F-2, F-8) and background (F-4) support twins.
 *
 * Every expectation is the Blade renderer's output for the same input
 * (`DuotoneSupport`, `BlockSupports::applyBackground()`), and each module
 * runs from both the React and the Vue package, which ship byte-identical
 * copies.
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import * as reactBackground from '../src/support/background';
import * as reactDuotone from '../src/support/duotone';
import * as reactNumber from '../src/support/phpNumberFormat';
import * as vueBackground from '../../visual-editor-renderer-vue/src/support/background';
import * as vueDuotone from '../../visual-editor-renderer-vue/src/support/duotone';
import * as vueNumber from '../../visual-editor-renderer-vue/src/support/phpNumberFormat';

const packages = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

const duotone = (value: unknown): Record<string, unknown> => ({ style: { color: { duotone: value } } });

describe('React and Vue ship identical support twins', () => {
    it.each(['duotone.ts', 'background.ts', 'phpNumberFormat.ts', 'cssNamedColors.ts', 'cssNumber.ts', 'urlSanitizer.ts'])(
        '%s',
        (file) => {
            const read = (renderer: string): string =>
                readFileSync(resolve(packages, `visual-editor-renderer-${renderer}/src/support/${file}`), 'utf8');

            expect(read('vue')).toBe(read('react'));
        }
    );
});

describe.each([
    ['react', reactDuotone, reactBackground, reactNumber],
    ['vue', vueDuotone, vueBackground, vueNumber],
])('%s', (_renderer, duo, bg, num) => {
    describe('compileDuotone', () => {
        it('compiles a custom pair like DuotoneSupport::compile()', () => {
            const compiled = duo.compileDuotone(duotone(['#000000', '#ffffff']));

            expect(compiled.class).toMatch(/^wp-duotone-[0-9a-f]{10}$/);
            expect(compiled.rules).toBe(`.${compiled.class} img{filter:url(#${compiled.class}) !important}`);
            expect(compiled.filter).toContain(`<filter id="${compiled.class}">`);
            expect(compiled.filter).toContain('<feFuncR type="table" tableValues="0 1"/>');
        });

        it('hashes the stop list the way PHP does, `/` escaping included', () => {
            // `substr( hash( 'xxh3', json_encode( $colors ) ), 0, 10 )` in PHP.
            expect(duo.compileDuotone(duotone(['#000000', '#ff0000'])).class).toBe('wp-duotone-48f9e13346');
            expect(duo.compileDuotone(duotone(['hsl(240 100% 50% / 50%)', 'rgba(255, 255, 0, 0.25)', 'White'])).class).toBe(
                'wp-duotone-079e577d66'
            );
        });

        it('parses hex, rgb(), hsl() and named colors (F-8)', () => {
            const compiled = duo.compileDuotone(
                duotone([
                    '#f00',
                    'rgba(0, 0, 255, 0.5)',
                    '#00ff0080',
                    'hsl(0, 100%, 50%)',
                    'hsla(240, 100%, 50%, .5)',
                    'hsl(0.5turn 100% 50% / 25%)',
                    'HSL(30deg 50% 60%)',
                    'hsl(-120, 100%, 50%)',
                    'hsl(200grad 100% 50%)',
                    'RebeccaPurple',
                    'transparent',
                ])
            );

            expect(compiled.filter).toContain(
                '<feFuncR type="table" tableValues="1 0 0 1 0 0 0.8 0 0 0.4 0"/>'
            );
            expect(compiled.filter).toContain(
                '<feFuncG type="table" tableValues="0 0 1 0 0 1 0.6 0 1 0.2 0"/>'
            );
            expect(compiled.filter).toContain(
                '<feFuncB type="table" tableValues="0 1 0 0 1 1 0.4 1 1 0.6 0"/>'
            );
            expect(compiled.filter).toContain(
                '<feFuncA type="table" tableValues="1 0.5 0.502 1 0.5 0.25 1 1 1 1 0"/>'
            );
        });

        it.each([
            ['unknown name', ['black', 'notacolor']],
            ['currentcolor', ['currentColor', 'white']],
            ['hsl without %', ['hsl(0 100 50)', '#fff']],
            ['hsl injection', ['hsl(0, 100%, 50%) url(x)', '#fff']],
            ['markup', ['#000', '"/><script>alert(1)</script>']],
            ['unicode space', ['#000', ' #fff']],
            ['single stop', ['#000']],
            ['not a duotone', 'linear-gradient(red, blue)'],
            ['missing', undefined],
        ])('emits nothing for %s', (_label, value) => {
            expect(duo.compileDuotone(duotone(value)).class).toBe('');
        });

        it('turns the filter off for unset and scopes every target part', () => {
            expect(duo.compileDuotone(duotone('unset'), duo.DUOTONE_TARGETS['core/cover'])).toEqual({
                class: 'wp-duotone-unset',
                rules: '.wp-duotone-unset > .wp-block-cover__image-background,.wp-duotone-unset > .wp-block-cover__video-background{filter:none !important}',
                filterId: '',
                filter: '',
            });
        });

        it('resolves presets, flat or origin-keyed, theme first', () => {
            const flat = [{ slug: 'midnight', colors: ['#000', '#f00'] }];
            const keyed = { default: [{ slug: 'midnight', colors: ['#fff', '#000'] }], theme: flat };

            for (const presets of [flat, keyed]) {
                const compiled = duo.compileDuotone(duotone('var(--wp--preset--duotone--midnight)'), 'img', presets);

                expect(compiled.class).toBe('wp-duotone-midnight');
                expect(compiled.filter).toContain('<feFuncR type="table" tableValues="0 1"/>');
            }

            expect(duo.compileDuotone(duotone('var:preset|duotone|midnight')).class).toBe('');
        });
    });

    describe('stampDuotoneScopes', () => {
        it('stamps blocks whose media renders, in Blade render order, once per filter', () => {
            const tree = [
                {
                    clientId: 'cover',
                    name: 'artisanpack/cover',
                    attributes: { url: '/c.jpg', ...duotone(['#000', '#fff']) },
                    innerBlocks: [
                        { clientId: 'a', name: 'core/image', attributes: { url: '/a.jpg', ...duotone(['#111', '#eee']) }, innerBlocks: [] },
                        { clientId: 'b', name: 'core/image', attributes: { url: '/b.jpg', ...duotone(['#111', '#eee']) }, innerBlocks: [] },
                        { clientId: 'c', name: 'core/image', attributes: duotone(['#111', '#eee']), innerBlocks: [] },
                    ],
                },
                { clientId: 'f', name: 'core/post-featured-image', attributes: duotone(['#000', '#fff']), innerBlocks: [] },
                { clientId: 'p', name: 'core/paragraph', attributes: duotone(['#000', '#fff']), innerBlocks: [] },
            ];

            const result = duo.stampDuotoneScopes(tree as never);
            const stamped = result.tree as Array<{ attributes: Record<string, unknown>; innerBlocks: Array<{ attributes: Record<string, unknown> }> }>;

            const inner = duo.compileDuotone(duotone(['#111', '#eee'])).class;
            const outer = duo.compileDuotone(duotone(['#000', '#fff'])).class;

            expect(stamped[0].attributes._veDuotone).toBe(outer);
            expect(stamped[0].innerBlocks[0].attributes._veDuotone).toBe(inner);
            expect(stamped[0].innerBlocks[2].attributes._veDuotone).toBeUndefined();
            expect(stamped[1].attributes._veDuotone).toBeUndefined();
            expect(stamped[2].attributes._veDuotone).toBeUndefined();
            expect(result.filters.indexOf(inner)).toBeLessThan(result.filters.indexOf(outer));
            expect(result.filters.split('<filter id=').length - 1).toBe(2);
            expect(result.css.split('{filter:').length - 1).toBe(2);
        });
    });

    describe('applyBackground', () => {
        it('applies WordPress defaults in Blade order', () => {
            expect(
                bg.applyBackground({
                    style: { background: { backgroundImage: { url: '/a.png' }, backgroundSize: 'contain', backgroundAttachment: 'fixed' } },
                })
            ).toEqual({
                backgroundImage: "url('/a.png')",
                backgroundSize: 'contain',
                backgroundPosition: '50% 50%',
                backgroundAttachment: 'fixed',
            });
        });

        it('percent-encodes every character that could close url()', () => {
            expect(bg.encodeBackgroundUrl("a b'c\"d(e)f\\g<h>i;j\tk\nl\x0Bm\fn\ro")).toBe(
                'a%20b%27c%22d%28e%29f%5Cg%3Ch%3Ei%3Bj%09k%0Al%0Bm%0Cn%0Do'
            );
        });

        it.each([
            ['javascript:', 'javascript:alert(1)'],
            ['mixed-case javascript:', 'JaVaScRiPt:alert(1)'],
            ['tab inside the scheme', 'java\tscript:alert(1)'],
            ['leading C0 control', '\x01javascript:alert(1)'],
            ['empty authority', 'javascript:///%0aalert(1)'],
            ['host and port', 'javascript:1/%0aalert(1)'],
            ['data:', 'data:image/svg+xml,<svg onload=alert(1)>'],
            ['vbscript:', 'vbscript:msgbox'],
            ['non-string', 42],
            ['blank', '   '],
        ])('emits nothing for a %s URL', (_label, url) => {
            expect(bg.applyBackground({ style: { background: { backgroundImage: { url } } } })).toEqual({});
            expect(bg.applyBackground({ style: { background: { backgroundImage: url } } })).toEqual({});
        });

        it('drops unsafe size, position, repeat and attachment values', () => {
            expect(
                bg.applyBackground({
                    style: {
                        background: {
                            backgroundImage: '/a.png',
                            backgroundSize: 'cover;position:fixed',
                            backgroundPosition: '0 0}',
                            backgroundRepeat: 'no-repeat;color:red',
                            backgroundAttachment: 'evil',
                        },
                    },
                })
            ).toEqual({ backgroundImage: "url('/a.png')", backgroundSize: 'cover' });
        });
    });

    describe('phpNumberFormat', () => {
        it.each([
            [0.11115, 4, '0.1112'],
            [0.33335, 4, '0.3334'],
            [0.285, 2, '0.29'],
            [1.005, 2, '1.01'],
            [0.99995, 4, '1.0000'],
            [2.5, 0, '3'],
            [5e-5, 4, '0.0001'],
            [1e-8, 4, '0.0000'],
            [-1e-8, 4, '0.0000'],
            [0.5019607843137255, 4, '0.5020'],
            [123.4, 0, '123'],
        ])('number_format(%s, %s) is %s', (value, places, expected) => {
            expect(num.phpNumberFormat(value, places)).toBe(expected);
        });
    });
});
