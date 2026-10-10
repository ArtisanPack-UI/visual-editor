/**
 * Tests for the editor-canvas visibility helpers (#805). The range
 * expectations mirror `ScreenSizeRule::evaluate()` +
 * `BlockRenderer::wrapWithScreenSizeCss()` on the PHP side.
 */

import { describe, expect, it } from 'vitest';

import {
    hasServerEvaluatedRule,
    rangeMediaQuery,
    screenSizeHiddenKeys,
    screenSizeHiddenRanges,
    visibilityListViewSuffix,
} from '../canvas-rules';

const BREAKPOINTS = [
    { key: 'sm', minWidthPx: 640 },
    { key: 'md', minWidthPx: 768 },
    { key: 'lg', minWidthPx: 1024 },
    { key: 'xl', minWidthPx: 1280 },
    { key: '2xl', minWidthPx: 1536 },
];

describe('screenSizeHiddenKeys', () => {
    it('returns nothing without configured breakpoints', () => {
        expect(screenSizeHiddenKeys(undefined, BREAKPOINTS)).toEqual([]);
        expect(screenSizeHiddenKeys({ direction: 'hide', breakpoints: [] }, BREAKPOINTS)).toEqual([]);
    });

    it('hides the listed keys for direction hide', () => {
        expect(screenSizeHiddenKeys({ direction: 'hide', breakpoints: ['md', 'xl'] }, BREAKPOINTS))
            .toEqual(['md', 'xl']);
    });

    it('treats a missing direction as hide', () => {
        expect(screenSizeHiddenKeys({ breakpoints: ['lg'] }, BREAKPOINTS)).toEqual(['lg']);
    });

    it('hides every other known key for direction show', () => {
        expect(screenSizeHiddenKeys({ direction: 'show', breakpoints: ['sm'] }, BREAKPOINTS))
            .toEqual(['md', 'lg', 'xl', '2xl']);
    });

    it('ignores unknown and non-string keys', () => {
        expect(
            screenSizeHiddenKeys(
                { direction: 'hide', breakpoints: ['tablet', 'md', 42 as unknown as string] },
                BREAKPOINTS,
            ),
        ).toEqual(['md']);
    });
});

describe('screenSizeHiddenRanges', () => {
    it('spans each hidden breakpoint up to the next one', () => {
        expect(screenSizeHiddenRanges({ direction: 'hide', breakpoints: ['md'] }, BREAKPOINTS))
            .toEqual([{ minWidthPx: 768, maxWidthPx: 1023 }]);
    });

    it('leaves the widest breakpoint unbounded', () => {
        expect(screenSizeHiddenRanges({ direction: 'hide', breakpoints: ['2xl'] }, BREAKPOINTS))
            .toEqual([{ minWidthPx: 1536, maxWidthPx: null }]);
    });

    it('orders by min-width regardless of registry order', () => {
        const shuffled = [BREAKPOINTS[2], BREAKPOINTS[0], BREAKPOINTS[1]];

        expect(screenSizeHiddenRanges({ direction: 'hide', breakpoints: ['sm'] }, shuffled))
            .toEqual([{ minWidthPx: 640, maxWidthPx: 767 }]);
    });

    it('skips non-positive min-widths', () => {
        expect(
            screenSizeHiddenRanges({ direction: 'hide', breakpoints: ['zero'] }, [{ key: 'zero', minWidthPx: 0 }]),
        ).toEqual([]);
    });
});

describe('rangeMediaQuery', () => {
    it('matches the front-end media query format', () => {
        expect(rangeMediaQuery({ minWidthPx: 768, maxWidthPx: 1023 }))
            .toBe('(min-width:768px) and (max-width:1023px)');
        expect(rangeMediaQuery({ minWidthPx: 1536, maxWidthPx: null })).toBe('(min-width:1536px)');
    });
});

describe('hasServerEvaluatedRule', () => {
    it('is false for no rules or screen-size only', () => {
        expect(hasServerEvaluatedRule(null)).toBe(false);
        expect(hasServerEvaluatedRule({ screenSize: { direction: 'hide', breakpoints: ['md'] } })).toBe(false);
    });

    it('is true for master hide and request / user / schedule rules', () => {
        expect(hasServerEvaluatedRule({ hide: { hidden: true } })).toBe(true);
        expect(hasServerEvaluatedRule({ loginState: { state: 'loggedIn' } })).toBe(true);
        expect(hasServerEvaluatedRule({ recurring: { windows: [{ day: 1, start: '09:00', end: '17:00' }] } })).toBe(true);
    });
});

describe('visibilityListViewSuffix', () => {
    it('returns null without active rules', () => {
        expect(visibilityListViewSuffix(null, BREAKPOINTS)).toBeNull();
        expect(visibilityListViewSuffix({ loginState: { state: 'either' } }, BREAKPOINTS)).toBeNull();
    });

    it('prefers Hidden for the master toggle', () => {
        expect(
            visibilityListViewSuffix(
                { hide: { hidden: true }, screenSize: { direction: 'hide', breakpoints: ['md'] } },
                BREAKPOINTS,
            ),
        ).toBe('Hidden');
    });

    it('flags screen-size gating', () => {
        expect(visibilityListViewSuffix({ screenSize: { direction: 'hide', breakpoints: ['md'] } }, BREAKPOINTS))
            .toBe('Hidden at some screen sizes');
    });

    it('does not flag a screen-size rule that hides nothing', () => {
        expect(visibilityListViewSuffix({ screenSize: { direction: 'hide', breakpoints: ['tablet'] } }, BREAKPOINTS))
            .toBeNull();
    });

    it('flags server-evaluated rules as conditional', () => {
        expect(visibilityListViewSuffix({ userRole: { roles: ['editor'] } }, BREAKPOINTS))
            .toBe('Conditionally visible');
    });
});
