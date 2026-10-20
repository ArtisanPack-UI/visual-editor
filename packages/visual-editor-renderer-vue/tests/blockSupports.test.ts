/**
 * Block-supports compiler tests (#920) — Vue renderer.
 *
 * `compileBlockSupports()` must emit the classes and declarations of the
 * Blade `BlockSupports::compile()` in the same order; the markup-parity
 * goldens pin whole blocks, so these cover each support and its edge cases.
 */

import { describe, expect, it } from 'vitest';
import { compileBlockSupports, compileCoverOverlay, splitBackgroundSupports } from '../src/support/blockSupports';
import { safeFontFamilyValue, safeGradientValue } from '../src/support/cssValue';

describe('compileBlockSupports', () => {
    it('emits nothing for a block without supports', () => {
        expect(compileBlockSupports({ content: 'Hi' })).toEqual({ classNames: [], style: {} });
    });

    it('lets palette slugs win over custom colour, font size and font family values', () => {
        const compiled = compileBlockSupports({
            backgroundColor: 'Primary Accent',
            textColor: 'base',
            gradient: 'sunset',
            fontSize: 'large',
            fontFamily: 'heading',
            style: {
                color: { background: '#123456', text: '#ff0000', gradient: 'linear-gradient(red, blue)' },
                typography: { fontSize: '3rem', fontFamily: 'serif', lineHeight: '1.4' },
            },
        });

        expect(compiled.classNames).toEqual([
            'has-primary-accent-background-color',
            'has-background',
            'has-sunset-gradient-background',
            'has-base-color',
            'has-text-color',
            'has-large-font-size',
            'has-defined-font-size',
            'has-heading-font-family',
        ]);
        expect(compiled.style).toEqual({ lineHeight: '1.4' });
    });

    it('prints custom colours inline, expanding preset references', () => {
        const compiled = compileBlockSupports({
            style: {
                color: {
                    background: 'var:preset|color|accent',
                    gradient: 'linear-gradient(red, blue)',
                    text: '#ff0000',
                },
            },
        });

        expect(compiled.classNames).toEqual(['has-background', 'has-text-color']);
        expect(Object.entries(compiled.style)).toEqual([
            ['backgroundColor', 'var(--wp--preset--color--accent)'],
            ['background', 'linear-gradient(red, blue)'],
            ['color', '#ff0000'],
        ]);
    });

    it('adds the preset pick class for a marker text slug, deduping its marker', () => {
        expect(compileBlockSupports({ textColor: 'text' }).classNames).toEqual(['has-text-color', 'has-text-color-preset']);
    });

    it('prints padding and margin as one value or per side, then Block spacing', () => {
        const compiled = compileBlockSupports({
            style: {
                spacing: {
                    padding: '2rem',
                    margin: { top: '1rem', left: 'var:preset|spacing|40', right: '' },
                    blockGap: { top: '1rem', left: '2rem' },
                },
            },
        });

        expect(Object.entries(compiled.style)).toEqual([
            ['padding', '2rem'],
            ['marginTop', '1rem'],
            ['marginLeft', 'var(--wp--preset--spacing--40)'],
            ['rowGap', '1rem'],
            ['columnGap', '2rem'],
        ]);
    });

    it('prints a string Block spacing as the custom property', () => {
        expect(compileBlockSupports({ style: { spacing: { blockGap: '1.5rem' } } }).style).toEqual({
            '--wp--style--block-gap': '1.5rem',
        });
    });

    it('defaults a root border width to a solid style', () => {
        const compiled = compileBlockSupports({ borderColor: 'contrast', style: { border: { width: '2px', radius: '8px' } } });

        expect(compiled.classNames).toEqual(['has-border-color', 'has-contrast-border-color']);
        expect(Object.entries(compiled.style)).toEqual([
            ['borderRadius', '8px'],
            ['borderWidth', '2px'],
            ['borderStyle', 'solid'],
        ]);
    });

    it('keeps an explicit border style', () => {
        expect(compileBlockSupports({ style: { border: { width: '2px', style: 'dashed' } } }).style).toEqual({
            borderStyle: 'dashed',
            borderWidth: '2px',
        });
    });

    it('prints per-corner radii and gives a side-only width its own solid style', () => {
        const compiled = compileBlockSupports({
            style: {
                border: {
                    radius: { topLeft: '4px', bottomRight: '12px' },
                    top: { width: '3px', color: 'red' },
                    left: { width: '1px', style: 'dashed' },
                },
            },
        });

        expect(Object.entries(compiled.style)).toEqual([
            ['borderTopLeftRadius', '4px'],
            ['borderBottomRightRadius', '12px'],
            ['borderTopColor', 'red'],
            ['borderTopWidth', '3px'],
            ['borderLeftStyle', 'dashed'],
            ['borderLeftWidth', '1px'],
            ['borderTopStyle', 'solid'],
        ]);
    });

    it('prints every custom typography value in Blade order', () => {
        const compiled = compileBlockSupports({
            style: {
                typography: {
                    textDecoration: 'underline',
                    textTransform: 'uppercase',
                    letterSpacing: '0.05em',
                    lineHeight: 1.4,
                    fontStyle: 'italic',
                    fontWeight: '700',
                    fontFamily: 'Georgia, serif',
                    fontSize: 'var:preset|font-size|large',
                },
            },
        });

        expect(Object.entries(compiled.style)).toEqual([
            ['fontSize', 'var(--wp--preset--font-size--large)'],
            ['fontFamily', 'Georgia, serif'],
            ['fontWeight', '700'],
            ['fontStyle', 'italic'],
            ['lineHeight', '1.4'],
            ['letterSpacing', '0.05em'],
            ['textTransform', 'uppercase'],
            ['textDecoration', 'underline'],
        ]);
    });

    it('lets an aspect ratio win over a min-height', () => {
        expect(compileBlockSupports({ style: { dimensions: { aspectRatio: '16/9', minHeight: '200px' } } })).toEqual({
            classNames: ['has-aspect-ratio'],
            style: { aspectRatio: '16/9', minHeight: 'unset' },
        });
        expect(compileBlockSupports({ style: { dimensions: { minHeight: '200px' } } }).style).toEqual({ minHeight: '200px' });
    });

    it('orders declarations colour, background, spacing, border, typography, dimensions', () => {
        const compiled = compileBlockSupports({
            style: {
                dimensions: { minHeight: '10px' },
                typography: { lineHeight: '1' },
                border: { width: '1px' },
                spacing: { padding: '1px' },
                background: { backgroundImage: { url: 'https://example.com/a.jpg' } },
                color: { text: 'red' },
            },
        });

        expect(Object.keys(compiled.style)).toEqual([
            'color',
            'backgroundImage',
            'backgroundSize',
            'padding',
            'borderWidth',
            'borderStyle',
            'lineHeight',
            'minHeight',
        ]);
    });

    it('ignores a legacy string `style` variant and non-object support bags', () => {
        expect(compileBlockSupports({ style: 'wide', backgroundColor: 'primary' })).toEqual({
            classNames: ['has-primary-background-color', 'has-background'],
            style: {},
        });
        expect(compileBlockSupports({ style: { border: '2px', typography: 'big', spacing: [] } })).toEqual({
            classNames: [],
            style: {},
        });
    });
});

describe('splitBackgroundSupports', () => {
    it('routes background classes and declarations away from the wrapper', () => {
        const split = splitBackgroundSupports(
            compileBlockSupports({
                backgroundColor: 'primary',
                textColor: 'base',
                style: {
                    color: { gradient: 'linear-gradient(red, blue)' },
                    background: { backgroundImage: 'https://example.com/a.jpg' },
                    spacing: { padding: '1rem' },
                },
            })
        );

        expect(split.background).toEqual({
            classNames: ['has-primary-background-color', 'has-background'],
            style: {
                background: 'linear-gradient(red, blue)',
                backgroundImage: "url('https://example.com/a.jpg')",
                backgroundSize: 'cover',
            },
        });
        expect(split.wrapper).toEqual({
            classNames: ['has-base-color', 'has-text-color'],
            style: { padding: '1rem' },
        });
    });

    it('keeps every background longhand with the image', () => {
        const split = splitBackgroundSupports(
            compileBlockSupports({
                style: {
                    background: {
                        backgroundImage: { url: 'https://example.com/a.jpg' },
                        backgroundSize: 'contain',
                        backgroundRepeat: 'no-repeat',
                        backgroundAttachment: 'fixed',
                    },
                    dimensions: { minHeight: '10rem' },
                },
            })
        );

        expect(Object.entries(split.background.style)).toEqual([
            ['backgroundImage', "url('https://example.com/a.jpg')"],
            ['backgroundSize', 'contain'],
            ['backgroundPosition', '50% 50%'],
            ['backgroundRepeat', 'no-repeat'],
            ['backgroundAttachment', 'fixed'],
        ]);
        expect(split.wrapper.style).toEqual({ minHeight: '10rem' });
    });
});

describe('compileBlockSupports (unsafe values)', () => {
    it('drops colour, spacing, border, typography and dimensions values that could add declarations', () => {
        const compiled = compileBlockSupports({
            style: {
                color: { background: 'red;position:fixed', text: 'red}body{color:red', gradient: 'linear-gradient(red, blue);top:0' },
                spacing: {
                    padding: '1rem; position:fixed; inset:0',
                    margin: { top: '1rem/* x', bottom: '2rem' },
                },
                border: {
                    radius: { topLeft: '4px{', bottomRight: '12px' },
                    width: '2px;color:red',
                    color: 'blue',
                    top: { width: '3px;', style: 'dashed' },
                },
                typography: {
                    fontSize: '1rem;color:red',
                    fontFamily: '"Inter";color:red',
                    lineHeight: '1.5',
                    letterSpacing: 'url(javascript:alert(1))',
                },
                dimensions: { aspectRatio: '16/9;position:fixed', minHeight: '200px' },
            },
        });

        expect(compiled.classNames).toEqual([]);
        expect(Object.entries(compiled.style)).toEqual([
            ['marginBottom', '2rem'],
            ['borderBottomRightRadius', '12px'],
            ['borderColor', 'blue'],
            ['borderTopStyle', 'dashed'],
            ['lineHeight', '1.5'],
            ['minHeight', '200px'],
        ]);
    });

    it('keeps legitimate preset, calc, clamp, shorthand and quoted font values', () => {
        const compiled = compileBlockSupports({
            style: {
                color: {
                    background: 'rgb(0 0 0 / 50%)',
                    text: 'var:preset|color|contrast',
                    gradient: 'var:preset|gradient|vivid-cyan',
                },
                spacing: { padding: 'calc(1rem + 2vw) clamp(1rem, 2vw, 3rem)', margin: '0 auto' },
                border: { radius: '8px 4px', width: '1px 2px' },
                typography: { fontFamily: '"Times New Roman", \'Open Sans\', serif', fontSize: 'clamp(1rem, 2.5vw, 2rem)' },
                dimensions: { aspectRatio: '16/9' },
            },
        });

        expect(Object.entries(compiled.style)).toEqual([
            ['backgroundColor', 'rgb(0 0 0 / 50%)'],
            ['background', 'var(--wp--preset--gradient--vivid-cyan)'],
            ['color', 'var(--wp--preset--color--contrast)'],
            ['padding', 'calc(1rem + 2vw) clamp(1rem, 2vw, 3rem)'],
            ['margin', '0 auto'],
            ['borderRadius', '8px 4px'],
            ['borderWidth', '1px 2px'],
            ['borderStyle', 'solid'],
            ['fontSize', 'clamp(1rem, 2.5vw, 2rem)'],
            ['fontFamily', '"Times New Roman", \'Open Sans\', serif'],
            ['aspectRatio', '16/9'],
            ['minHeight', 'unset'],
        ]);
        expect(compiled.classNames).toEqual(['has-background', 'has-text-color', 'has-aspect-ratio']);
    });

    it('drops a rejected root border width without forcing a solid style', () => {
        expect(compileBlockSupports({ style: { border: { width: '2px;color:red' } } }).style).toEqual({});
    });
});

describe('safeGradientValue', () => {
    it.each([
        'linear-gradient(135deg, #000 0%, #fff 100%)',
        'radial-gradient(circle at 50% 50%, rgba(6, 147, 227, 1) 0%, rgb(155, 81, 224) 100%)',
        'repeating-conic-gradient(from 0deg, color-mix(in srgb, red 50%, blue) 0 10%, var(--x) 10% 20%)',
        'Linear-Gradient(to right, hsl(0 0% 0%), calc(50% + 1px))',
        'var(--wp--preset--gradient--vivid-cyan)',
    ])('keeps %s', (value) => {
        expect(safeGradientValue(value)).toBe(value);
    });

    it.each([
        'linear-gradient(red, blue);position:fixed',
        'linear-gradient(red, blue)}body{color:red',
        'linear-gradient(red, blue), url(evil.png)',
        'linear-gradient(red, url(evil.png))',
        'url(javascript:alert(1))',
        'url(evil.png)',
        'image-set(evil.png 1x)',
        'linear-gradient(red, blue',
        'linear-gradient(red, blue))',
        'linear-gradient(red /* x */, blue)',
        'red',
        'var(--x) url(a)',
        '',
    ])('rejects %s', (value) => {
        expect(safeGradientValue(value)).toBeNull();
    });
});

describe('safeFontFamilyValue', () => {
    it.each(['Georgia, serif', '"Times New Roman", Times, serif', "'Open Sans', sans-serif", 'var(--wp--preset--font-family--body)'])(
        'keeps %s',
        (value) => {
            expect(safeFontFamilyValue(value)).toBe(value);
        }
    );

    it.each(['"Inter";color:red', '"Open Sans, serif', '"a;b", serif', 'Inter}body{color:red', '"x" /* y', ''])('rejects %s', (value) => {
        expect(safeFontFamilyValue(value)).toBeNull();
    });
});

describe('compileCoverOverlay', () => {
    it('lets the overlay slug win over the custom overlay colour and layers the gradient', () => {
        expect(compileCoverOverlay({ overlayColor: 'contrast', customOverlayColor: '#000', customGradient: 'linear-gradient(red, blue)' })).toEqual({
            classNames: ['has-contrast-background-color', 'has-background'],
            style: { background: 'linear-gradient(red, blue)' },
        });
        expect(compileCoverOverlay({ customOverlayColor: 'var:preset|color|primary' })).toEqual({
            classNames: ['has-background'],
            style: { backgroundColor: 'var(--wp--preset--color--primary)' },
        });
        expect(compileCoverOverlay({})).toEqual({ classNames: [], style: {} });
    });

    it('drops an unsafe custom overlay colour or gradient', () => {
        expect(
            compileCoverOverlay({ customOverlayColor: '#000;position:fixed', customGradient: 'linear-gradient(red, blue), url(evil.png)' })
        ).toEqual({ classNames: [], style: {} });
    });
});
