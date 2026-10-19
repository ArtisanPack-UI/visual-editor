/**
 * Screen-size visibility CSS for the Vue renderer — mirrors the React
 * renderer's `visibility.test.ts` device-key cases (#974).
 */

import { describe, expect, it } from 'vitest';

import { setBreakpoints, stampVisibilityScopes } from '../src/visibility';
import type { Block } from '../src/types';

describe('stampVisibilityScopes — device keys (#974)', () => {
    const defaults = [
        { key: 'tablet', maxWidthPx: 1023, previewWidthPx: 768 },
        { key: 'mobile', maxWidthPx: 767, previewWidthPx: 375 },
        { key: 'sm', minWidthPx: 640 },
        { key: 'md', minWidthPx: 768 },
        { key: 'lg', minWidthPx: 1024 },
        { key: 'xl', minWidthPx: 1280 },
        { key: '2xl', minWidthPx: 1536 },
    ];

    function cssFor(keys: string[]): string {
        setBreakpoints(defaults);

        return stampVisibilityScopes([
            { name: 'a', attributes: { _veHiddenBreakpoints: keys }, innerBlocks: [] },
        ] as unknown as Block[]).css;
    }

    it('hides `mobile` at max-width 767px', () => {
        expect(cssFor(['mobile'])).toBe('@media (max-width:767px){.ve-vis-1{display:none !important;}}');
    });

    it('hides `tablet` at max-width 1023px', () => {
        expect(cssFor(['tablet'])).toBe('@media (max-width:1023px){.ve-vis-1{display:none !important;}}');
    });

    it('combines device keys with legacy ranges', () => {
        expect(cssFor(['mobile', 'xl'])).toBe(
            '@media (max-width:767px){.ve-vis-1{display:none !important;}}'
            + '@media (min-width:1280px) and (max-width:1535px){.ve-vis-1{display:none !important;}}',
        );
    });

    it('follows a host-narrowed device width', () => {
        setBreakpoints({ mobile: 599 });

        const { css } = stampVisibilityScopes([
            { name: 'a', attributes: { _veHiddenBreakpoints: ['mobile'] }, innerBlocks: [] },
        ] as unknown as Block[]);

        expect(css).toBe('@media (max-width:599px){.ve-vis-1{display:none !important;}}');
    });
});
