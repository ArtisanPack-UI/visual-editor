/**
 * Tests for the `ap.visual-editor.canvas-styles` extension filter.
 *
 * `canvas-styles.ts` evaluates `applyFilters` synchronously at module-
 * load time inside an IIFE and freezes the resulting `canvasStyles`
 * export for the rest of the session. That's the timing contract the
 * public docblock calls out — so these tests register the callback
 * FIRST via a `@wordpress/hooks` mock, then dynamically import the
 * module so the IIFE runs against the callback. `vi.resetModules` in
 * `beforeEach` gives each case a fresh module load; the sibling
 * `canvas-styles.test.ts` covers the no-callback base behavior and
 * relies on real `@wordpress/hooks`, so mocking here stays isolated
 * to this file.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

type FilterCallback = (value: unknown, ...args: unknown[]) => unknown;

const filters = new Map<string, FilterCallback[]>();

vi.mock('@wordpress/hooks', () => ({
    addFilter: (hook: string, _id: string, callback: FilterCallback): void => {
        if (!filters.has(hook)) {
            filters.set(hook, []);
        }
        filters.get(hook)!.push(callback);
    },
    applyFilters: (hook: string, value: unknown, ...args: unknown[]): unknown => {
        const callbacks = filters.get(hook) ?? [];
        return callbacks.reduce(
            (acc, callback) => callback(acc, ...args),
            value
        );
    },
}));

beforeEach(() => {
    filters.clear();
    vi.resetModules();
});

async function loadCanvasStyles(): Promise<
    typeof import('../canvas-styles')
> {
    return await import('../canvas-styles');
}

function registerCanvasStylesFilter(callback: FilterCallback): void {
    filters.set('ap.visual-editor.canvas-styles', [callback]);
}

describe('ap.visual-editor.canvas-styles filter', () => {
    it('receives the base ordered list as a `CanvasStyle[]` copy the callback may mutate', async () => {
        let received: unknown = null;

        registerCanvasStylesFilter((value) => {
            received = value;
            return value;
        });

        const { canvasStyles } = await loadCanvasStyles();

        expect(Array.isArray(received)).toBe(true);
        const list = received as Array<{ css: unknown }>;
        expect(list.length).toBeGreaterThan(0);
        for (const entry of list) {
            expect(typeof entry.css).toBe('string');
        }
        // The IIFE spreads `baseCanvasStyles` into a new array before
        // passing it through the filter, so mutating it here MUST NOT
        // affect the final export.
        list.push({ css: 'mutation-that-should-not-appear' });
        expect(
            canvasStyles.some(
                (entry) => entry.css === 'mutation-that-should-not-appear'
            )
        ).toBe(false);
    });

    it('appends a callback-supplied stylesheet to canvasStyles', async () => {
        registerCanvasStylesFilter((value) => {
            const list = value as Array<{ css: string }>;
            return [...list, { css: '.injected { color: red; }' }];
        });

        const { canvasStyles } = await loadCanvasStyles();

        expect(canvasStyles[canvasStyles.length - 1]?.css).toBe(
            '.injected { color: red; }'
        );
    });

    it('falls back to the base list when a callback returns a non-array', async () => {
        registerCanvasStylesFilter(() => 'not-an-array');

        const { canvasStyles } = await loadCanvasStyles();

        expect(canvasStyles.length).toBeGreaterThan(0);
        for (const entry of canvasStyles) {
            expect(typeof entry.css).toBe('string');
        }
    });

    it('drops entries whose `css` property is not a string', async () => {
        // Marker string is deliberately unique so it can't collide with
        // real CSS text that Vite's `?inline` transform would inject at
        // build time (or that any of the module-defined constants
        // happen to contain).
        const marker = '__ap_filter_test_marker__';

        registerCanvasStylesFilter((value) => {
            const list = value as Array<{ css: string }>;
            return [
                ...list,
                { css: `.${marker}-first { color: green; }` },
                { css: 42 as unknown as string }, // wrong type — dropped
                { notCss: 'foo' } as unknown as { css: string }, // no css — dropped
                { css: `.${marker}-second { color: blue; }` },
            ];
        });

        const { canvasStyles } = await loadCanvasStyles();

        const injected = canvasStyles
            .map((entry) => entry.css)
            .filter((css) => css.includes(marker));

        expect(injected).toEqual([
            `.${marker}-first { color: green; }`,
            `.${marker}-second { color: blue; }`,
        ]);
    });

    it('drops null / non-object entries returned in the callback array', async () => {
        registerCanvasStylesFilter((value) => {
            const list = value as Array<{ css: string }>;
            return [
                ...list,
                null as unknown as { css: string },
                undefined as unknown as { css: string },
                'a raw string' as unknown as { css: string },
                42 as unknown as { css: string },
                { css: '.survivor { color: purple; }' },
            ];
        });

        const { canvasStyles } = await loadCanvasStyles();

        // Every entry that survived the filter is a well-formed
        // `{ css: string }` — the primitive garbage must not leak into
        // the array `BlockCanvas` hands to `__unstableEditorStyles`.
        for (const entry of canvasStyles) {
            expect(entry).not.toBeNull();
            expect(typeof entry).toBe('object');
            expect(typeof entry.css).toBe('string');
        }

        expect(
            canvasStyles.some(
                (entry) => entry.css === '.survivor { color: purple; }'
            )
        ).toBe(true);
    });
});
