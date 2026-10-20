/**
 * Block-supports compiler tests (#920) — Vue renderer.
 *
 * `compileBlockSupports()` must emit the classes and declarations of the
 * Blade `BlockSupports::compile()` in the same order; the markup-parity
 * goldens pin whole blocks, so these cover each support and its edge cases.
 */

import { describe, expect, it } from 'vitest';
import { compileBlockSupports, compileCoverOverlay, splitBackgroundSupports } from '../src/support/blockSupports';

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
            style: { background: 'linear-gradient(red, blue)', backgroundImage: "url('https://example.com/a.jpg')" },
        });
        expect(split.wrapper).toEqual({
            classNames: ['has-base-color', 'has-text-color'],
            style: { backgroundSize: 'cover', padding: '1rem' },
        });
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
});
