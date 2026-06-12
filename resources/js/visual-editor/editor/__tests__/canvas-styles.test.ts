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

import {
    DEFAULT_CANVAS_STYLES,
    POST_EDITOR_FRAMING_STYLES,
} from '../../editor-settings';
import { canvasStyles } from '../canvas-styles';

describe('canvasStyles', () => {
    it('exposes every sheet as a `{ css: string }` entry — the shape BlockCanvas injects', () => {
        expect(canvasStyles.length).toBeGreaterThan(0);

        for (const entry of canvasStyles) {
            expect(Object.keys(entry)).toEqual(['css']);
            expect(typeof entry.css).toBe('string');
        }
    });

    it('bundles the theme token bridge, the three @wordpress sheet groups, the canvas baseline, alignment overrides, and the post-editor framing', () => {
        // token bridge + components + block-editor (style + content)
        // + block-library (style + editor) + LAYOUT_BASELINE
        // + accordion + tabs + editor-tweaks (interactive blocks; #497)
        // + grid (#498) + marquee (#500) + social-icons (#501)
        // + DEFAULT_CANVAS_STYLES + ALIGNMENT_OVERRIDE_STYLES
        // + POST_EDITOR_FRAMING_STYLES (Keystone #47 — site-editor
        // canvases skip the framing entry but keep the alignment
        // overrides so the wide/full toolbar buttons take effect there
        // too).
        expect(canvasStyles).toHaveLength(16);
    });

    it('places DEFAULT_CANVAS_STYLES before POST_EDITOR_FRAMING_STYLES so the framing wins the cascade', () => {
        const defaultsIndex = canvasStyles.findIndex(
            (entry) => entry.css === DEFAULT_CANVAS_STYLES
        );
        const framingIndex = canvasStyles.findIndex(
            (entry) => entry.css === POST_EDITOR_FRAMING_STYLES
        );

        expect(defaultsIndex).toBeGreaterThanOrEqual(0);
        expect(framingIndex).toBeGreaterThan(defaultsIndex);
        // Framing is the cascade anchor for the post editor's page-like
        // visual; if anything else were to land after it, that entry
        // would have to be intentional (theme.json bridge for the post
        // editor will land after framing).
        expect(framingIndex).toBe(canvasStyles.length - 1);
    });
});
