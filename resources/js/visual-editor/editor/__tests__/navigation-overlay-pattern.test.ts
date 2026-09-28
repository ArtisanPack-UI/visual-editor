import { describe, expect, it } from 'vitest';

import { editorSettings } from '../../editor-settings';
import {
    NAVIGATION_OVERLAY_PATTERN_CONTENT,
    NAVIGATION_OVERLAY_PATTERN_NAME,
    getNavigationOverlayPattern,
} from '../navigation-overlay-pattern';

describe('navigation overlay seed pattern (#809)', () => {
    it('uses the name upstream Create Overlay looks up', () => {
        // `useCreateOverlayTemplatePart` calls
        // `getPatternBySlug('core/navigation-overlay')`.
        expect(NAVIGATION_OVERLAY_PATTERN_NAME).toBe('core/navigation-overlay');
    });

    it('seeds only blocks the package registers', () => {
        // Anything unregistered makes `createBlock` recurse on the
        // (also unregistered) `core/missing` fallback.
        const names = [
            ...NAVIGATION_OVERLAY_PATTERN_CONTENT.matchAll(/<!-- wp:([a-z0-9/-]+)/g),
        ].map((match) => match[1]);

        expect(names).toEqual(['navigation']);
    });

    it('stays out of the inserter', () => {
        expect(getNavigationOverlayPattern().inserter).toBe(false);
    });

    it('is shipped through the shared block-editor settings', () => {
        const patterns = (
            editorSettings as unknown as {
                __experimentalBlockPatterns?: Array<{ name: string }>;
            }
        ).__experimentalBlockPatterns;

        expect(patterns?.map((pattern) => pattern.name)).toContain(
            'core/navigation-overlay',
        );
    });
});
