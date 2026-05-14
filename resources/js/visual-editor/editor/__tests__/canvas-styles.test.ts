/**
 * `canvas-styles.ts` assembles the stylesheet bundle handed to
 * `BlockCanvas`'s `styles` prop so the iframe canvas isn't styled by
 * browser defaults (#347).
 *
 * The individual `@wordpress/*` sheets are resolved by Vite's `?inline`
 * transform at build/dev time; under vitest (`css: false`) those
 * imports collapse to empty strings, so this suite asserts the bundle's
 * *structure and ordering* rather than re-checking Gutenberg's CSS
 * text. `DEFAULT_CANVAS_STYLES` is a real TypeScript constant, so the
 * cascade-order anchor is still verifiable here.
 */

import { describe, expect, it } from 'vitest';

import { DEFAULT_CANVAS_STYLES } from '../../editor-settings';
import { canvasStyles } from '../canvas-styles';

describe('canvasStyles', () => {
    it('exposes every sheet as a `{ css: string }` entry — the shape BlockCanvas injects', () => {
        expect(canvasStyles.length).toBeGreaterThan(0);

        for (const entry of canvasStyles) {
            expect(Object.keys(entry)).toEqual(['css']);
            expect(typeof entry.css).toBe('string');
        }
    });

    it('bundles the theme token bridge, the three @wordpress sheet groups, and the canvas baseline', () => {
        // token bridge + components + block-editor (style + content)
        // + block-library (style + editor) + DEFAULT_CANVAS_STYLES.
        expect(canvasStyles).toHaveLength(7);
    });

    it('ends with DEFAULT_CANVAS_STYLES so the package typographic baseline wins the cascade', () => {
        expect(canvasStyles[canvasStyles.length - 1]?.css).toBe(
            DEFAULT_CANVAS_STYLES
        );
    });
});
