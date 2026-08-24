/**
 * Shared CSS-value whitelist + flex arbitrary-value emission guard (#720).
 *
 * `safeCssValue` is the one grammar all three renderers use to decide what
 * may be written into a `<style>` block; `buildArbitraryStyles` must route
 * every arbitrary flex value through it and drop the rule on rejection.
 */

import { describe, expect, it } from 'vitest';

import { safeCssValue } from '../src/support/cssValue';
import { buildArbitraryStyles } from '../src/support/flex-serializer';

describe('safeCssValue', () => {
    it.each([
        '100px',
        '1.5rem',
        '50%',
        '-5',
        'auto',
        'fit-content',
        'calc(50% - var(--wp--style--block-gap, 0.5em) * 0.5)',
        'calc(100% / 3)',
    ])('returns a safe value unchanged: %s', (value) => {
        expect(safeCssValue(value)).toBe(value);
    });

    it.each(['', '   '])('rejects empty / whitespace: %j', (value) => {
        expect(safeCssValue(value)).toBeNull();
    });

    it.each([
        '10px}body{display:none',
        'red;color:blue',
        '100px</style><script>alert(1)</script>',
        'x:expression(alert(1))',
        '1px}@import url(evil)',
        '"1px',
        '\\31 px',
    ])('rejects a CSS-structural breakout value: %j', (value) => {
        expect(safeCssValue(value)).toBeNull();
    });

    it.each([
        '50%/*',
        '50%*/',
        'url(//evil.example/x.png)',
    ])('rejects a CSS comment / protocol-relative digraph: %j', (value) => {
        expect(safeCssValue(value)).toBeNull();
    });

    it('rejects embedded Unicode whitespace so it matches the Blade twin', () => {
        // ECMAScript `\s` matches U+00A0 but PCRE `\s` (no /u) does not; the
        // explicit ASCII whitespace class drops it on both sides.
        expect(safeCssValue('200px\u00A0')).toBeNull();
    });
});

describe('buildArbitraryStyles (#720 guard)', () => {
    it('emits safe arbitrary values verbatim', () => {
        const css = buildArbitraryStyles([
            { className: 'ap-basis-[200px]', property: 'flex-basis', value: '200px', breakpoint: 'base' },
            { className: 'ap-gap-x-[1rem]', property: 'column-gap', value: '1rem', breakpoint: 'base' },
        ]);

        expect(css).toContain('flex-basis: 200px;');
        expect(css).toContain('column-gap: 1rem;');
    });

    it('drops a hostile arbitrary value so it never reaches the stylesheet', () => {
        const css = buildArbitraryStyles([
            { className: 'ap-basis-x', property: 'flex-basis', value: '200px}body{display:none', breakpoint: 'base' },
        ]);

        expect(css).toBe('');
    });

    it('skips a breakpoint whose only rule is hostile, emitting no @media block', () => {
        const css = buildArbitraryStyles([
            { className: 'ap-basis-[200px]', property: 'flex-basis', value: '200px', breakpoint: 'base' },
            { className: 'md:ap-basis-x', property: 'flex-basis', value: '50%}html{opacity:0', breakpoint: 'md' },
        ]);

        expect(css).toContain('flex-basis: 200px;');
        expect(css).not.toContain('@media');
        expect(css).not.toContain('opacity');
    });
});
