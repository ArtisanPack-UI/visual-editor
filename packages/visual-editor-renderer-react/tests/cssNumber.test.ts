import { describe, expect, it } from 'vitest';
import { cssNumber } from '../src/support/cssNumber';
import { cssNumber as vueCssNumber } from '../../visual-editor-renderer-vue/src/support/cssNumber';

// Expected values are PHP `BlockSupports::cssNumber()` output (R-13).
describe.each([
    ['react', cssNumber],
    ['vue', vueCssNumber],
])('%s cssNumber', (_renderer, format) => {
    it.each([
        [1e-8, '0'],
        [-1e-8, '0'],
        [0.00001, '0'],
        [0.00009999, '0.0001'],
        [0.0001, '0.0001'],
        [12.5, '12.5'],
        [0.03125, '0.03125'],
        [1.23456789, '1.23456789'],
        [40, '40'],
        [0, '0'],
        [99999999999999, '99999999999999'],
        [1e14, '100000000000000'],
        [2e15, '2000000000000000'],
    ])('%s → %s', (value, expected) => {
        expect(format(value)).toBe(expected);
    });
});

describe('cover min-height (R-13)', () => {
    it('never writes an exponent', async () => {
        const { renderToStaticMarkup } = await import('react-dom/server');
        const { createElement } = await import('react');
        const { CoverBlock } = await import('../src/blocks/core/design');

        const html = renderToStaticMarkup(
            createElement(CoverBlock, {
                name: 'core/cover',
                attributes: { minHeight: 1e-8, minHeightUnit: 'vh' },
                innerBlocks: [],
            } as never)
        );

        expect(html).toContain('min-height:0vh');
        expect(html).not.toContain('e-8');
    });
});

describe('vue cover min-height (R-13)', () => {
    it('never writes an exponent', async () => {
        const { createSSRApp, h } = await import('vue');
        const { renderToString } = await import('@vue/server-renderer');
        const { CoverBlock } = await import('../../visual-editor-renderer-vue/src/blocks/core/design');

        const html = await renderToString(
            createSSRApp({
                render: () => h(CoverBlock, { name: 'core/cover', attributes: { minHeight: 1e-8, minHeightUnit: 'vh' }, innerBlocks: [] }),
            })
        );

        expect(html).toContain('min-height:0vh');
        expect(html).not.toContain('e-8');
    });
});
